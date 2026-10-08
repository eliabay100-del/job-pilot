<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Candidates\Actions\RecomputeYearsExperience;
use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo job seeker for local development and manual frontend testing.
 * Login: demo@jobpilot.test / Password!123
 */
class DemoCandidateSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@jobpilot.test'],
            [
                'name' => 'Demo Candidate',
                'password' => 'Password!123',
                'role' => UserRole::JobSeeker->value,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $profile = $user->ensureCandidateProfile();

        $profile->update([
            'display_name' => 'Demo Candidate',
            'headline' => 'Full Stack Developer | Laravel + React',
            'summary' => 'Software developer focused on web platforms, APIs and clean architecture.',
            'city' => 'Addis Ababa',
            'region' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'current_job_title' => 'Junior Software Developer',
            'current_company' => 'Example Tech PLC',
            'preferred_job_title' => 'Full Stack Developer',
            'preferred_location' => 'Addis Ababa',
            'remote_preference' => 'hybrid',
            'employment_type_preference' => 'full_time',
        ]);

        $aau = Institution::where('name', 'Addis Ababa University')->first();

        $profile->educations()->firstOrCreate(
            ['degree' => 'BSc Software Engineering'],
            [
                'institution_id' => $aau?->id,
                'institution_name' => $aau?->name ?? 'Addis Ababa University',
                'field_of_study' => 'Software Engineering',
                'start_year' => 2019,
                'end_year' => 2023,
                'cgpa' => 3.65,
            ],
        );

        $profile->experiences()->firstOrCreate(
            ['job_title' => 'Junior Software Developer', 'company' => 'Example Tech PLC'],
            [
                'employment_type' => 'full_time',
                'location' => 'Addis Ababa',
                'start_date' => '2023-09-01',
                'is_current' => true,
                'description' => 'Building and maintaining Laravel APIs and React frontends.',
            ],
        );

        foreach (['PHP', 'Laravel', 'JavaScript', 'React', 'PostgreSQL', 'Git'] as $skillName) {
            $skill = Skill::where('name', $skillName)->first();
            if ($skill !== null) {
                $profile->skills()->firstOrCreate(
                    ['skill_id' => $skill->id],
                    ['level' => 4, 'source' => 'manual', 'confidence' => 1.00],
                );
            }
        }

        $profile->languages()->firstOrCreate(['language' => 'Amharic'], ['proficiency' => 'native']);
        $profile->languages()->firstOrCreate(['language' => 'English'], ['proficiency' => 'professional']);

        $profile->preference()->updateOrCreate(
            ['candidate_profile_id' => $profile->id],
            [
                'work_modes' => ['hybrid', 'remote'],
                'locations' => ['Addis Ababa'],
                'employment_types' => ['full_time'],
                'open_to_remote' => true,
            ],
        );

        app(RecomputeYearsExperience::class)->handle($profile);
    }
}
