<?php

declare(strict_types=1);

namespace App\Domain\Matching;

use Illuminate\Support\Carbon;

/**
 * Deterministic output of scoring one candidate against one job. Everything the
 * UI needs to explain the score lives here (SPEC section 19: "The system must
 * explain its score."). The overall score and recommendation are derived from
 * the components, never passed in, so they cannot disagree with the breakdown.
 */
final readonly class MatchResult
{
    /** @var array<string, ComponentScore> */
    public array $components;

    public float $overallScore;

    public ?string $recommendation;

    /**
     * @param  array<string, ComponentScore>  $components
     * @param  array<int, array<string, mixed>>  $matchedSkills
     * @param  array<int, array<string, mixed>>  $missingSkills
     * @param  array<int, array<string, mixed>>  $weakAreas
     * @param  array<int, string>  $flags
     */
    public function __construct(
        public int $candidateProfileId,
        public int $jobId,
        array $components,
        public array $matchedSkills,
        public array $missingSkills,
        public array $weakAreas,
        public array $hardRequirementFlags,
        public float $penalty,
        public string $modelVersion,
        public Carbon $computedAt,
    ) {
        $this->components = $components;
        $this->overallScore = $this->deriveOverallScore();
        $this->recommendation = $this->deriveRecommendation();
    }

    public function component(string $name): ?ComponentScore
    {
        return $this->components[$name] ?? null;
    }

    /** Weighted mean of the components that could be scored, before penalties. */
    public function weightedScore(): float
    {
        $totalWeight = $this->appliedWeight();

        if ($totalWeight === 0) {
            return 0.0;
        }

        $weighted = 0.0;
        foreach ($this->components as $component) {
            if (!$component->isApplied() || $component->weight <= 0) {
                continue;
            }

            $weighted += $component->weight * (float) $component->score;
        }

        return $weighted / $totalWeight;
    }

    /** Sum of the weights that actually contributed to the score. */
    public function appliedWeight(): int
    {
        $total = 0;

        foreach ($this->components as $component) {
            if ($component->isApplied() && $component->weight > 0) {
                $total += $component->weight;
            }
        }

        return $total;
    }

    /** @return array<string, array{score: float|null, weight: int, applied: bool, detail: string|null}> */
    public function componentScores(): array
    {
        $scores = [];

        foreach ($this->components as $name => $component) {
            $scores[$name] = $component->toArray();
        }

        return $scores;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'component_scores' => $this->componentScores(),
            'matched_skills' => $this->matchedSkills,
            'missing_skills' => $this->missingSkills,
            'weak_areas' => $this->weakAreas,
            'hard_requirement_flags' => $this->hardRequirementFlags,
            'recommendation' => $this->recommendation,
            'model_version' => $this->modelVersion,
            'computed_at' => $this->computedAt,
        ];
    }

    private function deriveOverallScore(): float
    {
        return round(max(0.0, min(100.0, $this->weightedScore() - $this->penalty)), 2);
    }

    private function deriveRecommendation(): ?string
    {
        if ($this->appliedWeight() === 0) {
            return (string) config('matching.insufficient_data_recommendation');
        }

        foreach ((array) config('matching.recommendations') as $threshold => $recommendation) {
            if ($this->overallScore >= (float) $threshold) {
                return (string) $recommendation;
            }
        }

        return null;
    }
}
