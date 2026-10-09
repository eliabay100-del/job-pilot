<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * One scored component of a match. A null score means the component could not
 * be evaluated for this candidate/job pair (e.g. the posting states no minimum
 * education), so its weight is excluded rather than counted as zero.
 */
final readonly class ComponentScore
{
    public function __construct(
        public ?float $score,
        public int $weight,
        public ?string $detail = null,
    ) {}

    public function isApplied(): bool
    {
        return $this->score !== null;
    }

    /** @return array{score: float|null, weight: int, applied: bool, detail: string|null} */
    public function toArray(): array
    {
        return [
            'score' => $this->score === null ? null : round($this->score, 2),
            'weight' => $this->weight,
            'applied' => $this->isApplied(),
            'detail' => $this->detail,
        ];
    }
}
