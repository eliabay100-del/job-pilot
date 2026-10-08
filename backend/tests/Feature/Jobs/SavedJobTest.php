<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $company = Company::create([
            'name' => 'Gebeya Inc.',
            'slug' => 'gebeya-inc',
            'verification_status' => 'verified',
        ]);

        $this->job = Job::create([
            'company_id' => $company->id,
            'title' => 'Backend Developer',
            'slug' => 'backend-developer',
            'description' => 'Laravel services.',
            'city' => 'Addis Ababa',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_save_is_idempotent_and_lists_for_owner_only(): void
    {
        $this->postJson("/api/v1/jobs/{$this->job->id}/save")
            ->assertCreated()
            ->assertJsonPath('data.is_saved', true);

        $this->postJson("/api/v1/jobs/{$this->job->id}/save")
            ->assertStatus(200)
            ->assertJsonPath('data.is_saved', true);

        $this->assertDatabaseCount('saved_jobs', 1);

        $this->getJson('/api/v1/saved-jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->job->id);

        $this->getJson("/api/v1/jobs/{$this->job->id}")->assertJsonPath('data.is_saved', true);

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/saved-jobs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unsave_removes_the_bookmark(): void
    {
        $this->postJson("/api/v1/jobs/{$this->job->id}/save")->assertCreated();
        $this->deleteJson("/api/v1/jobs/{$this->job->id}/save")->assertOk()->assertJsonPath('data.is_saved', false);
        $this->assertDatabaseCount('saved_jobs', 0);
    }

    public function test_unpublished_job_cannot_be_saved(): void
    {
        $this->job->update(['status' => 'pending_review', 'published_at' => null]);

        $this->postJson("/api/v1/jobs/{$this->job->id}/save")
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_saved_endpoints_require_authentication(): void
    {
        auth()->forgetGuards();

        $this->getJson('/api/v1/saved-jobs')->assertStatus(401);
        $this->postJson("/api/v1/jobs/{$this->job->id}/save")->assertStatus(401);
    }

    public function test_expired_job_disappears_from_saved_list(): void
    {
        $this->postJson("/api/v1/jobs/{$this->job->id}/save")->assertCreated();
        $this->job->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/v1/saved-jobs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_company_slug_is_the_route_key(): void
    {
        $this->assertSame('slug', (new Company)->getRouteKeyName());
        $this->getJson('/api/v1/companies/'.Str::random(8))->assertStatus(404);
    }
}
