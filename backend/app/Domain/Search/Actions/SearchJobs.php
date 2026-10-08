<?php

declare(strict_types=1);

namespace App\Domain\Search\Actions;

use App\Models\EducationLevel;
use App\Models\Job;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Database-side job search (SPEC section 18). Keyword matching uses the
 * generated tsvector column from migration ...000011; every other filter is a
 * plain indexed predicate. Nothing here is AI-decided.
 */
class SearchJobs
{
    public const PER_PAGE_DEFAULT = 15;

    public const PER_PAGE_MAX = 50;

    /**
     * @param  array<string, mixed>  $filters  validated input from SearchJobsRequest
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Job::query()
            ->published()
            ->with(['company:id,name,slug,logo_path,city,verification_status', 'jobCategory:id,name,slug', 'skills:id,name']);

        $keyword = trim((string) ($filters['q'] ?? ''));

        if ($keyword !== '') {
            // plainto_tsquery with a bound parameter: multi-word input becomes an
            // AND of lexemes and no tsquery operator can be injected.
            $query->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$keyword]);
        }

        if (($location = trim((string) ($filters['location'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $location).'%';
            $query->where(fn (Builder $q) => $q
                ->where('city', 'ilike', $like)
                ->orWhere('region', 'ilike', $like));
        }

        if (!empty($filters['work_modes'])) {
            $query->whereIn('work_mode', $filters['work_modes']);
        }

        if (!empty($filters['employment_types'])) {
            $query->whereIn('employment_type', $filters['employment_types']);
        }

        if (!empty($filters['seniorities'])) {
            $query->whereIn('seniority', $filters['seniorities']);
        }

        if (isset($filters['salary_min'])) {
            // A job matches a salary floor when its top of band reaches it, or
            // when the employer marked the salary negotiable.
            $query->where(fn (Builder $q) => $q
                ->where('salary_max_monthly', '>=', (int) $filters['salary_min'])
                ->orWhere('salary_negotiable', true));
        }

        if (isset($filters['salary_max'])) {
            $query->where(fn (Builder $q) => $q
                ->where('salary_min_monthly', '<=', (int) $filters['salary_max'])
                ->orWhereNull('salary_min_monthly'));
        }

        if (isset($filters['experience_years'])) {
            $years = (int) $filters['experience_years'];
            $query->where(fn (Builder $q) => $q
                ->whereNull('experience_years_min')
                ->orWhere('experience_years_min', '<=', $years))
                ->where(fn (Builder $q) => $q
                    ->whereNull('experience_years_max')
                    ->orWhere('experience_years_max', '>=', $years));
        }

        foreach (['job_category_id', 'job_role_id'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, (int) $filters[$column]);
            }
        }

        if (isset($filters['industry_id'])) {
            // Jobs may carry their own industry, but ingested listings usually
            // only inherit it from the employer, so both are accepted.
            $industryId = (int) $filters['industry_id'];
            $query->where(fn (Builder $q) => $q
                ->where('jobs.industry_id', $industryId)
                ->orWhereHas('company', fn (Builder $c) => $c->where('companies.industry_id', $industryId)));
        }

        if (isset($filters['education_level_id'])) {
            $rank = EducationLevel::whereKey((int) $filters['education_level_id'])->value('rank');

            if ($rank !== null) {
                $acceptable = EducationLevel::where('rank', '<=', $rank)->pluck('id');
                $query->where(fn (Builder $q) => $q
                    ->whereNull('min_education_level_id')
                    ->orWhereIn('min_education_level_id', $acceptable));
            }
        }

        if (!empty($filters['skill_ids'])) {
            $ids = array_map('intval', $filters['skill_ids']);
            $query->whereHas('skills', fn (Builder $q) => $q->whereIn('skills.id', $ids));
        }

        if (($company = trim((string) ($filters['company'] ?? ''))) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $company).'%';
            $query->whereHas('company', fn (Builder $q) => $q->where('name', 'ilike', $like));
        }

        if (isset($filters['posted_within_days'])) {
            $query->where('published_at', '>=', now()->subDays((int) $filters['posted_within_days']));
        }

        if (isset($filters['deadline_before'])) {
            $query->whereNotNull('application_deadline')
                ->where('application_deadline', '<=', $filters['deadline_before']);
        }

        $this->applySort($query, $filters['sort'] ?? null, $keyword);

        $perPage = min((int) ($filters['per_page'] ?? self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX);

        return $query->paginate($perPage);
    }

    private function applySort(Builder $query, ?string $sort, string $keyword): void
    {
        match ($sort) {
            'salary_desc' => $query->orderByDesc('salary_max_monthly'),
            'deadline' => $query->whereNotNull('application_deadline')->orderBy('application_deadline'),
            'newest' => $query->orderByDesc('published_at'),
            // Relevance is only meaningful with a keyword; fall back to newest.
            default => $keyword !== ''
                ? $query->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$keyword])
                    ->orderByDesc('published_at')
                : $query->orderByDesc('published_at'),
        };

        // Stable tie-break so pagination never repeats or skips rows.
        $query->orderByDesc('id');
    }
}
