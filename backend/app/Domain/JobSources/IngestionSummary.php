<?php

declare(strict_types=1);

namespace App\Domain\JobSources;

/** Counts for one ingestion run, surfaced by the jobs:ingest command. */
final readonly class IngestionSummary
{
    public function __construct(
        public int $fetched = 0,
        public int $created = 0,
        public int $duplicated = 0,
        public int $rejected = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'fetched' => $this->fetched,
            'created' => $this->created,
            'duplicated' => $this->duplicated,
            'rejected' => $this->rejected,
        ];
    }
}
