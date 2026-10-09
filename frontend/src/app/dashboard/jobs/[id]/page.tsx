"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState, Suspense } from "react";
import { api, ApiError } from "@/lib/api";
import { SaveJobButton } from "@/components/JobCard";
import { MatchPanel } from "@/components/MatchPanel";
import { Badge, Button, Card, ErrorText, SectionHeader, Spinner } from "@/components/ui";
import { fetchJobMatch, type MatchExplanation } from "@/lib/matches";
import {
  daysUntil,
  employmentTypeLabel,
  fetchCompany,
  fetchJob,
  formatDate,
  formatSalary,
  seniorityLabel,
  workModeLabel,
  type Company,
  type JobDetail,
} from "@/lib/jobs";

function TextBlock({ title, body }: { title: string; body: string | null }) {
  if (!body || body.trim() === "") return null;
  return (
    <section>
      <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{title}</h2>
      <p className="mt-1 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-300">{body}</p>
    </section>
  );
}

function Facts({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs text-zinc-500">{label}</dt>
      <dd className="text-sm font-medium text-zinc-800 dark:text-zinc-100">{value}</dd>
    </div>
  );
}

type JobState = {
  id: string;
  job: JobDetail | null;
  company: Company | null;
  error: string | null;
};

function describe(err: unknown): string {
  if (err instanceof ApiError && err.status === 403) return "This listing is not published yet.";
  if (err instanceof ApiError && err.status === 404) return "We could not find that job.";
  if (err instanceof ApiError) return err.message;
  return "Could not load the job.";
}

type MatchState = { id: number; match: MatchExplanation | null; error: string | null };

type CvSource = { id: number; kind: string; parse_status: string; title: string };

