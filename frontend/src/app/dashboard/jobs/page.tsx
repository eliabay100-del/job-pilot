"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ApiError, type Paginated } from "@/lib/api";
import { JobCard } from "@/components/JobCard";
import { Button, Card, EmptyState, ErrorText, Field, Input, Select, Spinner, cx } from "@/components/ui";
import {
  EMPLOYMENT_TYPES,
  SENIORITIES,
  SORTS,
  WORK_MODES,
  buildJobQuery,
  fetchEducationLevels,
  fetchIndustries,
  fetchJobCategories,
  fetchJobs,
  type JobSummary,
  type TaxonomyRef,
} from "@/lib/jobs";
import { useJobFilters } from "@/lib/useJobFilters";

type ListKey = "work_modes" | "employment_types" | "seniorities";
type TextKey = "q" | "location" | "company";

function Chip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cx(
        "rounded-full border px-3 py-1 text-xs font-medium transition-colors",
        active
          ? "border-blue-600 bg-blue-600 text-white"
          : "border-zinc-300 bg-white text-zinc-700 hover:border-blue-400 hover:text-blue-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300",
      )}
    >
      {children}
    </button>
  );
}

function describe(err: unknown): string {
  if (err instanceof ApiError) {
    // Validation failures carry the useful text in `errors`, not `message`.
    const first = err.errors ? Object.values(err.errors).flat()[0] : undefined;
    return first ?? err.message;
  }
  return "Job search failed.";
}

