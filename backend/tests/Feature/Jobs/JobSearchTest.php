<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\Company;
use App\Models\EducationLevel;
use App\Models\Industry;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function makeCompany(string $name = 'Gebeya Inc.'): Company
    {
        return Company::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'city' => 'Addis Ababa',
            'verification_status' => 'verified',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeJob(Company $company, array $overrides = []): Job
    {
        static $sequence = 0;
        $sequence++;

        return Job::create(array_merge([
            'company_id' => $company->id,
            'title' => "Software Engineer {$sequence}",
            'slug' => "software-engineer-{$sequence}",
            'description' => 'Build and operate web platforms with Laravel and PostgreSQL.',
            'city' => 'Addis Ababa',
            'work_mode' => 'onsite',
            'employment_type' => 'full_time',
            'seniority' => 'mid',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_search_requires_authentication(): void
    {
        auth()->forgetGuards();

        $this->getJson('/api/v1/jobs')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_search_lists_only_published_and_unexpired_jobs(): void
    {
        $company = $this->makeCompany();
        $published = $this->makeJob($company);
        $this->makeJob($company, ['status' => 'draft', 'title' => 'Draft role', 'slug' => 'draft-role']);
        $this->makeJob($company, ['status' => 'pending_review', 'title' => 'Held role', 'slug' => 'held-role']);
        $this->makeJob($company, [
            'title' => 'Lapsed role',
            'slug' => 'lapsed-role',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/jobs')->assertOk();

        $this->assertSame([$published->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_keyword_search_uses_full_text_index(): void
    {
        $company = $this->makeCompany();
        $match = $this->makeJob($company, [
            'title' => 'Senior Laravel Backend Developer',
            'slug' => 'laravel-backend',
            'description' => 'Own our payment integrations.',
        ]);
        $this->makeJob($company, [
            'title' => 'Office Administrator',
            'slug' => 'office-admin',
            'description' => 'Manage supplies and filing.',
        ]);

        $response = $this->getJson('/api/v1/jobs?q=laravel+backend')->assertOk();

        $this->assertSame([$match->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_filters_combine(): void
    {
        $company = $this->makeCompany();
        $laravel = Skill::firstOrCreate(['slug' => 'laravel'], ['name' => 'Laravel']);
        Skill::firstOrCreate(['slug' => 'docker'], ['name' => 'Docker']);

        $match = $this->makeJob($company, [
            'title' => 'Remote Laravel Engineer',
            'slug' => 'remote-laravel',
            'work_mode' => 'remote',
            'employment_type' => 'contract',
            'seniority' => 'senior',
            'salary_min_monthly' => 50000,
            'salary_max_monthly' => 80000,
            'experience_years_min' => 3,
            'experience_years_max' => 6,
            'city' => 'Bahir Dar',
            'region' => 'Amhara',
        ]);
        $match->skills()->attach($laravel->id, ['is_required' => true]);

        $this->makeJob($company, ['title' => 'Onsite Junior', 'slug' => 'onsite-junior', 'seniority' => 'junior']);

        $this->getJson('/api/v1/jobs?work_modes[]=remote&employment_types[]=contract&seniorities[]=senior&salary_min=60000&experience_years=4&location=bahir&skill_ids[]='.$laravel->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_education_filter_respects_level_rank(): void
    {
        $company = $this->makeCompany();
        $bachelor = EducationLevel::where('name', 'Bachelor')->firstOrFail();
        $master = EducationLevel::where('name', 'Master')->firstOrFail();

        $bachelorJob = $this->makeJob($company, [
            'title' => 'Graduate Trainee',
            'slug' => 'graduate-trainee',
            'min_education_level_id' => $bachelor->id,
        ]);
        $this->makeJob($company, [
            'title' => 'Research Lead',
            'slug' => 'research-lead',
            'min_education_level_id' => $master->id,
        ]);

        // A candidate holding a Bachelor must not be shown Master-only roles.
        $response = $this->getJson("/api/v1/jobs?education_level_id={$bachelor->id}")->assertOk();

        $this->assertSame([$bachelorJob->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_salary_filter_includes_negotiable_listings(): void
    {
        $company = $this->makeCompany();
        $negotiable = $this->makeJob($company, [
            'title' => 'Negotiable Salary Role',
            'slug' => 'negotiable-role',
            'salary_min_monthly' => null,
            'salary_max_monthly' => null,
            'salary_negotiable' => true,
        ]);
        $this->makeJob($company, [
            'title' => 'Low Paid Role',
            'slug' => 'low-paid-role',
            'salary_min_monthly' => 10000,
            'salary_max_monthly' => 15000,
        ]);

        $response = $this->getJson('/api/v1/jobs?salary_min=40000')->assertOk();

        $this->assertSame([$negotiable->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_company_and_industry_filters(): void
    {
        $gebeya = $this->makeCompany('Gebeya Inc.');
        $bank = $this->makeCompany('Commercial Bank of Ethiopia');
        $bank->update(['industry_id' => Industry::first()?->id]);

        $bankJob = $this->makeJob($bank, ['title' => 'Bank Officer', 'slug' => 'bank-officer']);
        $this->makeJob($gebeya, ['title' => 'Developer', 'slug' => 'developer']);

        $this->getJson('/api/v1/jobs?company=commercial+bank')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bankJob->id);

        if ($bank->industry_id !== null) {
            $this->getJson("/api/v1/jobs?industry_id={$bank->industry_id}")
                ->assertOk()
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_sorting_and_pagination(): void
    {
        $company = $this->makeCompany();
        $old = $this->makeJob($company, ['title' => 'Older Post', 'slug' => 'older-post', 'published_at' => now()->subDays(9), 'salary_max_monthly' => 90000]);
        $new = $this->makeJob($company, ['title' => 'Newer Post', 'slug' => 'newer-post', 'published_at' => now()->subDay(), 'salary_max_monthly' => 30000]);

        $newest = $this->getJson('/api/v1/jobs?sort=newest&per_page=1')->assertOk();
        $this->assertSame($new->id, $newest->json('data.0.id'));
        $this->assertSame(2, $newest->json('meta.total'));
        $this->assertSame(2, $newest->json('meta.last_page'));

        $pageTwo = $this->getJson('/api/v1/jobs?sort=newest&per_page=1&page=2')->assertOk();
        $this->assertSame($old->id, $pageTwo->json('data.0.id'));

        $salary = $this->getJson('/api/v1/jobs?sort=salary_desc')->assertOk();
        $this->assertSame([$old->id, $new->id], collect($salary->json('data'))->pluck('id')->all());
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->getJson('/api/v1/jobs?work_modes[]=wfh&sort=cheapest')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['work_modes.0', 'sort']);
    }

    public function test_show_returns_job_and_counts_a_view(): void
    {
        $company = $this->makeCompany();
        $job = $this->makeJob($company, ['title' => 'Detail Page Role', 'slug' => 'detail-role']);

        $this->getJson("/api/v1/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Detail Page Role')
            ->assertJsonPath('data.is_saved', false)
            ->assertJsonPath('data.company.name', $company->name);

        $this->assertSame(1, $job->fresh()->views_count);
    }

    public function test_unpublished_job_is_hidden_from_other_users_but_visible_to_author(): void
    {
        $company = $this->makeCompany();
        $draft = $this->makeJob($company, [
            'status' => 'pending_review',
            'published_at' => null,
            'title' => 'Unlisted Role',
            'slug' => 'unlisted-role',
            'created_by' => $this->user->id,
        ]);

        $this->getJson("/api/v1/jobs/{$draft->id}")->assertOk();

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/jobs/{$draft->id}")->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_non_numeric_job_id_is_not_found(): void
    {
        $this->getJson('/api/v1/jobs/undefined')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }
}