function TailorCvButton({ jobId }: { jobId: number }) {
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  async function tailor() {
    setBusy(true);
    setMessage(null);
    try {
      const versions = await api.get<CvSource[]>("/cv");
      const source = versions.find((version) => version.kind !== "tailored" && version.parse_status === "confirmed");
      if (!source) {
        setMessage("Confirm a parsed CV first from CV & Documents.");
        return;
      }
      await api.post(`/cv/${source.id}/tailor`, { target_job_id: jobId });
      setMessage("Tailored CV created. Find it in CV & Documents.");
    } catch (err) {
      setMessage(err instanceof ApiError ? err.message : "Could not tailor your CV.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <Button variant="secondary" onClick={() => void tailor()} disabled={busy}>
        {busy ? "Tailoring…" : "Tailor CV"}
      </Button>
      {message && <span className="max-w-56 text-right text-xs text-zinc-500">{message}</span>}
    </div>
  );
}

function MatchCard({ jobId }: { jobId: number }) {
  const [state, setState] = useState<MatchState | null>(null);

  useEffect(() => {
    let cancelled = false;

    fetchJobMatch(jobId)
      .then((result) => {
        if (!cancelled) setState({ id: jobId, match: result.match, error: null });
      })
      .catch((err: unknown) => {
        const message = err instanceof ApiError ? err.message : "Could not score this job.";
        if (!cancelled) setState({ id: jobId, match: null, error: message });
      });

    return () => {
      cancelled = true;
    };
  }, [jobId]);

  if (state === null || state.id !== jobId) {
    return (
      <Card>
        <Spinner label="Scoring this job against your profile…" />
      </Card>
    );
  }

  return (
    <Card>
      <SectionHeader
        title="Your match"
        description="Scored from your profile by the deterministic engine — every point is explained."
      />
      {state.match ? (
        <MatchPanel match={state.match} />
      ) : (
        <p className="text-sm text-zinc-500">{state.error ?? "Match unavailable."}</p>
      )}
    </Card>
  );
}

export default function JobDetailPage() {
  // useParams is runtime-only on dynamic segments; the boundary lets the shell
  // prerender and streams the job in afterwards.
  return (
    <Suspense fallback={<Spinner label="Loading job…" />}>
      <JobDetailContent />
    </Suspense>
  );
}

function JobDetailContent() {
  const params = useParams<{ id: string }>();
  const id = params?.id ?? "";

  const [state, setState] = useState<JobState | null>(null);

  useEffect(() => {
    if (id === "") return;

    let cancelled = false;

    fetchJob(id)
      .then(async (job) => {
        const company = job.company?.slug
          ? await fetchCompany(job.company.slug).catch(() => null)
          : null;
        if (!cancelled) setState({ id, job, company, error: null });
      })
      .catch((err: unknown) => {
        if (!cancelled) setState({ id, job: null, company: null, error: describe(err) });
      });

    return () => {
      cancelled = true;
    };
  }, [id]);

  // Derived from the keyed fetch result, so no effect has to flip a flag.
  const loading = state === null || state.id !== id;
  const job = loading ? null : state?.job ?? null;
  const company = loading ? null : state?.company ?? null;
  const error = loading ? null : state?.error ?? null;

  if (loading) return <Spinner label="Loading job…" />;

  if (error || !job) {
    return (
      <div className="space-y-4">
        <ErrorText error={error ?? "Could not load the job."} />
        <Link href="/dashboard/jobs" className="text-sm font-medium text-blue-700 hover:underline">
          ← Back to search
        </Link>
      </div>
    );
  }

  const deadline = daysUntil(job.application_deadline);

  return (
    <div className="space-y-6">
      <Link href="/dashboard/jobs" className="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">
        ← Back to search
      </Link>

      <Card>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-xl font-bold tracking-tight">{job.title}</h1>
              {job.is_featured && <Badge tone="amber">Featured</Badge>}
            </div>
            <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
              {job.company?.name ?? "Independent employer"} ·{" "}
              {[job.city, job.region].filter(Boolean).join(", ") || job.country}
            </p>
          </div>
          <div className="flex flex-wrap items-start justify-end gap-2">
            <TailorCvButton jobId={job.id} />
            <SaveJobButton jobId={job.id} initialSaved={job.is_saved ?? false} />
          </div>
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <Badge tone="blue">{formatSalary(job.salary)}</Badge>
          <Badge>{workModeLabel(job.work_mode)}</Badge>
          <Badge>{employmentTypeLabel(job.employment_type)}</Badge>
          {job.seniority && <Badge>{seniorityLabel(job.seniority)}</Badge>}
          {job.min_education_level && <Badge>Min. {job.min_education_level.name}</Badge>}
          {job.application_deadline && (
            <Badge tone={deadline !== null && deadline <= 7 ? "amber" : "zinc"}>
              Apply by {formatDate(job.application_deadline)}
              {deadline !== null && deadline >= 0 ? ` · ${deadline}d left` : ""}
            </Badge>
          )}
          <Badge>Posted {formatDate(job.published_at)}</Badge>
          <Badge>{job.views_count} views</Badge>
        </div>

        <dl className="mt-5 grid grid-cols-2 gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-800 md:grid-cols-4">
          <Facts label="Job category" value={job.job_category?.name ?? "—"} />
          <Facts label="Job role" value={job.job_role?.name ?? "—"} />
          <Facts label="Industry" value={job.industry?.name ?? company?.industry?.name ?? "—"} />
          <Facts
            label="Experience"
            value={
              job.experience_years_min === null && job.experience_years_max === null
                ? "Not specified"
                : `${job.experience_years_min ?? 0}–${job.experience_years_max ?? "+"} years`
            }
          />
        </dl>

        {job.skills && job.skills.length > 0 && (
          <div className="mt-5 flex flex-wrap gap-1.5">
            {job.skills.map((skill) => (
              <span
                key={skill.id}
                className="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
              >
                {skill.name}
                {skill.is_required ? " · required" : ""}
              </span>
            ))}
          </div>
        )}
      </Card>

      <MatchCard jobId={job.id} />

      <Card className="space-y-4">
        <TextBlock title="About the role" body={job.description} />
        <TextBlock title="Responsibilities" body={job.responsibilities} />
        <TextBlock title="Requirements" body={job.requirements} />
        <TextBlock title="Qualifications" body={job.qualifications} />
        <TextBlock title="Benefits" body={job.benefits} />
      </Card>

      <Card>
        <SectionHeader
          title={company?.name ?? job.company?.name ?? "Employer"}
          description={
            company?.verification_status === "verified"
              ? "Verified employer on JobPilot"
              : "Employer profile is not verified yet"
          }
        />
        {company ? (
          <div className="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
            {company.description && <p className="whitespace-pre-line">{company.description}</p>}
            <dl className="grid grid-cols-2 gap-4 md:grid-cols-4">
              <Facts label="Location" value={[company.city, company.region].filter(Boolean).join(", ") || "—"} />
              <Facts label="Industry" value={company.industry?.name ?? "—"} />
              <Facts label="Size" value={company.size ?? "—"} />
              <Facts label="Open jobs" value={String(company.jobs_count ?? 0)} />
            </dl>
            <div className="flex flex-wrap gap-2">
              {company.website && (
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => window.open(company.website as string, "_blank", "noopener")}
                >
                  Website
                </Button>
              )}
              <Link href={`/dashboard/jobs?company=${encodeURIComponent(company.name)}`}>
                <Button variant="ghost" size="sm">
                  All jobs at {company.name}
                </Button>
              </Link>
            </div>
          </div>
        ) : (
          <p className="text-sm text-zinc-500">No employer profile available.</p>
        )}
      </Card>

      {job.source_url && (
        <p className="text-xs text-zinc-500">
          Original posting:{" "}
          <a
            href={job.source_url}
            target="_blank"
            rel="noopener noreferrer"
            className="text-blue-700 hover:underline dark:text-blue-300"
          >
            {job.source_url}
          </a>
        </p>
      )}
    </div>
  );
}
