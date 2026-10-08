<?php

declare(strict_types=1);

namespace App\Domain\JobSources\Actions;

use App\Domain\JobSources\Contracts\JobSourceAdapter;
use App\Domain\JobSources\IngestionSummary;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobSource;
use App\Models\JobSourceRecord;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Runs the SPEC section 16 pipeline for one source:
 * fetch → raw record → normalize → duplicate detection → validation → publish.
 *
 * Publishing rule (deterministic, no AI): a validated record for a company the
 * platform already knows is published immediately; a record that would create
 * a brand-new, unverified company is held in `pending_review` for the admin
 * moderation queue (Phase 7). Either way the source reference is kept, so
 * reposts never produce duplicate listings (SPEC section 17).
 */
class IngestJobSource
{
    public function __construct(private readonly NormalizeJobRecord $normalizer) {}

    /**
     * @param  array<string, mixed>  $options  adapter-specific fetch options
     */
    public function handle(JobSource $source, JobSourceAdapter $adapter, array $options = []): IngestionSummary
    {
        $records = $adapter->fetch($source, $options);

        $created = 0;
        $duplicated = 0;
        $rejected = 0;

        foreach ($records as $record) {
            $sourceRecord = JobSourceRecord::updateOrCreate(
                ['job_source_id' => $source->id, 'source_url' => $record->sourceUrl],
                [
                    'external_id' => $record->externalId,
                    'raw_payload' => $record->payload,
                    'fetched_at' => $record->fetchedAt ?? now(),
                    'status' => 'new',
                    'error' => null,
                ],
            );

            $normalized = $this->normalizer->handle($record);

            if ($normalized['errors'] !== []) {
                $sourceRecord->update(['status' => 'rejected', 'error' => implode('; ', $normalized['errors'])]);
                $rejected++;

                continue;
            }

            $existing = Job::where('content_fingerprint', $normalized['content_fingerprint'])->first();

            if ($existing !== null) {
                $sourceRecord->update(['status' => 'duplicate', 'job_id' => $existing->id]);
                $existing->update(['last_checked_at' => now()]);
                $duplicated++;

                continue;
            }

            $job = DB::transaction(fn () => $this->createJob($source, $normalized, $sourceRecord));
            $created++;
        }

        return new IngestionSummary(count($records), $created, $duplicated, $rejected);
    }

    /**
     * @param  array{attributes: array<string, mixed>, company_name: string|null, skill_names: list<string>, errors: list<string>, content_fingerprint: string}  $normalized
     */
    private function createJob(JobSource $source, array $normalized, JobSourceRecord $sourceRecord): Job
    {
        $company = Company::firstOrCreate(
            ['slug' => Str::slug((string) $normalized['company_name'])],
            ['name' => (string) $normalized['company_name'], 'verification_status' => 'unverified'],
        );

        $knownCompany = !$company->wasRecentlyCreated;

        $attributes = $normalized['attributes'];
        $attributes += [
            'company_id' => $company->id,
            'job_source_id' => $source->id,
            'slug' => $this->uniqueSlug($attributes['title'], $company->name),
            'content_fingerprint' => $normalized['content_fingerprint'],
            'first_seen_at' => now(),
            'last_checked_at' => now(),
            'status' => $knownCompany ? 'published' : 'pending_review',
            'published_at' => $knownCompany ? now() : null,
            'risk_level' => $knownCompany ? 'low' : 'review',
            'risk_score' => 0,
        ];

        $job = Job::create($attributes);

        $skillIds = Skill::whereIn('name', $normalized['skill_names'])->pluck('id');

        foreach ($skillIds as $skillId) {
            $job->skills()->attach($skillId, ['is_required' => true]);
        }

        $sourceRecord->update(['status' => 'parsed', 'job_id' => $job->id]);

        return $job;
    }

    private function uniqueSlug(string $title, string $companyName): string
    {
        $base = Str::slug("{$title} {$companyName}");
        $slug = $base;
        $suffix = 2;

        while (Job::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
