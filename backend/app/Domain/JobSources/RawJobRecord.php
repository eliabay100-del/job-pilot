<?php

declare(strict_types=1);

namespace App\Domain\JobSources;

use DateTimeInterface;

/**
 * One untrusted record as delivered by a source adapter, before any
 * normalization, dedupe or validation (SPEC section 16 pipeline).
 */
final readonly class RawJobRecord
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $sourceUrl,
        public array $payload,
        public ?string $externalId = null,
        public ?DateTimeInterface $fetchedAt = null,
    ) {}
}
