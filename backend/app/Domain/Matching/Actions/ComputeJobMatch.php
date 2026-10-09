<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\MatchResult;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\JobMatch;
use Illuminate\Support\Collection;

/**
 * Scores and stores matches. Scoring is deterministic, so recomputing on read is
 * safe and keeps stored scores from going stale as a profile changes; the write
 * keeps the history queryable (analytics, notifications, later phases).
 */
class ComputeJobMatch
{
    public function __construct(private readonly ScoreJobMatch $scorer) {}

    public function handle(CandidateProfile $profile, Job $job): MatchResult
    {
        $result = $this->scorer->handle($profile, $job);

        JobMatch::updateOrCreate(
            [
                'candidate_profile_id' => $result->candidateProfileId,
                'job_id' => $result->jobId,
            ],
            $result->toAttributes(),
        );

        return $result;
    }

    /**
     * @param  iterable<Job>  $jobs
     * @return array<int, MatchResult>
     */
    public function handleMany(CandidateProfile $profile, iterable $jobs): array
    {
        $results = [];
        $rows = [];

        foreach ($jobs as $job) {
            $result = $this->scorer->handle($profile, $job);
            $results[] = $result;

            // Eloquent's upsert() bypasses casts, so jsonb columns are encoded
            // here; timestamps are added by the builder.
            $rows[] = [
                'candidate_profile_id' => $result->candidateProfileId,
                'job_id' => $result->jobId,
                'overall_score' => $result->overallScore,
                'component_scores' => json_encode($result->componentScores(), JSON_THROW_ON_ERROR),
                'matched_skills' => json_encode($result->matchedSkills, JSON_THROW_ON_ERROR),
                'missing_skills' => json_encode($result->missingSkills, JSON_THROW_ON_ERROR),
                'weak_areas' => json_encode($result->weakAreas, JSON_THROW_ON_ERROR),
                'hard_requirement_flags' => json_encode($result->hardRequirementFlags, JSON_THROW_ON_ERROR),
                'recommendation' => $result->recommendation,
                'model_version' => $result->modelVersion,
                'computed_at' => $result->computedAt,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            JobMatch::upsert(
                $chunk,
                ['candidate_profile_id', 'job_id'],
                [
                    'overall_score',
                    'component_scores',
                    'matched_skills',
                    'missing_skills',
                    'weak_areas',
                    'hard_requirement_flags',
                    'recommendation',
                    'model_version',
                    'computed_at',
                    'updated_at',
                ],
            );
        }

        return $results;
    }

    /**
     * Published, unexpired jobs with everything the engine reads, eager-loaded so
     * scoring N jobs stays at a constant number of queries.
     *
     * @return Collection<int, Job>
     */
    public function matchableJobs(int $limit): Collection
    {
        $limit = max(1, min($limit, (int) config('matching.max_jobs_per_request')));

        return Job::query()
            ->published()
            ->with(['skills', 'minEducationLevel', 'company', 'jobCategory', 'jobRole', 'industry'])
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }
}