export default function JobsPage() {
  const { filters, update, reset } = useJobFilters();
  const queryKey = buildJobQuery(filters);

  // Kept out of the fetch state so "loading" is derived during render instead of
  // being toggled synchronously inside an effect.
  const [page, setPage] = useState<{ key: string; result: Paginated<JobSummary> } | null>(null);
  const [failure, setFailure] = useState<{ key: string; message: string } | null>(null);
  const [overrides, setOverrides] = useState<Partial<Record<TextKey, string>>>({});
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [categories, setCategories] = useState<TaxonomyRef[]>([]);
  const [industries, setIndustries] = useState<TaxonomyRef[]>([]);
  const [levels, setLevels] = useState<Array<{ id: number; name: string }>>([]);

  useEffect(() => {
    let cancelled = false;

    fetchJobs(filters)
      .then((result) => {
        if (!cancelled) setPage({ key: queryKey, result });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setFailure({ key: queryKey, message: describe(err) });
      });

    return () => {
      cancelled = true;
    };
  }, [filters, queryKey]);

  useEffect(() => {
    // Optional selects only: if a lookup fails the rest of the search still works.
    void fetchJobCategories().then(setCategories).catch(() => setCategories([]));
    void fetchIndustries().then(setIndustries).catch(() => setIndustries([]));
    void fetchEducationLevels().then(setLevels).catch(() => setLevels([]));
  }, []);

  const loading = page?.key !== queryKey && failure?.key !== queryKey;
  const result = loading ? null : page?.key === queryKey ? page.result : null;
  const error = loading ? null : failure?.key === queryKey ? failure.message : null;
  const jobs = result?.data ?? [];
  const meta = result?.meta;

  const text = (key: TextKey) => overrides[key] ?? filters[key] ?? "";

  function toggleList(key: ListKey, value: string) {
    const selected = filters[key] ?? [];
    const next = selected.includes(value)
      ? selected.filter((item) => item !== value)
      : [...selected, value];
    update({ [key]: next.length ? next : undefined });
  }

  function submitSearch(event: React.FormEvent) {
    event.preventDefault();
    update({
      q: text("q").trim() || undefined,
      location: text("location").trim() || undefined,
      company: text("company").trim() || undefined,
    });
    setOverrides({});
  }

  function clearFilters() {
    setOverrides({});
    reset();
  }

  const from = meta && meta.total > 0 ? (meta.current_page - 1) * meta.per_page + 1 : 0;
  const to = meta ? Math.min(meta.current_page * meta.per_page, meta.total) : 0;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold tracking-tight">Find jobs</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Published vacancies across Ethiopia, ranked by PostgreSQL full-text search.
          </p>
        </div>
        <Link
          href="/dashboard/saved"
          className="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300"
        >
          Saved jobs →
        </Link>
      </div>

      <Card>
        <form onSubmit={submitSearch} className="grid gap-3 md:grid-cols-[1.4fr_1fr_1fr_auto]">
          <Field label="Keyword">
            <Input
              value={text("q")}
              onChange={(event) => setOverrides((current) => ({ ...current, q: event.target.value }))}
              placeholder="e.g. Laravel, accountant, nurse"
            />
          </Field>
          <Field label="Location">
            <Input
              value={text("location")}
              onChange={(event) => setOverrides((current) => ({ ...current, location: event.target.value }))}
              placeholder="City or region"
            />
          </Field>
          <Field label="Company">
            <Input
              value={text("company")}
              onChange={(event) => setOverrides((current) => ({ ...current, company: event.target.value }))}
              placeholder="Employer name"
            />
          </Field>
          <div className="flex items-end gap-2">
            <Button type="submit">Search</Button>
            <Button type="button" variant="ghost" onClick={clearFilters}>
              Reset
            </Button>
          </div>
        </form>

        <div className="mt-4 flex flex-wrap items-center gap-2">
          <span className="text-xs font-medium text-zinc-500">Work mode</span>
          {WORK_MODES.map((option) => (
            <Chip
              key={option.value}
              active={(filters.work_modes ?? []).includes(option.value)}
              onClick={() => toggleList("work_modes", option.value)}
            >
              {option.label}
            </Chip>
          ))}
          <span className="ml-2 text-xs font-medium text-zinc-500">Type</span>
          {EMPLOYMENT_TYPES.map((option) => (
            <Chip
              key={option.value}
              active={(filters.employment_types ?? []).includes(option.value)}
              onClick={() => toggleList("employment_types", option.value)}
            >
              {option.label}
            </Chip>
          ))}
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="ml-auto"
            onClick={() => setShowAdvanced((open) => !open)}
            aria-expanded={showAdvanced}
          >
            {showAdvanced ? "Fewer filters" : "More filters"}
          </Button>
        </div>

        {showAdvanced && (
          <div className="mt-4 grid gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800 md:grid-cols-3">
            <div className="flex flex-wrap gap-2 md:col-span-3">
              <span className="w-full text-xs font-medium text-zinc-500">Seniority</span>
              {SENIORITIES.map((option) => (
                <Chip
                  key={option.value}
                  active={(filters.seniorities ?? []).includes(option.value)}
                  onClick={() => toggleList("seniorities", option.value)}
                >
                  {option.label}
                </Chip>
              ))}
            </div>
            <Field label="Minimum salary (ETB/month)">
              <Input
                type="number"
                min={0}
                step={1000}
                value={filters.salary_min ?? ""}
                onChange={(event) => update({ salary_min: event.target.value || undefined })}
              />
            </Field>
            <Field label="Maximum salary (ETB/month)">
              <Input
                type="number"
                min={0}
                step={1000}
                value={filters.salary_max ?? ""}
                onChange={(event) => update({ salary_max: event.target.value || undefined })}
              />
            </Field>
            <Field label="Your years of experience">
              <Input
                type="number"
                min={0}
                max={60}
                value={filters.experience_years ?? ""}
                onChange={(event) => update({ experience_years: event.target.value || undefined })}
              />
            </Field>
            <Field label="Job category">
              <Select
                value={filters.job_category_id ?? ""}
                onChange={(event) => update({ job_category_id: event.target.value || undefined })}
              >
                <option value="">Any</option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Industry">
              <Select
                value={filters.industry_id ?? ""}
                onChange={(event) => update({ industry_id: event.target.value || undefined })}
              >
                <option value="">Any</option>
                {industries.map((industry) => (
                  <option key={industry.id} value={industry.id}>
                    {industry.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Your education level">
              <Select
                value={filters.education_level_id ?? ""}
                onChange={(event) => update({ education_level_id: event.target.value || undefined })}
              >
                <option value="">Any</option>
                {levels.map((level) => (
                  <option key={level.id} value={level.id}>
                    {level.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Posted within">
              <Select
                value={filters.posted_within_days ?? ""}
                onChange={(event) => update({ posted_within_days: event.target.value || undefined })}
              >
                <option value="">Any time</option>
                <option value="1">Last 24 hours</option>
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
              </Select>
            </Field>
          </div>
        )}

        <div className="mt-4 flex flex-wrap items-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
          <div className="w-48">
            <Field label="Sort by">
              <Select
                value={filters.sort ?? "relevance"}
                onChange={(event) => update({ sort: event.target.value })}
              >
                {SORTS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <div className="w-32">
            <Field label="Per page">
              <Select
                value={String(filters.per_page ?? 10)}
                onChange={(event) => update({ per_page: Number(event.target.value) })}
              >
                {[10, 15, 25, 50].map((size) => (
                  <option key={size} value={size}>
                    {size}
                  </option>
                ))}
              </Select>
            </Field>
          </div>
        </div>
      </Card>

      <ErrorText error={error} />

      <section aria-live="polite">
        {loading ? (
          <Spinner label="Searching jobs…" />
        ) : jobs.length === 0 ? (
          <EmptyState message="No published jobs match these filters yet." />
        ) : (
          <ul className="space-y-3">
            {jobs.map((job) => (
              <JobCard key={job.id} job={job} />
            ))}
          </ul>
        )}
      </section>

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between gap-3 text-sm text-zinc-600 dark:text-zinc-300">
          <span>
            Showing {from}–{to} of {meta.total}
          </span>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={meta.current_page <= 1 || loading}
              onClick={() => update({ page: meta.current_page - 1 }, { keepPage: true })}
            >
              Previous
            </Button>
            <span className="text-xs">
              Page {meta.current_page} / {meta.last_page}
            </span>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={!meta.has_more_pages || loading}
              onClick={() => update({ page: meta.current_page + 1 }, { keepPage: true })}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
