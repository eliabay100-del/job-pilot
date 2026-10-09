<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Domain\Matching\Actions\ScoreJobMatch;
use App\Domain\Matching\Contracts\SemanticSimilarityProvider;
use App\Models\CandidateEducation;
use App\Models\CandidatePreference;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\EducationLevel;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Engine-level tests: every assertion pins the arithmetic from
 * config/matching.php so a weight or formula change fails loudly.
 */
class MatchScoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['matching.model_version' => 'test-v1']);
    }

    private function scorer(): ScoreJobMatch
    {
        return app(ScoreJobMatch::class);
    }

    private function skill(string $name): Skill
    {
        return Skill::firstOrCreate(['slug' => strtolower(str_replace(' ', '-', $name))], ['name' => $name]);
    }

    private function level(string $name, int $rank): EducationLevel
    {
        return EducationLevel::firstOrCreate(['name' => $name], ['rank' => $rank]);
    }

    /** @param array<string, mixed> $attributes */
    private function profile(array $attributes = []): CandidateProfile
    {
        return CandidateProfile::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'city' => 'Addis Ababa',
            'region' => 'Addis Ababa',
            'years_experience' => 4,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function job(array $attributes = []): Job
    {
        static $sequence = 0;
        $sequence++;

        return Job::create(array_merge([
            'title' => "Analyst {$sequence}",
            'slug' => "analyst-{$sequence}",
            'city' => 'Addis Ababa',
            'region' => 'Addis Ababa',
            'work_mode' => 'onsite',
            'employment_type' => 'full_time',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    private function giveSkill(CandidateProfile $profile, Skill $skill, ?int $yearsUsing = null): void
    {
        CandidateSkill::create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill->id,
            'level' => 4,
            'years_using' => $yearsUsing,
        ]);
    }

    public function test_a_fully_aligned_candidate_scores_one_hundred(): void
    {
        $bachelor = $this->level('Bachelor', 5);
        $laravel = $this->skill('Laravel');
        $sql = $this->skill('SQL');

        $profile = $this->profile();
        $this->giveSkill($profile, $laravel, 5);
        $this->giveSkill($profile, $sql, 6);
        CandidateEducation::create([
            'candidate_profile_id' => $profile->id,
            'degree' => 'BSc Software Engineering',
            'education_level_id' => $bachelor->id,
        ]);
        CandidatePreference::create([
            'candidate_profile_id' => $profile->id,
            'work_modes' => ['onsite', 'hybrid'],
            'locations' => ['Addis Ababa'],
            'employment_types' => ['full_time'],
            'salary_min_monthly_eth' => 20000,
        ]);

        $job = $this->job([
            'seniority' => 'mid',
            'experience_years_min' => 3,
            'experience_years_max' => 6,
            'min_education_level_id' => $bachelor->id,
            'salary_max_monthly' => 30000,
        ]);
        $job->skills()->attach($laravel->id, ['is_required' => true, 'min_years' => 3]);
        $job->skills()->attach($sql->id, ['is_required' => true, 'min_years' => 2]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame(100.0, $result->overallScore);
        $this->assertSame(0.0, $result->penalty);
        $this->assertSame([], $result->hardRequirementFlags);
        $this->assertSame('Strong candidate. Apply.', $result->recommendation);
        $this->assertSame('test-v1', $result->modelVersion);

        foreach (['skills', 'experience', 'education', 'seniority', 'location', 'work_mode', 'preference'] as $name) {
            $this->assertSame(100.0, $result->component($name)?->score, "component {$name}");
        }

        $this->assertFalse($result->component('semantic')?->isApplied());
        $this->assertCount(2, $result->matchedSkills);
    }

    public function test_missing_required_skill_is_listed_and_penalized(): void
    {
        $laravel = $this->skill('Laravel');
        $powerBi = $this->skill('Power BI');

        $profile = $this->profile();
        $this->giveSkill($profile, $laravel, 4);

        // Only skills (30) and location (10) can be scored for this pair.
        $job = $this->job(['city' => 'Addis Ababa']);
        $job->skills()->attach($laravel->id, ['is_required' => true]);
        $job->skills()->attach($powerBi->id, ['is_required' => true]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame(50.0, $result->component('skills')?->score);
        $this->assertSame(4.0, $result->penalty);
        $this->assertSame(['missing_required_skills'], $result->hardRequirementFlags);
        $this->assertSame([
            ['id' => $powerBi->id, 'name' => 'Power BI', 'is_required' => true, 'min_years' => null],
        ], $result->missingSkills);

        // (30*50 + 10*100) / 40 = 62.5, minus the 4-point penalty.
        $this->assertSame(58.5, $result->overallScore);
        $this->assertSame('Partial fit — consider closing the listed gaps first.', $result->recommendation);
    }

    public function test_nice_to_have_skills_never_trigger_a_penalty(): void
    {
        $laravel = $this->skill('Laravel');
        $docker = $this->skill('Docker');

        $profile = $this->profile();
        $this->giveSkill($profile, $laravel, 4);

        $job = $this->job();
        $job->skills()->attach($laravel->id, ['is_required' => true]);
        $job->skills()->attach($docker->id, ['is_required' => false]);

        $result = $this->scorer()->handle($profile, $job);

        // Required (2) earned of required (2) + optional (1) possible.
        $this->assertEqualsWithDelta(66.67, $result->component('skills')?->score, 0.01);
        $this->assertSame(0.0, $result->penalty);
        $this->assertSame([], $result->hardRequirementFlags);
    }

    public function test_skill_held_for_fewer_years_than_required_is_a_weak_area(): void
    {
        $laravel = $this->skill('Laravel');

        $profile = $this->profile();
        $this->giveSkill($profile, $laravel, 2);

        $job = $this->job();
        $job->skills()->attach($laravel->id, ['is_required' => true, 'min_years' => 5]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame(50.0, $result->component('skills')?->score);
        $this->assertSame([], $result->missingSkills);
        $this->assertSame(0.0, $result->penalty);
        $this->assertSame('skill_years', $result->weakAreas[0]['type']);
        $this->assertSame('Laravel', $result->weakAreas[0]['name']);
        $this->assertStringContainsString('2 years', $result->weakAreas[0]['message']);
    }

    public function test_experience_below_the_minimum_is_proportional_and_flagged(): void
    {
        $profile = $this->profile(['years_experience' => 2]);
        $job = $this->job(['experience_years_min' => 5, 'seniority' => null]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame(40.0, $result->component('experience')?->score);
        $this->assertContains('experience_below_min', $result->hardRequirementFlags);
        $this->assertSame(8.0, $result->penalty);

        // (20*40 + 10*100) / 30 = 60, minus 8.
        $this->assertSame(52.0, $result->overallScore);
    }

    public function test_experience_above_the_maximum_is_only_mildly_penalized(): void
    {
        $profile = $this->profile(['years_experience' => 12]);
        $job = $this->job(['experience_years_min' => 2, 'experience_years_max' => 6, 'seniority' => null]);

        $result = $this->scorer()->handle($profile, $job);

        // 100 - 5*(12-6) = 70, above the 60 floor.
        $this->assertSame(70.0, $result->component('experience')?->score);
        $this->assertSame(0.0, $result->penalty);
        $this->assertNotContains('experience_below_min', $result->hardRequirementFlags);
    }

    public function test_education_below_the_minimum_is_proportional_and_flagged(): void
    {
        $diploma = $this->level('Diploma', 3);
        $bachelor = $this->level('Bachelor', 5);

        $profile = $this->profile();
        CandidateEducation::create([
            'candidate_profile_id' => $profile->id,
            'degree' => 'Diploma in IT',
            'education_level_id' => $diploma->id,
        ]);

        $job = $this->job(['min_education_level_id' => $bachelor->id, 'seniority' => null]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame(60.0, $result->component('education')?->score);
        $this->assertContains('education_below_min', $result->hardRequirementFlags);
        $this->assertSame(8.0, $result->penalty);

        // (15*60 + 10*100) / 25 = 76, minus 8.
        $this->assertSame(68.0, $result->overallScore);
    }

    public function test_missing_education_scores_zero_for_that_component(): void
    {
        $bachelor = $this->level('Bachelor', 5);
        $profile = $this->profile();
        $job = $this->job(['min_education_level_id' => $bachelor->id, 'seniority' => null]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame(0.0, $result->component('education')?->score);
        $this->assertContains('education_below_min', $result->hardRequirementFlags);

        // (15*0 + 10*100) / 25 = 40, minus 8.
        $this->assertSame(32.0, $result->overallScore);
    }

    public function test_education_without_a_taxonomy_level_is_unscoreable_not_zero(): void
    {
        $bachelor = $this->level('Bachelor', 5);
        $profile = $this->profile();
        CandidateEducation::create([
            'candidate_profile_id' => $profile->id,
            'degree' => 'BSc Software Engineering',
        ]);

        $result = $this->scorer()->handle(
            $profile,
            $this->job(['min_education_level_id' => $bachelor->id, 'seniority' => null]),
        );

        $education = $result->component('education');
        $this->assertNotNull($education);
        $this->assertFalse($education->isApplied());
        $this->assertStringContainsString('no level set', (string) $education->detail);
        $this->assertNotContains('education_below_min', $result->hardRequirementFlags);
        $this->assertSame(0.0, $result->penalty);
    }

    public function test_seniority_is_derived_from_the_years_ladder(): void    {
        $over = $this->scorer()->handle(
            $this->profile(['years_experience' => 12]),
            $this->job(['seniority' => 'mid']),
        );
        // 12 years maps to "lead" (index 4) against "mid" (index 2): two above.
        $this->assertSame(70.0, $over->component('seniority')?->score);

        $under = $this->scorer()->handle(
            $this->profile(['years_experience' => 1]),
            $this->job(['seniority' => 'senior']),
        );
        // 1 year maps to "junior" (index 1) against "senior" (index 3): two below.
        $this->assertSame(50.0, $under->component('seniority')?->score);

        $exact = $this->scorer()->handle(
            $this->profile(['years_experience' => 4]),
            $this->job(['seniority' => 'mid']),
        );
        $this->assertSame(100.0, $exact->component('seniority')?->score);
    }

    public function test_location_prefers_city_then_preference_then_region(): void
    {
        $sameCity = $this->scorer()->handle($this->profile(), $this->job(['city' => 'Addis Ababa', 'seniority' => null]));
        $this->assertSame(100.0, $sameCity->component('location')?->score);

        $preferred = $this->profile(['city' => 'Bahir Dar', 'region' => 'Amhara']);
        CandidatePreference::create([
            'candidate_profile_id' => $preferred->id,
            'locations' => ['addis ababa'],
        ]);
        $this->assertSame(
            90.0,
            $this->scorer()->handle($preferred, $this->job(['city' => 'Addis Ababa', 'seniority' => null]))->component('location')?->score,
        );

        $sameRegion = $this->profile(['city' => 'Adama', 'region' => 'Oromia']);
        $this->assertSame(
            80.0,
            $this->scorer()->handle($sameRegion, $this->job(['city' => 'Bishoftu', 'region' => 'Oromia', 'seniority' => null]))->component('location')?->score,
        );

        $relocatable = $this->profile(['city' => 'Mekelle', 'region' => 'Tigray']);
        CandidatePreference::create([
            'candidate_profile_id' => $relocatable->id,
            'willing_to_relocate' => true,
        ]);
        $this->assertSame(
            60.0,
            $this->scorer()->handle($relocatable, $this->job(['city' => 'Addis Ababa', 'seniority' => null]))->component('location')?->score,
        );

        $far = $this->profile(['city' => 'Mekelle', 'region' => 'Tigray']);
        $this->assertSame(
            30.0,
            $this->scorer()->handle($far, $this->job(['city' => 'Addis Ababa', 'seniority' => null]))->component('location')?->score,
        );
    }

    public function test_work_mode_scores_the_stated_preference(): void
    {
        $profile = $this->profile();
        CandidatePreference::create([
            'candidate_profile_id' => $profile->id,
            'work_modes' => ['hybrid'],
            'open_to_remote' => true,
        ]);

        $job = $this->job(['work_mode' => 'onsite', 'seniority' => null]);

        $result = $this->scorer()->handle($profile, $job);
        $this->assertSame(40.0, $result->component('work_mode')?->score);

        $remote = $this->job(['work_mode' => 'remote', 'seniority' => null]);
        $this->assertSame(100.0, $this->scorer()->handle($profile, $remote)->component('work_mode')?->score);

        $unset = $this->profile(['city' => 'Addis Ababa']);
        $this->assertFalse(
            $this->scorer()->handle($unset, $this->job(['seniority' => null]))->component('work_mode')?->isApplied(),
        );
    }

    public function test_preference_component_averages_the_signals_that_exist(): void
    {
        $profile = $this->profile();
        CandidatePreference::create([
            'candidate_profile_id' => $profile->id,
            'employment_types' => ['contract'],
            'salary_min_monthly_eth' => 40000,
        ]);

        // Employment mismatch (40) and salary at half the ask (50): mean 45.
        $job = $this->job(['employment_type' => 'full_time', 'salary_max_monthly' => 20000, 'seniority' => null]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame(45.0, $result->component('preference')?->score);
    }

    public function test_components_that_cannot_be_scored_drop_out_of_the_weight(): void
    {
        $profile = $this->profile(['city' => null, 'region' => null, 'years_experience' => 0]);
        $job = $this->job([
            'city' => null,
            'region' => null,
            'seniority' => null,
            'work_mode' => 'onsite',
        ]);

        $result = $this->scorer()->handle($profile, $job);

        foreach ($result->components as $name => $component) {
            $this->assertFalse($component->isApplied(), "component {$name} should be unscoreable");
        }

        $this->assertSame(0, $result->appliedWeight());
        $this->assertSame(['insufficient_data'], $result->hardRequirementFlags);
        $this->assertSame(0.0, $result->overallScore);
        $this->assertSame(
            (string) config('matching.insufficient_data_recommendation'),
            $result->recommendation,
        );
    }

    public function test_penalties_cannot_push_the_score_below_zero(): void
    {
        $bachelor = $this->level('Bachelor', 5);
        $profile = $this->profile(['city' => 'Mekelle', 'region' => 'Tigray', 'years_experience' => 0]);

        $job = $this->job([
            'city' => 'Addis Ababa',
            'seniority' => 'senior',
            'experience_years_min' => 10,
            'min_education_level_id' => $bachelor->id,
        ]);
        foreach (['SQL', 'Python', 'Airflow', 'dbt', 'Spark', 'Kafka'] as $name) {
            $job->skills()->attach($this->skill($name)->id, ['is_required' => true]);
        }

        $result = $this->scorer()->handle($profile, $job);

        // Six missing required skills would cost 24; the cap limits it to 20.
        $this->assertSame(36.0, $result->penalty);
        $this->assertSame(0.0, $result->overallScore);
        $this->assertContains('missing_required_skills', $result->hardRequirementFlags);
        $this->assertContains('experience_below_min', $result->hardRequirementFlags);
        $this->assertContains('education_below_min', $result->hardRequirementFlags);
        $this->assertSame('Not a fit yet — focus on the missing requirements.', $result->recommendation);
    }

    public function test_weights_come_from_configuration(): void
    {
        $laravel = $this->skill('Laravel');
        $powerBi = $this->skill('Power BI');

        $profile = $this->profile();
        $this->giveSkill($profile, $laravel, 4);

        $job = $this->job();
        $job->skills()->attach($laravel->id, ['is_required' => true]);
        $job->skills()->attach($powerBi->id, ['is_required' => true]);

        config(['matching.weights' => ['skills' => 100]]);

        $result = $this->scorer()->handle($profile, $job);

        // Only skills carries weight now: 50 - 4 penalty.
        $this->assertSame(46.0, $result->overallScore);
    }

    public function test_semantic_component_is_unavailable_without_embeddings(): void
    {
        $result = $this->scorer()->handle($this->profile(), $this->job(['seniority' => null]));

        $semantic = $result->component('semantic');
        $this->assertNotNull($semantic);
        $this->assertNull($semantic->score);
        $this->assertStringContainsString('none', (string) $semantic->detail);
    }

    public function test_a_semantic_provider_contributes_when_bound(): void
    {
        $this->app->instance(SemanticSimilarityProvider::class, new class implements SemanticSimilarityProvider
        {
            public function similarity(CandidateProfile $profile, Job $job): ?float
            {
                return 90.0;
            }

            public function name(): string
            {
                return 'stub';
            }
        });
        config(['matching.weights' => ['semantic' => 20]]);

        // Nothing else is scoreable, so the semantic component decides alone.
        $result = app(ScoreJobMatch::class)->handle(
            $this->profile(['city' => null, 'region' => null, 'years_experience' => 0]),
            $this->job(['city' => null, 'region' => null, 'seniority' => null]),
        );

        $this->assertSame(90.0, $result->overallScore);
        $this->assertSame(90.0, $result->component('semantic')?->score);
        $this->assertSame('Strong candidate. Apply.', $result->recommendation);
    }

    public function test_matched_skills_carry_the_evidence_the_ui_shows(): void
    {
        $laravel = $this->skill('Laravel');
        $profile = $this->profile();
        $this->giveSkill($profile, $laravel, 4);

        $job = $this->job(['seniority' => null]);
        $job->skills()->attach($laravel->id, ['is_required' => true, 'min_years' => 2]);

        $result = $this->scorer()->handle($profile, $job);

        $this->assertSame([
            'id' => $laravel->id,
            'name' => 'Laravel',
            'is_required' => true,
            'level' => 4,
            'years_using' => 4.0,
            'min_years' => 2,
        ], $result->matchedSkills[0]);
    }
}
