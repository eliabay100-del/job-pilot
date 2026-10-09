<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\JobMatch;
use App\Models\Skill;
use App\Models\User;
use App\Models\CandidateSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'job_seeker']);
        $this->profile = CandidateProfile::create([
            'user_id' => $this->user->id,
            'city' => 'Addis Ababa',
            'region' => 'Addis Ababa',
            'years_experience' => 4,
        ]);

        Sanctum::actingAs($this->user);
    }

    private function skill(string $name): Skill
    {
        return Skill::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeJob(array $attributes = []): Job
    {
        static $sequence = 0;
        $sequence++;

        return Job::create(array_merge([
            'title' => "Data Analyst {$sequence}",
            'slug' => "data-analyst-{$sequence}",
            'description' => 'Analyse platform data with SQL.',
            'city' => 'Addis Ababa',
            'work_mode' => 'onsite',
            'employment_type' => 'full_time',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_matches_require_authentication(): void
    {
        auth()->forgetGuards();

        $this->getJson('/api/v1/matches')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->getJson('/api/v1/jobs/1/match')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_index_returns_ranked_matches_with_an_explanation_and_stores_them(): void
    {
        $sql = $this->skill('SQL');
        CandidateSkill::create([
            'candidate_profile_id' => $this->profile->id,
            'skill_id' => $sql->id,
            'level' => 4,
            'years_using' => 4,
        ]);

        $strong = $this->makeJob();
        $strong->skills()->attach($sql->id, ['is_required' => true]);

        $weak = $this->makeJob(['title' => 'Power BI Lead', 'slug' => 'power-bi-lead']);
        $weak->skills()->attach($this->skill('Power BI')->id, ['is_required' => true]);

        $response = $this->getJson('/api/v1/matches')->assertOk();

        $ids = collect($response->json('data'))->pluck('job.id')->all();
        $this->assertSame([$strong->id, $weak->id], $ids);

        $scores = collect($response->json('data'))->pluck('match.overall_score')->all();
        $this->assertSame($scores, collect($scores)->sortByDesc(fn ($score) => $score)->values()->all());

        $top = $response->json('data.0');
        $this->assertSame('Data Analyst 1', $top['job']['title']);
        $this->assertArrayHasKey('component_scores', $top['match']);
        $this->assertEquals(100.0, $top['match']['component_scores']['skills']['score']);
        $this->assertSame('SQL', $top['match']['matched_skills'][0]['name']);
        $this->assertNotEmpty($top['match']['recommendation']);
        $this->assertSame((string) config('matching.model_version'), $top['match']['model_version']);

        $bottom = $response->json('data.1');
        $this->assertSame('Power BI', $bottom['match']['missing_skills'][0]['name']);
        $this->assertContains('missing_required_skills', $bottom['match']['hard_requirement_flags']);

        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.scored_jobs'));

        $this->assertDatabaseCount('job_matches', 2);
        $this->assertDatabaseHas('job_matches', [
            'candidate_profile_id' => $this->profile->id,
            'job_id' => $strong->id,
        ]);
    }

    public function test_index_scores_only_published_and_unexpired_jobs(): void
    {
        $published = $this->makeJob();
        $this->makeJob(['status' => 'draft', 'title' => 'Draft', 'slug' => 'draft-role']);
        $this->makeJob(['status' => 'pending_review', 'title' => 'Held', 'slug' => 'held-role']);
        $this->makeJob(['title' => 'Lapsed', 'slug' => 'lapsed-role', 'expires_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/matches')->assertOk();

        $this->assertSame([$published->id], collect($response->json('data'))->pluck('job.id')->all());
        $this->assertDatabaseCount('job_matches', 1);
    }

    public function test_index_honours_limit_and_min_score(): void
    {
        $sql = $this->skill('SQL');
        CandidateSkill::create([
            'candidate_profile_id' => $this->profile->id,
            'skill_id' => $sql->id,
            'level' => 4,
            'years_using' => 4,
        ]);

        $strong = $this->makeJob();
        $strong->skills()->attach($sql->id, ['is_required' => true]);
        $weak = $this->makeJob(['title' => 'Power BI Lead', 'slug' => 'power-bi-lead']);
        $weak->skills()->attach($this->skill('Power BI')->id, ['is_required' => true]);

        $this->getJson('/api/v1/matches?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job.id', $strong->id)
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/matches?min_score=70')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.job.id', $strong->id);
    }

    public function test_index_rejects_invalid_query_parameters(): void
    {
        $this->getJson('/api/v1/matches?limit=0')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['limit']);

        $this->getJson('/api/v1/matches?min_score=150')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['min_score']);
    }

    public function test_index_creates_a_profile_for_a_user_without_one(): void
    {
        $fresh = User::factory()->create(['role' => 'job_seeker']);
        Sanctum::actingAs($fresh);

        $this->makeJob();

        $this->getJson('/api/v1/matches')->assertOk();

        $this->assertDatabaseHas('candidate_profiles', ['user_id' => $fresh->id]);
        $this->assertDatabaseCount('job_matches', 1);
    }

    public function test_show_returns_the_breakdown_for_one_job_and_upserts_a_single_row(): void
    {
        $job = $this->makeJob(['experience_years_min' => 3, 'experience_years_max' => 6]);

        $first = $this->getJson("/api/v1/jobs/{$job->id}/match")->assertOk();
        $first->assertJsonPath('data.job.id', $job->id)
            ->assertJsonPath('data.match.model_version', config('matching.model_version'));

        // json_encode renders 100.0 as 100, so compare numerically.
        $this->assertEquals(100.0, $first->json('data.match.component_scores.experience.score'));
        $this->assertNotEmpty($first->json('data.match.component_scores.location.detail'));

        $this->getJson("/api/v1/jobs/{$job->id}/match")->assertOk();

        $this->assertDatabaseCount('job_matches', 1);
    }

    public function test_show_is_denied_for_a_listing_that_is_not_public(): void
    {
        $held = $this->makeJob(['status' => 'pending_review', 'title' => 'Held', 'slug' => 'held-role']);

        $this->getJson("/api/v1/jobs/{$held->id}/match")
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->assertDatabaseCount('job_matches', 0);
    }

    public function test_show_404s_for_an_unknown_or_non_numeric_job(): void
    {
        $this->getJson('/api/v1/jobs/999999/match')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/api/v1/jobs/undefined/match')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_a_candidate_only_ever_sees_their_own_matches(): void
    {
        $other = User::factory()->create(['role' => 'job_seeker']);
        $otherProfile = CandidateProfile::create(['user_id' => $other->id, 'years_experience' => 9]);
        $job = $this->makeJob();

        JobMatch::create([
            'candidate_profile_id' => $otherProfile->id,
            'job_id' => $job->id,
            'overall_score' => 12.34,
            'component_scores' => ['skills' => ['score' => 10.0, 'weight' => 30, 'applied' => true, 'detail' => null]],
            'matched_skills' => [],
            'missing_skills' => [],
            'weak_areas' => [],
            'hard_requirement_flags' => [],
            'model_version' => 'someone-else',
            'computed_at' => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/v1/matches')->assertOk();

        $this->assertNotSame(12.34, $response->json('data.0.match.overall_score'));
        $this->assertDatabaseHas('job_matches', [
            'candidate_profile_id' => $otherProfile->id,
            'model_version' => 'someone-else',
        ]);
        $this->assertDatabaseCount('job_matches', 2);
    }

    public function test_the_compute_command_stores_matches_for_every_profile(): void
    {
        $this->makeJob();
        $this->makeJob(['title' => 'Second', 'slug' => 'second-role']);
        CandidateProfile::create(['user_id' => User::factory()->create()->id, 'years_experience' => 1]);

        $this->artisan('matching:compute')->assertSuccessful();

        // 2 seeded profiles (this test's + the extra one) x 2 published jobs.
        $this->assertDatabaseCount('job_matches', 4);

        $this->artisan('matching:compute')->assertSuccessful();
        $this->assertDatabaseCount('job_matches', 4);
    }

    public function test_the_compute_command_can_be_scoped_to_one_profile_and_job(): void
    {
        $job = $this->makeJob();
        $this->makeJob(['title' => 'Second', 'slug' => 'second-role']);
        CandidateProfile::create(['user_id' => User::factory()->create()->id, 'years_experience' => 1]);

        $this->artisan('matching:compute', ['--profile' => $this->profile->id, '--job' => $job->id])
            ->assertSuccessful();

        $this->assertDatabaseCount('job_matches', 1);
        $this->assertDatabaseHas('job_matches', [
            'candidate_profile_id' => $this->profile->id,
            'job_id' => $job->id,
        ]);
    }
}
