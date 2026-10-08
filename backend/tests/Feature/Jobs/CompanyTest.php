<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_profile_with_published_job_count(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $company = Company::create([
            'name' => 'Ethio Telecom',
            'slug' => 'ethio-telecom',
            'city' => 'Addis Ababa',
            'size' => '1000+',
            'verification_status' => 'verified',
            'social_links' => ['linkedin' => 'https://linkedin.com/company/ethio-telecom'],
        ]);

        Job::create([
            'company_id' => $company->id,
            'title' => 'Network Engineer',
            'slug' => 'network-engineer',
            'city' => 'Addis Ababa',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Job::create([
            'company_id' => $company->id,
            'title' => 'Unlisted Intern',
            'slug' => 'unlisted-intern',
            'city' => 'Addis Ababa',
            'status' => 'draft',
        ]);

        $this->getJson('/api/v1/companies/ethio-telecom')
            ->assertOk()
            ->assertJsonPath('data.name', 'Ethio Telecom')
            ->assertJsonPath('data.jobs_count', 1)
            ->assertJsonPath('data.social_links.linkedin', 'https://linkedin.com/company/ethio-telecom');
    }

    public function test_show_requires_authentication(): void
    {
        auth()->forgetGuards();

        $this->getJson('/api/v1/companies/ethio-telecom')->assertStatus(401);
    }
}
