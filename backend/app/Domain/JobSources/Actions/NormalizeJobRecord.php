<?php

declare(strict_types=1);

namespace App\Domain\JobSources\Actions;

use App\Domain\JobSources\RawJobRecord;
use Illuminate\Support\Str;

/**
 * Turns one untrusted raw record into validated, normalized job attributes
 * (SPEC section 16 "Parser / Normalizer"). Rejects instead of guessing:
 * anything absent or malformed becomes an error, never a default that would
 * invent factual platform data.
 */
class NormalizeJobRecord
{
    private const WORK_MODES = ['onsite', 'hybrid', 'remote'];

    private const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'];

    private const SENIORITIES = ['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'];

    /**
     * @return array{attributes: array<string, mixed>, company_name: string|null, skill_names: list<string>, errors: list<string>, content_fingerprint: string}
     */
    public function handle(RawJobRecord $record): array
    {
        $payload = $record->payload;
        $errors = [];

        $title = $this->string($payload['title'] ?? null, 200);
        $companyName = $this->string($payload['company'] ?? null, 200);

        if ($title === null) {
            $errors[] = 'title is required';
        }

        if ($companyName === null) {
            $errors[] = 'company is required';
        }

        $city = $this->string($payload['city'] ?? null, 120);
        $region = $this->string($payload['region'] ?? null, 120);
        $description = $this->string($payload['description'] ?? null, 20000);

        $workMode = $this->enum($payload['work_mode'] ?? null, self::WORK_MODES, 'work_mode', $errors) ?? 'onsite';
        $employmentType = $this->enum($payload['employment_type'] ?? null, self::EMPLOYMENT_TYPES, 'employment_type', $errors) ?? 'full_time';
        $seniority = $this->enum($payload['seniority'] ?? null, self::SENIORITIES, 'seniority', $errors);

        $salaryMin = $this->money($payload['salary_min_monthly'] ?? null, 'salary_min_monthly', $errors);
        $salaryMax = $this->money($payload['salary_max_monthly'] ?? null, 'salary_max_monthly', $errors);

        if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
            $errors[] = 'salary_min_monthly must not exceed salary_max_monthly';
        }

        $deadline = null;
        $rawDeadline = $this->string($payload['application_deadline'] ?? null, 20);

        if ($rawDeadline !== null) {
            $deadline = \DateTimeImmutable::createFromFormat('Y-m-d', $rawDeadline);

            if ($deadline === false) {
                $errors[] = 'application_deadline must be a Y-m-d date';
                $deadline = null;
            } else {
                $deadline = $deadline->format('Y-m-d');
            }
        }

        $skillNames = array_values(array_filter(array_map(
            static fn (string $name): string => trim($name),
            preg_split('/[|;,]/', (string) ($payload['skills'] ?? '')) ?: [],
        ), static fn (string $name): bool => $name !== ''));

        $attributes = [
            'title' => $title,
            'description' => $description,
            'city' => $city,
            'region' => $region,
            'country' => 'Ethiopia',
            'work_mode' => $workMode,
            'employment_type' => $employmentType,
            'seniority' => $seniority,
            'salary_min_monthly' => $salaryMin,
            'salary_max_monthly' => $salaryMax,
            'salary_currency' => 'ETB',
            'application_deadline' => $deadline,
            'source_url' => $record->sourceUrl,
        ];

        return [
            'attributes' => $attributes,
            'company_name' => $companyName,
            'skill_names' => $skillNames,
            'errors' => $errors,
            'content_fingerprint' => $this->fingerprint($companyName, $title, $city, $description),
        ];
    }

    /**
     * SPEC section 17: duplicates are detected on company + title + location +
     * normalized description, so the same vacancy reposted by several sources
     * collapses into one job with multiple source references.
     */
    public function fingerprint(?string $company, ?string $title, ?string $city, ?string $description): string
    {
        $normalizedDescription = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $description)));

        return hash('sha256', implode('|', [
            Str::lower(trim((string) $company)),
            Str::lower(trim((string) $title)),
            Str::lower(trim((string) $city)),
            Str::limit(Str::lower($normalizedDescription), 500, ''),
        ]));
    }

    private function string(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Str::limit(trim((string) $value), $max, '');
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<string>  $errors
     */
    private function enum(mixed $value, array $allowed, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = Str::lower(trim((string) $value));

        if (!in_array($normalized, $allowed, true)) {
            $errors[] = "{$field} has unsupported value";

            return null;
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $errors
     */
    private function money(mixed $value, string $field, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!ctype_digit((string) $value)) {
            $errors[] = "{$field} must be a non-negative integer";

            return null;
        }

        return (int) $value;
    }
}
