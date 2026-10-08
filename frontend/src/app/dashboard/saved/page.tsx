"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { JobCard } from "@/components/JobCard";
import { EmptyState, ErrorText, Spinner } from "@/components/ui";
import { fetchSavedJobs, type JobSummary } from "@/lib/jobs";

export default function SavedJobsPage() {
  const [jobs, setJobs] = useState<JobSummary[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    fetchSavedJobs()
      .then((data) => setJobs(Array.isArray(data) ? data : []))
      .catch((err) => setError(err instanceof ApiError ? err.message : "Could not load saved jobs."));
  }, []);

  useEffect(load, [load]);

  const remove = useCallback((jobId: number) => {
    setJobs((current) => (current ?? []).filter((job) => job.id !== jobId));
  }, []);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold tracking-tight">Saved jobs</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Bookmarks you added from search. Listings disappear once they close or expire.
          </p>
        </div>
        <Link href="/dashboard/jobs" className="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">
          ← Back to search
        </Link>
      </div>

      <ErrorText error={error} />

      {jobs === null ? (
        <Spinner label="Loading saved jobs…" />
      ) : jobs.length === 0 ? (
        <EmptyState message="Nothing saved yet — find a role and press “Save job”." />
      ) : (
        <ul className="space-y-3">
          {jobs.map((job) => (
            <JobCard key={job.id} job={job} saved onUnsaved={remove} />
          ))}
        </ul>
      )}
    </div>
  );
}
