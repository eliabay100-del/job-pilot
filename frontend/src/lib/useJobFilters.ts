"use client";

import { useCallback, useMemo, useSyncExternalStore } from "react";
import { buildJobQuery, type JobFilters } from "./jobs";

const FILTER_EVENT = "jobpilot:job-filters";

const DEFAULTS: JobFilters = { sort: "relevance", per_page: 10 };

function subscribe(onStoreChange: () => void): () => void {
  window.addEventListener("popstate", onStoreChange);
  window.addEventListener(FILTER_EVENT, onStoreChange);
  return () => {
    window.removeEventListener("popstate", onStoreChange);
    window.removeEventListener(FILTER_EVENT, onStoreChange);
  };
}

export function parseFilters(search: string): JobFilters {
  const params = new URLSearchParams(search);
  // Accept both `key[]` (what we write) and bare repeated `key` params.
  const list = (key: string) => params.getAll(`${key}[]`).concat(params.getAll(key));

  return {
    ...DEFAULTS,
    q: params.get("q") ?? undefined,
    location: params.get("location") ?? undefined,
    company: params.get("company") ?? undefined,
    work_modes: list("work_modes").length ? list("work_modes") : undefined,
    employment_types: list("employment_types").length ? list("employment_types") : undefined,
    seniorities: list("seniorities").length ? list("seniorities") : undefined,
    salary_min: params.get("salary_min") ?? undefined,
    salary_max: params.get("salary_max") ?? undefined,
    experience_years: params.get("experience_years") ?? undefined,
    industry_id: params.get("industry_id") ?? undefined,
    job_category_id: params.get("job_category_id") ?? undefined,
    education_level_id: params.get("education_level_id") ?? undefined,
    posted_within_days: params.get("posted_within_days") ?? undefined,
    sort: params.get("sort") ?? DEFAULTS.sort,
    per_page: params.get("per_page") ? Number(params.get("per_page")) : DEFAULTS.per_page,
    page: params.get("page") ? Number(params.get("page")) : undefined,
  };
}

/**
 * Job filters live in the query string so searches are shareable and survive a
 * reload. `useSyncExternalStore` keeps the prerendered shell (empty query) and
 * the hydrated client state in sync without a state-setting effect.
 */
export function useJobFilters() {
  const search = useSyncExternalStore(
    subscribe,
    () => window.location.search,
    () => "",
  );

  const filters = useMemo(() => parseFilters(search), [search]);

  const update = useCallback((changes: Partial<JobFilters>, options?: { keepPage?: boolean }) => {
    const next: JobFilters = { ...parseFilters(window.location.search), ...changes };
    if (!options?.keepPage) next.page = undefined;

    const query = buildJobQuery(next);
    window.history.replaceState(null, "", query ? `${window.location.pathname}?${query}` : window.location.pathname);
    window.dispatchEvent(new Event(FILTER_EVENT));
  }, []);

  const reset = useCallback(() => {
    const query = buildJobQuery(DEFAULTS);
    window.history.replaceState(null, "", `${window.location.pathname}?${query}`);
    window.dispatchEvent(new Event(FILTER_EVENT));
  }, []);

  return { filters, search, update, reset };
}
