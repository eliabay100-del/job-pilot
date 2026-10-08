<?php

declare(strict_types=1);

namespace Tests\Feature\Candidates;

use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateSkill;
use App\Models\EducationLevel;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileEntitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_education_crud_lifecycle(): void
    {
        $level = EducationLevel::create(['name' => 'Bachelor', 'rank' => 6]);

        $created = $this->postJson('/api/v1/profile/education', [
            'institution_name' => 'Addis Ababa University',
            'degree' => 'BSc Software Engineering',
            'education_level_id' => $level->id,
            'field_of_study' => 'Software Engineering',
            'start_year' => 2019,
            'end_year' => 2023,
            'cgpa' => 3.65,
        ])->assertCreated()->assertJsonPath('data.degree', 'BSc Software Engineering');

        $id = $created->json('data.id');

        $this->getJson('/api/v1/profile/education')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/profile/education/{$id}", ['cgpa' => 3.8])
            ->assertOk()->assertJsonPath('data.cgpa', '3.80');

        $this->deleteJson("/api/v1/profile/education/{$id}")->assertOk();
        $this->assertDatabaseCount('candidate_educations', 0);
    }

    public function test_education_requires_degree_or_institution_reference(): void
    {
        $this->postJson('/api/v1/profile/education', ['field_of_study' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['degree', 'institution_name']);
    }

    public function test_experience_crud_and_years_recomputation(): void
    {
        $this->postJson('/api/v1/profile/experiences', [
            'job_title' => 'Junior Developer',
            'company' => 'Example Tech PLC',
            'employment_type' => 'full_time',
            'start_date' => now()->subYears(3)->toDateString(),
            'end_date' => now()->subYear()->toDateString(),
        ])->assertCreated();

        $profile = $this->user->fresh()->candidateProfile;
        $this->assertSame(2, $profile->years_experience);

        $second = $this->postJson('/api/v1/profile/experiences', [
            'job_title' => 'Developer',
            'company' => 'Other PLC',
            'start_date' => now()->subYear()->toDateString(),
            'is_current' => true,
        ])->assertCreated();

        // Contiguous year totals to 3 (merged intervals, no double counting).
        $this->assertSame(3, $this->user->fresh()->candidateProfile->years_experience);

        $this->deleteJson("/api/v1/profile/experiences/{$second->json('data.id')}")->assertOk();
        $this->assertSame(2, $this->user->fresh()->candidateProfile->years_experience);
    }

    public function test_experience_rejects_end_before_start(): void
    {
        $this->postJson('/api/v1/profile/experiences', [
            'job_title' => 'X', 'company' => 'Y',
            'start_date' => '2024-06-01', 'end_date' => '2024-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_skill_attach_requires_existing_skill_and_blocks_duplicates(): void
    {
        $skill = Skill::create(['name' => 'PHP', 'slug' => 'php']);

        $this->postJson('/api/v1/profile/skills', ['skill_id' => $skill->id, 'level' => 4])
            ->assertCreated()->assertJsonPath('data.skill', 'PHP');

        $this->postJson('/api/v1/profile/skills', ['skill_id' => $skill->id, 'level' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('skill_id');

        $this->postJson('/api/v1/profile/skills', ['skill_id' => 99999, 'level' => 3])
            ->assertStatus(422)->assertJsonValidationErrors('skill_id');

        $this->postJson('/api/v1/profile/skills', ['skill_id' => $skill->id, 'level' => 9])
            ->assertStatus(422)->assertJsonValidationErrors('level');
    }

    public function test_project_certification_language_crud(): void
    {
        $project = $this->postJson('/api/v1/profile/projects', [
            'name' => 'Job Board',
            'role' => 'Backend Lead',
            'tech_stack' => ['Laravel', 'PostgreSQL'],
        ])->assertCreated();

        $this->putJson("/api/v1/profile/projects/{$project->json('data.id')}", ['name' => 'Job Board v2'])
            ->assertOk()->assertJsonPath('data.name', 'Job Board v2');

        $cert = $this->postJson('/api/v1/profile/certifications', [
            'name' => 'AWS Cloud Practitioner',
            'issuer' => 'Amazon Web Services',
            'issued_at' => '2025-03-01',
        ])->assertCreated();

        $this->deleteJson("/api/v1/profile/certifications/{$cert->json('data.id')}")->assertOk();

        $lang = $this->postJson('/api/v1/profile/languages', [
            'language' => 'Amharic',
            'proficiency' => 'native',
        ])->assertCreated();

        // Same language twice is blocked by the per-profile uniqueness rule.
        $this->postJson('/api/v1/profile/languages', ['language' => 'Amharic', 'proficiency' => 'basic'])
            ->assertStatus(422)->assertJsonValidationErrors('language');

        $this->putJson("/api/v1/profile/languages/{$lang->json('data.id')}", ['proficiency' => 'professional'])
            ->assertOk()->assertJsonPath('data.proficiency', 'professional');
    }

    public function test_user_cannot_touch_other_users_entities(): void
    {
        $other = User::factory()->create();
        $otherProfile = $other->ensureCandidateProfile();

        $education = CandidateEducation::create([
            'candidate_profile_id' => $otherProfile->id,
            'degree' => 'BSc Physics',
        ]);
        $experience = CandidateExperience::create([
            'candidate_profile_id' => $otherProfile->id,
            'job_title' => 'Teller',
            'company' => 'Bank',
            'start_date' => '2023-01-01',
        ]);
        $skill = CandidateSkill::create([
            'candidate_profile_id' => $otherProfile->id,
            'skill_id' => Skill::create(['name' => 'SQL', 'slug' => 'sql'])->id,
            'level' => 3,
        ]);

        $this->putJson("/api/v1/profile/education/{$education->id}", ['degree' => 'Hacked'])
            ->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->deleteJson("/api/v1/profile/experiences/{$experience->id}")
            ->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->putJson("/api/v1/profile/skills/{$skill->id}", ['level' => 5])->assertStatus(403);
        $this->deleteJson("/api/v1/profile/skills/{$skill->id}")->assertStatus(403);
    }

    public function test_taxonomy_skill_lookup_searches_by_name(): void
    {
        Skill::create(['name' => 'PostgreSQL', 'slug' => 'postgresql']);
        Skill::create(['name' => 'Python', 'slug' => 'python']);

        $this->getJson('/api/v1/taxonomy/skills?q=post')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'PostgreSQL');
    }
}
