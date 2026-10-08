<?php

declare(strict_types=1);

namespace App\Domain\JobSources\Adapters;

use App\Domain\JobSources\Contracts\JobSourceAdapter;
use App\Domain\JobSources\RawJobRecord;
use App\Models\JobSource;
use RuntimeException;

/**
 * Admin/CSV import source (SPEC section 16, source type 7). Reads a UTF-8 CSV
 * with a header row; every other column beyond source_url/title/company is
 * optional and passed through as-is for normalization.
 */
class CsvJobSourceAdapter implements JobSourceAdapter
{
    private const COLUMNS = [
        'source_url',
        'external_id',
        'title',
        'company',
        'city',
        'region',
        'work_mode',
        'employment_type',
        'seniority',
        'salary_min_monthly',
        'salary_max_monthly',
        'application_deadline',
        'description',
        'skills',
    ];

    public function name(): string
    {
        return 'csv';
    }

    public function fetch(JobSource $source, array $options = []): array
    {
        $path = (string) ($options['path'] ?? $source->base_url ?? '');

        if ($path === '' || !is_file($path)) {
            throw new RuntimeException("CSV source file not found: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("CSV source file not readable: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                return [];
            }

            $header = array_map(static fn ($column) => trim((string) $column), $header);
            $records = [];
            $fetchedAt = now();

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                $payload = [];

                foreach ($header as $index => $column) {
                    if (in_array($column, self::COLUMNS, true)) {
                        $value = $row[$index] ?? null;
                        $payload[$column] = $value === null ? null : trim((string) $value);
                    }
                }

                $sourceUrl = $payload['source_url'] ?? null;

                if ($sourceUrl === null || $sourceUrl === '') {
                    continue;
                }

                $records[] = new RawJobRecord(
                    sourceUrl: $sourceUrl,
                    payload: $payload,
                    externalId: $payload['external_id'] ?? null,
                    fetchedAt: $fetchedAt,
                );
            }

            return $records;
        } finally {
            fclose($handle);
        }
    }
}
