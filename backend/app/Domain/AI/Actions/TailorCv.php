<?php

declare(strict_types=1);

namespace App\Domain\AI\Actions;

use App\Models\CandidateProfile;
use App\Models\CvVersion;
use App\Models\Job;

final class TailorCv
{
    public function handle(CvVersion $source, Job $job): CvVersion
    {
        /** @var CandidateProfile $profile */
        $profile = $source->candidateProfile()->with([
            'experiences',
            'projects',
            'skills.skill',
        ])->firstOrFail();

        $jobSkillNames = $job->skills()->pluck('skills.name')->map(fn ($name): string => mb_strtolower($name))->all();
        $skills = $profile->skills->map(function ($candidateSkill) use ($jobSkillNames): array {
            $name = (string) $candidateSkill->skill->name;

            return [
                'name' => $name,
                'level' => $candidateSkill->level,
                'years_using' => $candidateSkill->years_using,
                'relevant_to_job' => in_array(mb_strtolower($name), $jobSkillNames, true),
                'source' => 'candidate_profile',
            ];
        })->sortByDesc('relevant_to_job')->values()->all();

        $structuredData = [
            'source_cv_version_id' => $source->id,
            'target_job_id' => $job->id,
            'target_job_title' => $job->title,
            'summary' => $profile->summary,
            'headline' => $profile->headline,
            'skills' => $skills,
            'experience' => $profile->experiences->map(fn ($experience): array => [
                'job_title' => $experience->job_title,
                'company' => $experience->company,
                'start_date' => $experience->start_date?->toDateString(),
                'end_date' => $experience->end_date?->toDateString(),
                'description' => $experience->description,
                'source' => 'candidate_profile',
            ])->values()->all(),
            'projects' => $profile->projects->map(fn ($project): array => [
                'name' => $project->name,
                'description' => $project->description,
                'tech_stack' => $project->tech_stack,
                'source' => 'candidate_profile',
            ])->values()->all(),
            'tailoring_notes' => [
                'Relevant skills are ordered first.',
                'All claims are copied from candidate-provided profile data.',
            ],
            'source' => 'deterministic-tailor-v1',
        ];

        return $profile->cvVersions()->create([
            'title' => "{$source->title} · {$job->title}",
            'kind' => 'tailored',
            'tailored_for_job_id' => $job->id,
            'structured_data' => $structuredData,
            'parse_status' => 'confirmed',
            'parse_confidence' => 1.00,
            'version_number' => (int) $profile->cvVersions()->max('version_number') + 1,
        ])->load('document');
    }
}