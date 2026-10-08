import { api } from "./api";

export type JobCompanyRef = {
  id: number;
  name: string;
  slug: string;
  verification_status: string;
};

export type JobSalary = {
  min_monthly: number | null;
  max_monthly: number | null;
  currency: string;
  negotiable: boolean;
};

export type JobSkillRef = { id: number; name: string; is_required?: boolean };

export type TaxonomyRef = { id: number; name: string; slug?: string };

export type JobSummary = {
  id: number;
  title: string;
  slug: string;
  city: string | null;
  region: string | null;
  country: string;
  work_mode: string;
  employment_type: string;
  seniority: string | null;
  salary: JobSalary;
  experience_years_min: number | null;
  experience_years_max: number | null;
  application_deadline: string | null;
  is_featured: boolean;
  published_at: string | null;
  company?: JobCompanyRef | null;
  job_category?: TaxonomyRef | null;
  skills?: JobSkillRef[];
};

export type JobDetail = JobSummary & {
  description: string | null;
  responsibilities: string | null;
  requirements: string | null;
  qualifications: string | null;
  benefits: string | null;
  min_education_level?: TaxonomyRef | null;
  job_role?: TaxonomyRef | null;
  industry?: TaxonomyRef | null;
  source_url: string | null;
  views_count: number;
  is_saved?: boolean;
};

export type Company = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  website: string | null;
  city: string | null;
  region: string | null;
  country: string | null;
  size: string | null;
  type: string | null;
  verification_status: string;
  social_links: Record<string, string> | null;
  industry?: TaxonomyRef | null;
  jobs_count?: number;
};

export const WORK_MODES: Array<{ value: string; label: string }> = [
  { value: "onsite", label: "On-site" },
  { value: "hybrid", label: "Hybrid" },
  { value: "remote", label: "Remote" },
];

export const EMPLOYMENT_TYPES: Array<{ value: string; label: string }> = [
  { value: "full_time", label: "Full time" },
  { value: "part_time", label: "Part time" },
  { value: "contract", label: "Contract" },
  { value: "internship", label: "Internship" },
  { value: "temporary", label: "Temporary" },
  { value: "freelance", label: "Freelance" },
];

export const SENIORITIES: Array<{ value: string; label: string }> = [
  { value: "entry", label: "Entry" },
  { value: "junior", label: "Junior" },
  { value: "mid", label: "Mid" },
  { value: "senior", label: "Senior" },
  { value: "lead", label: "Lead" },
  { value: "manager", label: "Manager" },
  { value: "director", label: "Director" },
  { value: "executive", label: "Executive" },
];

export const SORTS: Array<{ value: string; label: string }> = [
  { value: "relevance", label: "Most relevant" },
  { value: "newest", label: "Newest" },
  { value: "salary_desc", label: "Highest salary" },
  { value: "deadline", label: "Closing soon" },
];

export type JobFilters = {
  q?: string;
  location?: string;
  company?: string;
  work_modes?: string[];
  employment_types?: string[];
  seniorities?: string[];
  salary_min?: string;
  salary_max?: string;
  experience_years?: string;
  industry_id?: string;
  job_category_id?: string;
  education_level_id?: string;
  posted_within_days?: string;
  sort?: string;
  per_page?: number;
  page?: number;
};

/** Mirrors SearchJobsRequest: arrays use Laravel's `key[]` form, empty values drop out. */
export function buildJobQuery(filters: JobFilters): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value === undefined || value === null || value === "") continue;
    if (Array.isArray(value)) {
      for (const item of value) if (item !== "") params.append(`${key}[]`, item);
      continue;
    }
    params.set(key, String(value));
  }

  return params.toString();
}

export function fetchJobs(filters: JobFilters) {
  const query = buildJobQuery(filters);
  return api.list<JobSummary>(query === "" ? "/jobs" : `/jobs?${query}`);
}

export function fetchJob(id: number | string) {
  return api.get<JobDetail>(`/jobs/${id}`);
}

export function fetchSavedJobs() {
  return api.get<JobSummary[]>("/saved-jobs");
}

export function saveJob(id: number) {
  return api.post<{ is_saved: boolean }>(`/jobs/${id}/save`);
}

export function unsaveJob(id: number) {
  return api.del<{ is_saved: boolean }>(`/jobs/${id}/save`);
}

export function fetchCompany(slug: string) {
  return api.get<Company>(`/companies/${slug}`);
}

export function fetchJobCategories() {
  return api.get<TaxonomyRef[]>("/taxonomy/job-categories");
}

export function fetchIndustries() {
  return api.get<TaxonomyRef[]>("/taxonomy/industries");
}

export function fetchEducationLevels() {
  return api.get<Array<{ id: number; name: string }>>("/taxonomy/education-levels");
}

function labelFor(options: Array<{ value: string; label: string }>, value: string | null): string {
  return options.find((option) => option.value === value)?.label ?? value ?? "—";
}

export const workModeLabel = (value: string | null) => labelFor(WORK_MODES, value);
export const employmentTypeLabel = (value: string | null) => labelFor(EMPLOYMENT_TYPES, value);
export const seniorityLabel = (value: string | null) => labelFor(SENIORITIES, value);

export function formatSalary(salary: JobSalary): string {
  if (salary.negotiable) return "Negotiable";
  const min = salary.min_monthly;
  const max = salary.max_monthly;
  if (min === null && max === null) return "Not disclosed";

  const money = (value: number) => `${value.toLocaleString("en-US")} ${salary.currency}`;
  if (min !== null && max !== null && min !== max) return `${money(min)} – ${money(max)}`;
  return money((max ?? min) as number);
}

export function formatDate(value: string | null): string {
  return value === null ? "—" : new Date(value).toLocaleDateString();
}

export function daysUntil(value: string | null): number | null {
  if (value === null) return null;
  const diff = new Date(value).getTime() - Date.now();
  return Math.ceil(diff / 86_400_000);
}
