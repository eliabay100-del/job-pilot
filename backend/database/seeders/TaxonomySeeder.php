<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EducationLevel;
use App\Models\Industry;
use App\Models\Institution;
use App\Models\JobCategory;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Layer 1 platform data (SPEC section 2): curated Ethiopian job taxonomy.
 * Idempotent — safe to re-run; never deletes admin edits.
 */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Grade 8', 'rank' => 1],
            ['name' => 'Grade 10', 'rank' => 2],
            ['name' => 'Grade 12', 'rank' => 3],
            ['name' => 'Diploma', 'rank' => 4],
            ['name' => 'Advanced Diploma', 'rank' => 5],
            ['name' => 'Bachelor', 'rank' => 6],
            ['name' => 'Postgraduate Diploma', 'rank' => 7],
            ['name' => 'Master', 'rank' => 8],
            ['name' => 'Doctorate', 'rank' => 9],
        ] as $level) {
            EducationLevel::updateOrCreate(['name' => $level['name']], $level);
        }

        $skills = [
            'PHP' => ['laravel', 'php8'],
            'JavaScript' => ['js', 'ecmascript'],
            'TypeScript' => ['ts'],
            'React' => ['reactjs', 'react.js'],
            'Next.js' => ['nextjs'],
            'Node.js' => ['nodejs', 'node'],
            'Laravel' => [],
            'Python' => ['python3'],
            'SQL' => [],
            'PostgreSQL' => ['postgres'],
            'Docker' => [],
            'REST APIs' => ['rest', 'restful api'],
            'Git' => ['github', 'version control'],
            'HTML' => ['html5'],
            'CSS' => ['css3'],
            'Tailwind CSS' => ['tailwind', 'tailwindcss'],
            'Flutter' => ['dart'],
            'Java' => [],
            'C++' => ['cpp'],
            'Machine Learning' => ['ml'],
            'Data Analysis' => ['analytics'],
            'Accounting' => [],
            'Project Management' => ['pm'],
            'Communication' => [],
            'Amharic Writing' => [],
            'Customer Service' => [],
            'Sales' => [],
            'Marketing' => ['digital marketing'],
            'Graphic Design' => [],
            'Civil Engineering' => [],
        ];

        foreach ($skills as $name => $aliases) {
            $skill = Skill::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );

            foreach ($aliases as $alias) {
                SkillAlias::updateOrCreate(['alias' => $alias], ['skill_id' => $skill->id]);
            }
        }

        foreach ([
            'Software & IT', 'Banking & Finance', 'Telecommunications', 'Healthcare',
            'Education', 'Construction & Real Estate', 'Manufacturing', 'Agriculture',
            'Logistics & Transport', 'Retail & E-commerce', 'NGO & Development',
            'Government & Public Sector', 'Hospitality & Tourism', 'Media & Entertainment',
            'Energy & Utilities', 'Consulting',
        ] as $industry) {
            Industry::updateOrCreate(['slug' => Str::slug($industry)], ['name' => $industry, 'is_active' => true]);
        }

        $categories = [
            'Software & Data' => [
                'Software Engineer', 'Frontend Developer', 'Backend Developer', 'Full Stack Developer',
                'Mobile Developer', 'DevOps Engineer', 'Data Analyst', 'Data Engineer',
                'Machine Learning Engineer', 'QA Engineer', 'Database Administrator', 'IT Support Specialist',
            ],
            'Business & Finance' => [
                'Accountant', 'Auditor', 'Financial Analyst', 'Bank Officer', 'Loan Officer',
                'Procurement Officer', 'Supply Chain Manager', 'Business Development Manager',
            ],
            'Health' => [
                'General Practitioner', 'Nurse', 'Pharmacist', 'Lab Technician', 'Public Health Officer',
            ],
            'Education' => ['Lecturer', 'Teacher', 'Curriculum Developer', 'Academic Advisor'],
            'Engineering' => [
                'Civil Engineer', 'Electrical Engineer', 'Mechanical Engineer', 'Site Supervisor',
                'CAD Technician',
            ],
            'Sales & Marketing' => [
                'Sales Representative', 'Marketing Manager', 'Social Media Manager',
                'Content Writer', 'Graphic Designer', 'Brand Manager',
            ],
            'Administration & HR' => [
                'Human Resources Officer', 'Recruiter', 'Administrative Assistant',
                'Office Manager', 'Executive Secretary',
            ],
            'Customer Service' => ['Call Center Agent', 'Customer Service Representative', 'Receptionist'],
        ];

        foreach ($categories as $category => $roles) {
            $jobCategory = JobCategory::updateOrCreate(
                ['slug' => Str::slug($category)],
                ['name' => $category, 'is_active' => true],
            );

            foreach ($roles as $role) {
                JobRole::updateOrCreate(
                    ['slug' => Str::slug($role)],
                    ['name' => $role, 'job_category_id' => $jobCategory->id, 'is_active' => true],
                );
            }
        }

        foreach ([
            ['name' => 'Addis Ababa University', 'type' => 'university', 'city' => 'Addis Ababa'],
            ['name' => 'Bahir Dar University', 'type' => 'university', 'city' => 'Bahir Dar'],
            ['name' => 'Mekelle University', 'type' => 'university', 'city' => 'Mekelle'],
            ['name' => 'University of Gondar', 'type' => 'university', 'city' => 'Gondar'],
            ['name' => 'Hawassa University', 'type' => 'university', 'city' => 'Hawassa'],
            ['name' => 'Jimma University', 'type' => 'university', 'city' => 'Jimma'],
            ['name' => 'Adama Science and Technology University', 'type' => 'university', 'city' => 'Adama'],
            ['name' => 'Wolaita Sodo University', 'type' => 'university', 'city' => 'Wolaita Sodo'],
            ['name' => 'Dire Dawa University', 'type' => 'university', 'city' => 'Dire Dawa'],
            ['name' => 'Ethiopian Civil Service University', 'type' => 'university', 'city' => 'Addis Ababa'],
            ['name' => 'Unity University', 'type' => 'college', 'city' => 'Addis Ababa'],
            ['name' => "St. Mary's University", 'type' => 'university', 'city' => 'Addis Ababa'],
            ['name' => 'Addis Ababa Institute of Technology', 'type' => 'college', 'city' => 'Addis Ababa'],
            ['name' => 'Lideta Manufacturing College', 'type' => 'training_center', 'city' => 'Addis Ababa'],
        ] as $institution) {
            Institution::updateOrCreate(
                ['name' => $institution['name'], 'type' => $institution['type']],
                $institution + ['country' => 'Ethiopia'],
            );
        }
    }
}
