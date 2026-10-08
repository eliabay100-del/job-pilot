"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { api } from "@/lib/api";
import { Badge, Card, SectionHeader, Spinner } from "@/components/ui";

type Profile = {
  id: number;
  display_name: string | null;
  headline: string | null;
  city: string | null;
  years_experience: number;
  is_public: boolean;
};

type Counts = { educations: number; experiences: number; skills: number; cvs: number };

export default function DashboardPage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [counts, setCounts] = useState<Counts | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const [p, education, experiences, skills, cv] = await Promise.all([
          api.get<Profile>("/profile"),
          api.get<unknown[]>("/profile/education"),
          api.get<unknown[]>("/profile/experiences"),
          api.get<unknown[]>("/profile/skills"),
          api.get<{ data: unknown[] }>("/cv"),
        ]);
        setProfile(p);
        setCounts({
          educations: education.length,
          experiences: experiences.length,
          skills: skills.length,
          cvs: Array.isArray(cv) ? cv.length : (cv.data?.length ?? 0),
        });
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  if (loading || !profile) return <Spinner />;

  const steps = [
    { label: "Education", done: counts!.educations > 0, href: "/dashboard/profile" },
    { label: "Experience", done: counts!.experiences > 0, href: "/dashboard/profile" },
    { label: "Skills", done: counts!.skills > 0, href: "/dashboard/profile" },
    { label: "CV uploaded", done: counts!.cvs > 0, href: "/dashboard/cv" },
  ];
  const complete = steps.filter((s) => s.done).length;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold tracking-tight">
          {profile.display_name ? `Selam, ${profile.display_name}` : "Your dashboard"}
        </h1>
        <p className="mt-1 text-sm text-zinc-500">
          {profile.headline ?? "Add a headline to introduce yourself to employers."}
          {profile.city ? ` · ${profile.city}` : ""} · {profile.years_experience} yrs experience
        </p>
      </div>

      <Card>
        <SectionHeader
          title="Profile strength"
          description={`${complete} of ${steps.length} basics complete. A complete profile gets better matches.`}
        />
        <div className="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
          <div
            className="h-full rounded-full bg-blue-700 transition-all"
            style={{ width: `${(complete / steps.length) * 100}%` }}
          />
        </div>
        <ul className="mt-4 grid gap-2 sm:grid-cols-2">
          {steps.map((step) => (
            <li key={step.label}>
              <Link
                href={step.href}
                className="flex items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:border-blue-300 dark:border-zinc-700"
              >
                {step.label}
                <Badge tone={step.done ? "green" : "amber"}>{step.done ? "Done" : "To do"}</Badge>
              </Link>
            </li>
          ))}
        </ul>
      </Card>

      <Card>
        <SectionHeader
          title="Coming next"
          description="Job search, AI matching with explanations, and application tracking land in the next phases."
        />
        <p className="text-sm text-zinc-500">
          Finish your profile and upload your CV so matching has everything it needs.
        </p>
      </Card>
    </div>
  );
}
