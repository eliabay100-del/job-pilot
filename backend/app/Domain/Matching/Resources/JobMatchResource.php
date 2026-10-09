<?php

declare(strict_types=1);

namespace App\Domain\Matching\Resources;

use App\Domain\Jobs\Resources\JobSummaryResource;
use App\Domain\Matching\MatchResult;
use App\Models\Job;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A match is always returned next to the job it describes, so the client can
 * render "87% — Senior Data Analyst at Acme" plus the explanation in one pass.
 *
 * @mixin MatchResult
 */
class JobMatchResource extends JsonResource
{
    public function __construct(
        MatchResult $result,
        private readonly Job $job,
    ) {
        parent::__construct($result);
    }

    public function toArray($request): array
    {
        return [
            'job' => new JobSummaryResource($this->job),
            'match' => [
                'overall_score' => $this->overallScore,
                'weighted_score' => round($this->weightedScore(), 2),
                'penalty' => $this->penalty,
                'recommendation' => $this->recommendation,
                'component_scores' => $this->componentScores(),
                'matched_skills' => $this->matchedSkills,
                'missing_skills' => $this->missingSkills,
                'weak_areas' => $this->weakAreas,
                'hard_requirement_flags' => $this->hardRequirementFlags,
                'model_version' => $this->modelVersion,
                'computed_at' => $this->computedAt->toIso8601String(),
            ],
        ];
    }
}
