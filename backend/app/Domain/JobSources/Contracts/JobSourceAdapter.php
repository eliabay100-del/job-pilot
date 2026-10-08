<?php

declare(strict_types=1);

namespace App\Domain\JobSources\Contracts;

use App\Domain\JobSources\RawJobRecord;
use App\Models\JobSource;

interface JobSourceAdapter
{
    /** Stable identifier used to select the adapter for a job_sources.name. */
    public function name(): string;

    /**
     * @param  array<string, mixed>  $options  adapter-specific (e.g. CSV file path)
     * @return list<RawJobRecord>
     */
    public function fetch(JobSource $source, array $options = []): array;
}
