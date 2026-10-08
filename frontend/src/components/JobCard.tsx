"use client";

import Link from "next/link";
import { useState } from "react";
import { ApiError } from "@/lib/api";
import {
  daysUntil,
  employmentTypeLabel,
  formatDate,
  formatSalary,
  saveJob,
  seniorityLabel,
  unsaveJob,
  workModeLabel,
  type JobSummary,
} from "@/lib/jobs";
import { Badge, Button, ErrorText } from "./ui";

export function SaveJobButton({
  jobId,
  initialSaved,
  onUnsaved,
}: {
  jobId: number;
  initialSaved: boolean;
  onUnsaved?: (jobId: number) => void;
}) {
  const [saved, setSaved] = useState(initialSaved);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function toggle() {
    setBusy(true);
    setError(null);
    try {
      if (saved) {
        await unsaveJob(jobId);
        setSaved(false);
        onUnsaved?.(jobId);
      } else {
        await saveJob(jobId);
        setSaved(true);
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not update the saved list.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <Button
        type="button"
        size="sm"
        variant={saved ? "secondary" : "primary"}
        disabled={busy}
        onClick={() => void toggle()}
        aria-pressed={saved}
      >
        {busy ? "Saving…" : saved ? "Saved" : "Save job"}
      </Button>
      <ErrorText error={error} />
    </div>
  );
}

export function JobCard({
  job,
  saved = false,
  onUnsaved,
}: {
  job: JobSummary;
  saved?: boolean;
  onUnsaved?: (jobId: number) => void;
}) {
  const deadline = daysUntil(job.application_deadline);

  return (
    <li className="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-colors hover:border-blue-300 dark:border-zinc-800 dark:bg-zinc-900">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <Link
              href={`/dashboard/jobs/${job.id}`}
              className="text-base font-semibold text-zinc-900 hover:text-blue-700 dark:text-zinc-50"
            >
              {job.title}
            </Link>
            {job.is_featured && <Badge tone="amber">Featured</Badge>}
          </div>
          <p className="mt-0.5 text-sm text-zinc-600 dark:text-zinc-300">
            {job.company?.name ?? "Independent employer"}
            {job.company?.verification_status === "verified" ? " · verified" : ""}
          </p>
          <p className="mt-1 text-xs text-zinc-500">
            {[
              [job.city, job.region].filter(Boolean).join(", ") || "Ethiopia",
              workModeLabel(job.work_mode),
              employmentTypeLabel(job.employment_type),
              job.seniority ? seniorityLabel(job.seniority) : null,
            ]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
        <SaveJobButton jobId={job.id} initialSaved={saved} onUnsaved={onUnsaved} />
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <Badge tone="blue">{formatSalary(job.salary)}</Badge>
        {job.job_category && <Badge>{job.job_category.name}</Badge>}
        {job.application_deadline && (
          <Badge tone={deadline !== null && deadline <= 7 ? "amber" : "zinc"}>
            Apply by {formatDate(job.application_deadline)}
            {deadline !== null && deadline >= 0 ? ` · ${deadline}d left` : ""}
          </Badge>
        )}
        {job.published_at && <Badge>Posted {formatDate(job.published_at)}</Badge>}
      </div>

      {job.skills && job.skills.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-1.5">
          {job.skills.map((skill) => (
            <span
              key={skill.id}
              className="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
            >
              {skill.name}
            </span>
          ))}
        </div>
      )}
    </li>
  );
}
