<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\JobSources\Actions\IngestJobSource;
use App\Domain\JobSources\Adapters\CsvJobSourceAdapter;
use App\Domain\JobSources\Contracts\JobSourceAdapter;
use App\Models\JobSource;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;

#[AsCommand(
    name: 'jobs:ingest',
    description: 'Fetch and normalize jobs from a configured source into the jobs table',
)]
class IngestJobsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'jobs:ingest
                            {source=csv : Name of the job source (job_sources.name)}
                            {--path= : Adapter input, e.g. the CSV file path for the csv adapter}';

    protected $description = 'Fetch and normalize jobs from a configured source into the jobs table';

    public function handle(IngestJobSource $ingest, CsvJobSourceAdapter $csvAdapter): int
    {
        $name = (string) $this->argument('source');
        $path = $this->option('path');

        $source = JobSource::firstOrCreate(
            ['name' => $name],
            ['base_url' => is_string($path) ? $path : null],
        );

        if (!$source->is_active) {
            $this->error("Source [{$name}] is disabled.");

            return self::FAILURE;
        }

        $adapter = match ($name) {
            'csv' => $csvAdapter,
            default => null,
        };

        if (!$adapter instanceof JobSourceAdapter) {
            $this->error("No adapter registered for source [{$name}].");

            return self::FAILURE;
        }

        $summary = $ingest->handle($source, $adapter, ['path' => $path]);

        $this->table(
            ['fetched', 'created', 'duplicated', 'rejected'],
            [array_values($summary->toArray())],
        );

        return self::SUCCESS;
    }
}
