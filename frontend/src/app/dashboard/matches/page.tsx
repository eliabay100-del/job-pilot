"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { Badge, Button, Card, EmptyState, ErrorText, Select, Spinner } from "@/components/ui";
import { MatchPanel } from "@/components/MatchPanel";
import { SaveJobButton } from "@/components/JobCard";
import { employmentTypeLabel, fetchSavedJobs, formatSalary, workModeLabel, type JobSummary } from "@/lib/jobs";
import { fetchMatches, type JobMatch, type MatchesMeta } from "@/lib/matches";

const MIN_SCORES = [
  { value: "", label: "Any match" },
  { value: "40", label: "40% and above" },
  { value: "60", label: "60% and above" },
  { value: "80", label: "80% and above" },
];

const LIMITS = [
  { value: 10, label: "Top 10" },
  { value: 20, label: "Top 20" },
  { value: 50, label: "Top 50" },
];

type PageState = { key: string; items: JobMatch[]; meta: MatchesMeta; saved: number[] };
type Failure = { key: string; message: string };

function describe(err: unknown): string {
  if (err instanceof ApiError && err.status === 401) return "Your session expired. Please sign in again.";
  if (err instanceof ApiError) return err.message;
  return "Could not load your matches.";
}

export default function MatchesPage() {
  const [minScore, setMinScore] = useState("");
  const [limit, setLimit] = useState(20);
  const [nonce, setNonce] = useState(0);
  const [state, setState] = useState<PageState | null>(null);
  const [failure, setFailure] = useState<Failure | null>(null);

  const key = `${minScore}|${limit}|${nonce}`;

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      fetchMatches({ limit, ...(minScore === "" ? {} : { min_score: Number(minScore) }) }),
      // The saved list is a nicety here; a failure must not hide the matches.
      fetchSavedJobs().catch(() => [] as JobSummary[]),
    ])
      .then(([response, saved]) => {
        if (!cancelled) {
          setState({
            key,
            items: response.data,
            meta: response.meta,
            saved: saved.map((job) => job.id),
          });
        }
      })
      .catch((err: unknown) => {
        if (!cancelled) setFailure({ key, message: describe(err) });
      });

    return () => {
      cancelled = true;
    };
  }, [key, limit, minScore, nonce]);

  const loading = state?.key !== key && failure?.key !== key;
  const items = state?.key === key ? state.items : [];
  const meta = state?.key === key ? state.meta : null;
  const saved = state?.key === key ? state.saved : [];
  const error = failure?.key === key ? failure.message : null;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold tracking-tight">Your matches</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Every published job scored against your profile, with the arithmetic behind each score.
        </p>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300">
            Minimum match
          </span>
          <Select
            value={minScore}
            onChange={(event) => setMinScore(event.target.value)}
            className="w-44"
          >
            {MIN_SCORES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </label>

        <label className="block">
          <span className="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-300">
            Show
          </span>
          <Select
            value={String(limit)}
            onChange={(event) => setLimit(Number(event.target.value))}
            className="w-32"
          >
            {LIMITS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        </label>

        <Button
          variant="secondary"
          size="sm"
          onClick={() => setNonce((value) => value + 1)}
          disabled={loading}
        >
          {loading ? "Scoring…" : "Re-score"}
        </Button>

        <Link href="/dashboard/profile" className="pb-2 text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">
          Improve your profile →
        </Link>
      </div>

      <ErrorText error={error} />

      {loading ? (
        <Spinner label="Scoring jobs against your profile…" />
      ) : items.length === 0 && !error ? (
        <EmptyState message="No matches yet. Add skills, experience and education so the engine has something to score." />
      ) : (
        <ul className="space-y-4">
          {items.map(({ job, match }) => (
            <li key={job.id}>
              <Card>
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div className="min-w-0">
                    <Link
                      href={`/dashboard/jobs/${job.id}`}
                      className="text-base font-semibold text-zinc-900 hover:text-blue-700 dark:text-zinc-50"
                    >
                      {job.title}
                    </Link>
                    <p className="mt-0.5 text-sm text-zinc-600 dark:text-zinc-300">
                      {job.company?.name ?? "Independent employer"}
                      {job.company?.verification_status === "verified" ? " · verified" : ""}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      <Badge tone="blue">{formatSalary(job.salary)}</Badge>
                      <Badge>{[job.city, job.region].filter(Boolean).join(", ") || job.country}</Badge>
                      <Badge>{workModeLabel(job.work_mode)}</Badge>
                      <Badge>{employmentTypeLabel(job.employment_type)}</Badge>
                    </div>
                  </div>
                  <SaveJobButton jobId={job.id} initialSaved={saved.includes(job.id)} />
                </div>

                <div className="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                  <MatchPanel match={match} />
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {meta && (
        <p className="text-xs text-zinc-500">
          Showing {meta.returned} of {meta.total} matches · {meta.scored_jobs} published jobs scored ·
          model {meta.model_version}
        </p>
      )}
    </div>
  );
}
