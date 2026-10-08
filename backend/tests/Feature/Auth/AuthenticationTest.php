<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_job_seeker_with_candidate_profile(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Abebe Kebede',
            'email' => 'abebe@example.com',
            'password' => 'Passw0rd!long',
            'role' => 'job_seeker',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'job_seeker')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'status']]);

        $this->assertDatabaseHas('users', ['email' => 'abebe@example.com']);
        $this->assertDatabaseCount('candidate_profiles', 1);
        Notification::assertSentTo(
            User::where('email', 'abebe@example.com')->firstOrFail(),
            \Illuminate\Auth\Notifications\VerifyEmail::class,
        );
    }

    public function test_register_rejects_admin_role_self_assignment(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Evil Admin',
            'email' => 'evil@example.com',
            'password' => 'Passw0rd!long',
            'role' => 'super_admin',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Dup',
            'email' => 'dup@example.com',
            'password' => 'Passw0rd!long',
        ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_login_returns_token_and_me_works(): void
    {
        $user = User::factory()->create([
            'role' => 'job_seeker',
            'password' => 'Passw0rd!long',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Passw0rd!long',
        ]);
        $login->assertOk()->assertJsonStructure(['success', 'data' => ['token', 'user']]);

        $token = $login->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_fails_with_bad_password(): void
    {
        $user = User::factory()->create(['password' => 'Passw0rd!long']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => 'Passw0rd!long',
            'status' => 'suspended',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Passw0rd!long',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create(['password' => 'Passw0rd!long']);
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Passw0rd!long',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        // The test client keeps the resolved user in guards between requests;
        // forget them so /me must re-authenticate against the (now deleted) token.
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_error_envelope_contains_request_id(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertJsonStructure(['request_id']);
    }
}
