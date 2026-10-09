import { api } from "./api";
import type { JobSummary } from "./jobs";

export type ComponentScore = {
  score: number | null;
  weight: number;
  applied: boolean;
  detail: string | null;
};

export type MatchedSkill = {
  id: number;
  name: string;
  is_required: boolean;
  level: number | null;
  years_using: number | null;
  min_years: number | null;
};

export type MissingSkill = {
  id: number;
  name: string;
  is_required: boolean;
  min_years: number | null;
};

export type WeakArea = {
  type: string;
  name?: string;
  message?: string;
};

export type MatchExplanation = {
  overall_score: number;
  weighted_score: number;
  penalty: number;
  recommendation: string | null;
  component_scores: Record<string, ComponentScore>;
  matched_skills: MatchedSkill[];
  missing_skills: MissingSkill[];
  weak_areas: WeakArea[];
  hard_requirement_flags: string[];
  model_version: string;
  computed_at: string;
};

export type JobMatch = { job: JobSummary; match: MatchExplanation };

export type MatchesMeta = {
  total: number;
  returned: number;
  scored_jobs: number;
  model_version: string;
};

export const COMPONENT_LABELS: Record<string, string> = {
  skills: "Skills",
  experience: "Experience",
  education: "Education",
  seniority: "Seniority",
  location: "Location",
  work_mode: "Work mode",
  preference: "Preferences",
  semantic: "Semantic fit",
};

export const FLAG_LABELS: Record<string, string> = {
  missing_required_skills: "Missing required skills",
  experience_below_min: "Below the experience minimum",
  education_below_min: "Below the education minimum",
  insufficient_data: "Not enough data to score yet",
};

export function fetchMatches(params: { limit?: number; min_score?: number } = {}) {
  const search = new URLSearchParams();
  if (params.limit !== undefined) search.set("limit", String(params.limit));
  if (params.min_score !== undefined) search.set("min_score", String(params.min_score));
  const query = search.toString();

  return api.envelope<JobMatch[], MatchesMeta>(query === "" ? "/matches" : `/matches?${query}`);
}

export function fetchJobMatch(jobId: number | string) {
  return api.get<JobMatch>(`/jobs/${jobId}/match`);
}

/** Colour bands mirror config/matching.php recommendation thresholds. */
export function scoreBarClass(score: number): string {
  if (score >= 80) return "bg-green-600";
  if (score >= 60) return "bg-blue-700";
  if (score >= 40) return "bg-amber-500";
  return "bg-zinc-400";
}

export function flagLabel(flag: string): string {
  return FLAG_LABELS[flag] ?? flag.replaceAll("_", " ");
}

export function formatScore(score: number): string {
  return `${Math.round(score)}%`;
}
