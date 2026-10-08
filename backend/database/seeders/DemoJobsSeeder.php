<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\EducationLevel;
use App\Models\Industry;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobRole;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo employers and published vacancies so search, filters and the jobs UI
 * have realistic Ethiopian data locally. Idempotent: keyed on company slug and
 * job slug, so re-running never duplicates listings.
 */
class DemoJobsSeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            'Gebeya Inc.' => [
                'industry' => 'Software & IT',
                'city' => 'Addis Ababa',
                'size' => '51-200',
                'type' => 'startup',
                'website' => 'https://gebeya.com',
                'description' => 'Talent marketplace and software company building digital products for African markets.',
            ],
            'Ethio Telecom' => [
                'industry' => 'Telecommunications',
                'city' => 'Addis Ababa',
                'size' => '1000+',
                'type' => 'quasi_government',
                'website' => 'https://www.ethiotelecom.et',
                'description' => 'National telecommunications operator providing mobile, fixed and internet services.',
            ],
            'Commercial Bank of Ethiopia' => [
                'industry' => 'Banking & Finance',
                'city' => 'Addis Ababa',
                'size' => '1000+',
                'type' => 'quasi_government',
                'website' => 'https://cbebirr.com',
                'description' => 'The largest commercial bank in Ethiopia with a nationwide branch network.',
            ],
            'Ethiopian Airlines' => [
                'industry' => 'Logistics & Transport',
                'city' => 'Addis Ababa',
                'size' => '1000+',
                'type' => 'quasi_government',
                'website' => 'https://www.ethiopianairlines.com',
                'description' => 'Flag carrier of Ethiopia and the largest airline network in Africa.',
            ],
        ];

        foreach ($companies as $name => $attributes) {
            Company::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'industry_id' => Industry::where('name', $attributes['industry'])->value('id'),
                    'city' => $attributes['city'],
                    'region' => 'Addis Ababa',
                    'size' => $attributes['size'],
                    'type' => $attributes['type'],
                    'website' => $attributes['website'],
                    'description' => $attributes['description'],
                    'verification_status' => 'verified',
                    'verified_at' => now()->subYear(),
                ],
            );
        }

        foreach ($this->jobs() as $job) {
            $company = Company::where('slug', Str::slug($job['company']))->firstOrFail();

            $model = Job::updateOrCreate(
                ['slug' => $job['slug']],
                [
                    'company_id' => $company->id,
                    'title' => $job['title'],
                    'description' => $job['description'],
                    'requirements' => $job['requirements'] ?? null,
                    'city' => $job['city'],
                    'region' => $job['region'] ?? $job['city'],
                    'work_mode' => $job['work_mode'],
                    'employment_type' => $job['employment_type'],
                    'seniority' => $job['seniority'],
                    'job_category_id' => JobCategory::where('name', $job['category'])->value('id'),
                    'job_role_id' => JobRole::where('name', $job['role'])->value('id'),
                    'industry_id' => $company->industry_id,
                    'min_education_level_id' => EducationLevel::where('name', $job['education'])->value('id'),
                    'experience_years_min' => $job['exp_min'],
                    'experience_years_max' => $job['exp_max'],
                    'salary_min_monthly' => $job['salary_min'],
                    'salary_max_monthly' => $job['salary_max'],
                    'application_deadline' => now()->addDays($job['deadline_in_days'])->toDateString(),
                    'status' => $job['status'],
                    'published_at' => $job['status'] === 'published'
                        ? now()->subDays($job['published_days_ago'])
                        : null,
                    'expires_at' => isset($job['expired_days_ago'])
                        ? now()->subDays($job['expired_days_ago'])
                        : null,
                    'first_seen_at' => now()->subDays($job['published_days_ago']),
                    'last_checked_at' => now(),
                    'content_fingerprint' => hash('sha256', $job['slug']),
                    'risk_level' => 'low',
                    'risk_score' => 0,
                ],
            );

            $skillIds = Skill::whereIn('name', $job['skills'])->pluck('id');
            $model->skills()->sync($skillIds->mapWithKeys(fn ($id) => [$id => ['is_required' => true]]));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jobs(): array
    {
        return [
            [
                'slug' => 'senior-backend-developer-gebeya',
                'company' => 'Gebeya Inc.',
                'title' => 'Senior Backend Developer',
                'category' => 'Software & Data',
                'role' => 'Backend Developer',
                'skills' => ['PHP', 'Laravel', 'PostgreSQL'],
                'city' => 'Addis Ababa',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'seniority' => 'senior',
                'education' => 'Bachelor',
                'exp_min' => 4,
                'exp_max' => 7,
                'salary_min' => 60000,
                'salary_max' => 90000,
                'deadline_in_days' => 21,
                'published_days_ago' => 3,
                'status' => 'published',
                'description' => 'Design and build Laravel services powering our talent marketplace: REST APIs, queue workers and PostgreSQL schema design, with code review and mentoring of mid-level engineers.',
                'requirements' => '4+ years of PHP/Laravel production experience, strong SQL, and experience designing REST APIs consumed by web and mobile clients.',
            ],
            [
                'slug' => 'full-stack-developer-gebeya',
                'company' => 'Gebeya Inc.',
                'title' => 'Full Stack Developer',
                'category' => 'Software & Data',
                'role' => 'Full Stack Developer',
                'skills' => ['JavaScript', 'TypeScript', 'React', 'Laravel'],
                'city' => 'Addis Ababa',
                'work_mode' => 'remote',
                'employment_type' => 'full_time',
                'seniority' => 'mid',
                'education' => 'Bachelor',
                'exp_min' => 2,
                'exp_max' => 5,
                'salary_min' => 45000,
                'salary_max' => 70000,
                'deadline_in_days' => 30,
                'published_days_ago' => 6,
                'status' => 'published',
                'description' => 'Ship features end to end across a React/Next.js frontend and Laravel API for client projects in finance and agriculture.',
            ],
            [
                'slug' => 'frontend-developer-gebeya',
                'company' => 'Gebeya Inc.',
                'title' => 'Frontend Developer',
                'category' => 'Software & Data',
                'role' => 'Frontend Developer',
                'skills' => ['React', 'Next.js', 'TypeScript'],
                'city' => 'Addis Ababa',
                'work_mode' => 'hybrid',
                'employment_type' => 'contract',
                'seniority' => 'mid',
                'education' => 'Bachelor',
                'exp_min' => 2,
                'exp_max' => 4,
                'salary_min' => 50000,
                'salary_max' => 75000,
                'deadline_in_days' => 40,
                'published_days_ago' => 12,
                'status' => 'published',
                'description' => 'Build accessible, low-bandwidth-friendly interfaces with React and Next.js for public-sector digital services.',
            ],
            [
                'slug' => 'data-analyst-cbe',
                'company' => 'Commercial Bank of Ethiopia',
                'title' => 'Data Analyst',
                'category' => 'Software & Data',
                'role' => 'Data Analyst',
                'skills' => ['SQL', 'PostgreSQL'],
                'city' => 'Addis Ababa',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'seniority' => 'junior',
                'education' => 'Bachelor',
                'exp_min' => 1,
                'exp_max' => 3,
                'salary_min' => 25000,
                'salary_max' => 40000,
                'deadline_in_days' => 14,
                'published_days_ago' => 1,
                'status' => 'published',
                'description' => 'Produce branch performance and portfolio reports for retail banking, and maintain the reporting SQL layer.',
            ],
            [
                'slug' => 'bank-officer-bahir-dar-cbe',
                'company' => 'Commercial Bank of Ethiopia',
                'title' => 'Customer Service Officer',
                'category' => 'Business & Finance',
                'role' => 'Bank Officer',
                'skills' => [],
                'city' => 'Bahir Dar',
                'region' => 'Amhara',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'seniority' => 'entry',
                'education' => 'Diploma',
                'exp_min' => 0,
                'exp_max' => 2,
                'salary_min' => 15000,
                'salary_max' => 22000,
                'deadline_in_days' => 10,
                'published_days_ago' => 2,
                'status' => 'published',
                'description' => 'Serve walk-in customers at the Bahir Dar district: account opening, transfers and cash handling under bank procedure.',
            ],
            [
                'slug' => 'database-administrator-ethio-telecom',
                'company' => 'Ethio Telecom',
                'title' => 'Database Administrator',
                'category' => 'Software & Data',
                'role' => 'Database Administrator',
                'skills' => ['SQL', 'PostgreSQL'],
                'city' => 'Addis Ababa',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'seniority' => 'mid',
                'education' => 'Bachelor',
                'exp_min' => 3,
                'exp_max' => 6,
                'salary_min' => 40000,
                'salary_max' => 60000,
                'deadline_in_days' => 45,
                'published_days_ago' => 10,
                'status' => 'published',
                'description' => 'Operate billing and subscriber databases: backups, replication, capacity planning and query tuning.',
            ],
            [
                'slug' => 'devops-engineer-ethio-telecom',
                'company' => 'Ethio Telecom',
                'title' => 'DevOps Engineer',
                'category' => 'Software & Data',
                'role' => 'DevOps Engineer',
                'skills' => ['Docker', 'SQL'],
                'city' => 'Addis Ababa',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'seniority' => 'senior',
                'education' => 'Bachelor',
                'exp_min' => 5,
                'exp_max' => 8,
                'salary_min' => 70000,
                'salary_max' => 110000,
                'deadline_in_days' => 28,
                'published_days_ago' => 8,
                'status' => 'published',
                'description' => 'Automate deployment of internal service platforms and harden the container runtime used by digital services teams.',
            ],
            [
                'slug' => 'mechanical-engineer-ethiopian-airlines',
                'company' => 'Ethiopian Airlines',
                'title' => 'Mechanical Engineer',
                'category' => 'Engineering',
                'role' => 'Mechanical Engineer',
                'skills' => [],
                'city' => 'Bishoftu',
                'region' => 'Oromia',
                'work_mode' => 'onsite',
                'employment_type' => 'full_time',
                'seniority' => 'mid',
                'education' => 'Bachelor',
                'exp_min' => 3,
                'exp_max' => 6,
                'salary_min' => 35000,
                'salary_max' => 55000,
                'deadline_in_days' => 25,
                'published_days_ago' => 5,
                'status' => 'published',
                'description' => 'Support heavy maintenance checks at the Bishoftu MRO facility, including work package preparation and component tracking.',
            ],
            [
                'slug' => 'data-engineer-ethiopian-airlines',
                'company' => 'Ethiopian Airlines',
                'title' => 'Data Engineer',
                'category' => 'Software & Data',
                'role' => 'Data Engineer',
                'skills' => ['Python', 'SQL'],
                'city' => 'Addis Ababa',
                'work_mode' => 'hybrid',
                'employment_type' => 'full_time',
                'seniority' => 'senior',
                'education' => 'Master',
                'exp_min' => 4,
                'exp_max' => 7,
                'salary_min' => 65000,
                'salary_max' => 95000,
                'deadline_in_days' => 35,
                'published_days_ago' => 4,
                'status' => 'published',
                'description' => 'Build the passenger and operations data pipelines feeding revenue management and crew planning systems.',
            ],
            [
                'slug' => 'qa-engineer-gebeya-expired',
                'company' => 'Gebeya Inc.',
                'title' => 'QA Engineer',
                'category' => 'Software & Data',
                'role' => 'QA Engineer',
                'skills' => ['SQL'],
                'city' => 'Addis Ababa',
                'work_mode' => 'remote',
                'employment_type' => 'full_time',
                'seniority' => 'mid',
                'education' => 'Bachelor',
                'exp_min' => 2,
                'exp_max' => 5,
                'salary_min' => 30000,
                'salary_max' => 50000,
                'deadline_in_days' => -5,
                'expired_days_ago' => 5,
                'published_days_ago' => 60,
                'status' => 'published',
                'description' => 'Expired listing kept to prove search excludes lapsed vacancies.',
            ],
            [
                'slug' => 'intern-software-engineer-gebeya',
                'company' => 'Gebeya Inc.',
                'title' => 'Intern Software Engineer',
                'category' => 'Software & Data',
                'role' => 'Software Engineer',
                'skills' => ['JavaScript'],
                'city' => 'Addis Ababa',
                'work_mode' => 'onsite',
                'employment_type' => 'internship',
                'seniority' => 'entry',
                'education' => 'Bachelor',
                'exp_min' => 0,
                'exp_max' => 1,
                'salary_min' => 8000,
                'salary_max' => 12000,
                'deadline_in_days' => 20,
                'published_days_ago' => 1,
                'status' => 'pending_review',
                'description' => 'Held in moderation to prove unpublished jobs stay out of search.',
            ],
        ];
    }
}
