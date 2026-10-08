<?php

declare(strict_types=1);

namespace Tests\Feature\Candidates;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        auth()->forgetGuards();

        $this->getJson('/api/v1/profile')->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_unauthenticated_api_request_without_accept_header_still_returns_json(): void
    {
        auth()->forgetGuards();

        // Plain get(), so no "Accept: application/json". The framework's default
        // redirectGuestsTo(route('login')) used to blow up here with a 500.
        $this->get('/api/v1/profile')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_show_returns_profile_and_creates_one_if_missing(): void
    {
        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonStructure(['data' => [
                'id', 'display_name', 'headline', 'city', 'years_experience',
                'remote_preference', 'is_public', 'preference',
            ]]);

        $this->assertDatabaseHas('candidate_profiles', ['user_id' => $this->user->id]);
    }

    public function test_update_persists_validated_fields(): void
    {
        $this->putJson('/api/v1/profile', [
            'display_name' => 'Abebe Kebede',
            'headline' => 'Backend Developer',
            'city' => 'Addis Ababa',
            'region' => 'Addis Ababa',
            'remote_preference' => 'hybrid',
            'employment_type_preference' => 'full_time',
            'expected_salary_monthly_etb' => 50000,
            'is_public' => true,
        ])->assertOk()
            ->assertJsonPath('data.display_name', 'Abebe Kebede')
            ->assertJsonPath('data.remote_preference', 'hybrid')
            ->assertJsonPath('data.is_public', true);

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $this->user->id,
            'headline' => 'Backend Developer',
        ]);
    }

    public function test_update_rejects_invalid_enum_and_overlong_values(): void
    {
        $this->putJson('/api/v1/profile', [
            'remote_preference' => 'teleport',
            'headline' => str_repeat('x', 161),
        ])->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['remote_preference', 'headline']);
    }

    public function test_preferences_update_creates_then_updates(): void
    {
        $this->putJson('/api/v1/profile/preferences', [
            'work_modes' => ['hybrid', 'remote'],
            'locations' => ['Addis Ababa'],
            'employment_types' => ['full_time'],
            'salary_min_monthly_eth' => 40000,
            'open_to_remote' => true,
        ])->assertOk()
            ->assertJsonPath('data.work_modes', ['hybrid', 'remote'])
            ->assertJsonPath('data.open_to_remote', true);

        $this->putJson('/api/v1/profile/preferences', [
            'salary_min_monthly_eth' => 60000,
        ])->assertOk()
            ->assertJsonPath('data.salary_min_monthly_eth', 60000)
            ->assertJsonPath('data.work_modes', ['hybrid', 'remote']);

        $this->assertDatabaseCount('candidate_preferences', 1);
    }

    public function test_preferences_reject_unknown_work_mode(): void
    {
        $this->putJson('/api/v1/profile/preferences', [
            'work_modes' => ['underwater'],
        ])->assertStatus(422)->assertJsonValidationErrors('work_modes.0');
    }

    public function test_user_cannot_read_another_users_profile(): void
    {
        $other = User::factory()->create();
        $otherProfile = $other->ensureCandidateProfile();

        $this->getJson("/api/v1/profile/{$otherProfile->id}/photo")->assertStatus(403);
    }
}
