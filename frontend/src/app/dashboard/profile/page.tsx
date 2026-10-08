"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import { CrudSection } from "@/components/CrudSection";
import { Badge, Button, Card, ErrorText, Field, Input, SectionHeader, Select, Spinner, Textarea } from "@/components/ui";

type Profile = Record<string, unknown> & { id: number };
type Preferences = Record<string, unknown>;

const GENDERS = ["male", "female", "other", "undisclosed"];
const REMOTE = ["onsite", "hybrid", "remote", "flexible"];
const EMPLOYMENT = ["full_time", "part_time", "contract", "internship", "temporary", "freelance"];
const LEVELS = ["basic", "working", "professional", "native"];

function labelize(value: string): string {
  return value.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase());
}

function AboutSection() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [photo, setPhoto] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.get<Profile>("/profile").then((p) => {
      setProfile(p);
      const initial: Record<string, string> = {};
      for (const key of [
        "display_name", "headline", "summary", "city", "region", "country",
        "date_of_birth", "gender", "current_job_title", "current_company",
        "preferred_job_title", "preferred_location", "remote_preference",
        "employment_type_preference", "expected_salary_monthly_etb", "availability",
      ]) {
        const v = p[key];
        initial[key] = v === null || v === undefined ? "" : String(v);
      }
      setForm(initial);
    });
  }, []);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    setSaved(false);
    try {
      const payload: Record<string, unknown> = {};
      for (const [key, value] of Object.entries(form)) {
        payload[key] = value === "" ? null : key === "expected_salary_monthly_etb" ? Number(value) : value;
      }
      let updated: Profile;
      if (photo) {
        const fd = new FormData();
        fd.append("photo", photo);
        for (const [key, value] of Object.entries(payload)) {
          if (value !== null) fd.append(key, String(value));
        }
        updated = await api.upload<Profile>("/profile", fd);
      } else {
        updated = await api.put<Profile>("/profile", payload);
      }
      setProfile(updated);
      setSaved(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Save failed.");
    } finally {
      setBusy(false);
    }
  }

  if (!profile) return <Spinner />;

  const set = (key: string) => (e: { target: { value: string } }) =>
    setForm({ ...form, [key]: e.target.value });

  return (
    <Card>
      <SectionHeader title="About you" description="The basics employers see first." />
      <form onSubmit={onSubmit} className="grid gap-3 sm:grid-cols-2">
        <Field label="Display name"><Input value={form.display_name ?? ""} onChange={set("display_name")} /></Field>
        <Field label="Headline"><Input value={form.headline ?? ""} onChange={set("headline")} placeholder="e.g. Full Stack Developer" /></Field>
        <div className="sm:col-span-2">
          <Field label="Summary"><Textarea value={form.summary ?? ""} onChange={set("summary")} /></Field>
        </div>
        <Field label="City"><Input value={form.city ?? ""} onChange={set("city")} /></Field>
        <Field label="Region"><Input value={form.region ?? ""} onChange={set("region")} /></Field>
        <Field label="Date of birth"><Input type="date" value={form.date_of_birth ?? ""} onChange={set("date_of_birth")} /></Field>
        <Field label="Gender">
          <Select value={form.gender ?? ""} onChange={set("gender")}>
            {GENDERS.map((g) => <option key={g} value={g}>{labelize(g)}</option>)}
          </Select>
        </Field>
        <Field label="Current job title"><Input value={form.current_job_title ?? ""} onChange={set("current_job_title")} /></Field>
        <Field label="Current company"><Input value={form.current_company ?? ""} onChange={set("current_company")} /></Field>
        <Field label="Preferred job title"><Input value={form.preferred_job_title ?? ""} onChange={set("preferred_job_title")} /></Field>
        <Field label="Preferred location"><Input value={form.preferred_location ?? ""} onChange={set("preferred_location")} /></Field>
        <Field label="Remote preference">
          <Select value={form.remote_preference ?? ""} onChange={set("remote_preference")}>
            {REMOTE.map((r) => <option key={r} value={r}>{labelize(r)}</option>)}
          </Select>
        </Field>
        <Field label="Employment type preference">
          <Select value={form.employment_type_preference ?? ""} onChange={set("employment_type_preference")}>
            <option value="">—</option>
            {EMPLOYMENT.map((t) => <option key={t} value={t}>{labelize(t)}</option>)}
          </Select>
        </Field>
        <Field label="Expected monthly salary (ETB)">
          <Input type="number" min={0} value={form.expected_salary_monthly_etb ?? ""} onChange={set("expected_salary_monthly_etb")} />
        </Field>
        <Field label="Availability"><Input value={form.availability ?? ""} onChange={set("availability")} placeholder="e.g. Immediately, 2 weeks notice" /></Field>
        <Field label="Profile photo">
          <Input type="file" accept="image/*" onChange={(e) => setPhoto(e.target.files?.[0] ?? null)} />
        </Field>
        <div className="flex items-center gap-3 sm:col-span-2">
          <Button type="submit" disabled={busy}>{busy ? "Saving…" : "Save profile"}</Button>
          {saved && <span className="text-sm text-green-600">Saved.</span>}
          <ErrorText error={error} />
        </div>
      </form>
    </Card>
  );
}

function PreferencesSection() {
  const [prefs, setPrefs] = useState<Preferences | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.get<Preferences>("/profile/preferences").then(setPrefs);
  }, []);

  if (!prefs) return <Spinner />;

  const toggle = (key: string, value: string) => {
    const current = (prefs[key] as string[] | undefined) ?? [];
    const next = current.includes(value)
      ? current.filter((v) => v !== value)
      : [...current, value];
    setPrefs({ ...prefs, [key]: next });
  };

  async function save() {
    setBusy(true);
    setError(null);
    setSaved(false);
    try {
      const payload: Record<string, unknown> = {};
      for (const key of ["work_modes", "locations", "employment_types", "salary_min_monthly_eth", "experience_level", "open_to_remote", "willing_to_relocate"]) {
        if (prefs![key] !== undefined) payload[key] = prefs![key];
      }
      setPrefs(await api.put<Preferences>("/profile/preferences", payload));
      setSaved(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Save failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <SectionHeader title="Job preferences" description="What you want next. Used by matching later." />
      <div className="space-y-4">
        <div>
          <p className="mb-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">Work modes</p>
          <div className="flex flex-wrap gap-2">
            {["onsite", "hybrid", "remote"].map((mode) => (
              <button
                key={mode}
                type="button"
                onClick={() => toggle("work_modes", mode)}
                className={`rounded-full border px-3 py-1 text-xs font-medium ${
                  ((prefs.work_modes as string[]) ?? []).includes(mode)
                    ? "border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200"
                    : "border-zinc-300 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300"
                }`}
              >
                {labelize(mode)}
              </button>
            ))}
          </div>
        </div>
        <div>
          <p className="mb-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">Employment types</p>
          <div className="flex flex-wrap gap-2">
            {EMPLOYMENT.map((type) => (
              <button
                key={type}
                type="button"
                onClick={() => toggle("employment_types", type)}
                className={`rounded-full border px-3 py-1 text-xs font-medium ${
                  ((prefs.employment_types as string[]) ?? []).includes(type)
                    ? "border-blue-600 bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200"
                    : "border-zinc-300 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300"
                }`}
              >
                {labelize(type)}
              </button>
            ))}
          </div>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Preferred locations (comma separated)">
            <Input
              value={((prefs.locations as string[]) ?? []).join(", ")}
              onChange={(e) =>
                setPrefs({ ...prefs, locations: e.target.value.split(",").map((s) => s.trim()).filter(Boolean) })
              }
            />
          </Field>
          <Field label="Minimum monthly salary (ETB)">
            <Input
              type="number"
              min={0}
              value={prefs.salary_min_monthly_eth === null ? "" : String(prefs.salary_min_monthly_eth ?? "")}
              onChange={(e) =>
                setPrefs({ ...prefs, salary_min_monthly_eth: e.target.value === "" ? null : Number(e.target.value) })
              }
            />
          </Field>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={Boolean(prefs.open_to_remote)}
              onChange={(e) => setPrefs({ ...prefs, open_to_remote: e.target.checked })}
            />
            Open to remote
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={Boolean(prefs.willing_to_relocate)}
              onChange={(e) => setPrefs({ ...prefs, willing_to_relocate: e.target.checked })}
            />
            Willing to relocate
          </label>
        </div>
        <div className="flex items-center gap-3">
          <Button onClick={() => void save()} disabled={busy}>{busy ? "Saving…" : "Save preferences"}</Button>
          {saved && <span className="text-sm text-green-600">Saved.</span>}
          <ErrorText error={error} />
        </div>
      </div>
    </Card>
  );
}

type TaxonomySkill = { id: number; name: string; slug: string };
type AttachedSkill = { id: number; skill_id: number; skill: string | null; level: number };

function SkillsSection() {
  const [skills, setSkills] = useState<AttachedSkill[]>([]);
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<TaxonomySkill[]>([]);
  const [level, setLevel] = useState(3);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api.get<AttachedSkill[]>("/profile/skills").then(setSkills);
  }, []);

  useEffect(load, [load]);

  useEffect(() => {
    const t = setTimeout(() => {
      if (query.trim() === "") {
        setResults([]);
        return;
      }
      api
        .get<TaxonomySkill[]>(`/taxonomy/skills?q=${encodeURIComponent(query)}`)
        .then((r) => setResults(r.filter((s) => !skills.some((a) => a.skill_id === s.id))))
        .catch(() => setResults([]));
    }, 250);
    return () => clearTimeout(t);
  }, [query, skills]);

  async function attach(skillId: number) {
    setError(null);
    try {
      await api.post("/profile/skills", { skill_id: skillId, level });
      setQuery("");
      setResults([]);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not add skill.");
    }
  }

  async function setSkillLevel(id: number, newLevel: number) {
    await api.put(`/profile/skills/${id}`, { level: newLevel });
    load();
  }

  async function remove(id: number) {
    await api.del(`/profile/skills/${id}`);
    load();
  }

  return (
    <Card>
      <SectionHeader title="Skills" description="Pick from the platform taxonomy so matching can use them." />
      <div className="flex gap-2">
        <Input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search skills, e.g. Laravel…"
        />
        <Select value={String(level)} onChange={(e) => setLevel(Number(e.target.value))} className="w-28">
          {[1, 2, 3, 4, 5].map((l) => (
            <option key={l} value={l}>Level {l}</option>
          ))}
        </Select>
      </div>
      {results.length > 0 && (
        <ul className="mt-2 max-h-40 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
          {results.map((s) => (
            <li key={s.id}>
              <button
                type="button"
                onClick={() => void attach(s.id)}
                className="w-full px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-zinc-800"
              >
                {s.name}
              </button>
            </li>
          ))}
        </ul>
      )}
      <ErrorText error={error} />
      <div className="mt-3 flex flex-wrap gap-2">
        {skills.map((s) => (
          <span
            key={s.id}
            className="inline-flex items-center gap-1 rounded-full border border-zinc-300 py-1 pl-3 pr-1 text-xs dark:border-zinc-700"
          >
            {s.skill ?? `Skill #${s.skill_id}`}
            <select
              value={s.level}
              onChange={(e) => void setSkillLevel(s.id, Number(e.target.value))}
              className="bg-transparent text-xs focus:outline-none"
              aria-label={`Level for ${s.skill}`}
            >
              {[1, 2, 3, 4, 5].map((l) => (
                <option key={l} value={l}>{l}</option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => void remove(s.id)}
              className="rounded-full px-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-600"
              aria-label={`Remove ${s.skill}`}
            >
              ×
            </button>
          </span>
        ))}
        {skills.length === 0 && (
          <span className="text-sm text-zinc-500">No skills yet — search above to add.</span>
        )}
      </div>
    </Card>
  );
}

type Item = Record<string, unknown> & { id: number };

const str = (item: Item, key: string): string => {
  const v = item[key];
  return v === null || v === undefined ? "" : String(v);
};

export default function ProfilePage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold tracking-tight">Your profile</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Everything here feeds deterministic matching (Phase 4) and CV tailoring (Phase 5).
        </p>
      </div>

      <AboutSection />
      <PreferencesSection />
      <SkillsSection />

      <CrudSection
        title="Education"
        endpoint="/profile/education"
        emptyMessage="No education yet."
        fields={[
          { name: "institution_name", label: "Institution", placeholder: "e.g. Addis Ababa University" },
          { name: "degree", label: "Degree", required: true, placeholder: "e.g. BSc Software Engineering" },
          { name: "field_of_study", label: "Field of study" },
          { name: "start_year", label: "Start year", type: "number", min: 1950, max: 2100 },
          { name: "end_year", label: "End year", type: "number", min: 1950, max: 2100 },
          { name: "cgpa", label: "CGPA (0–4)", type: "number", min: 0, max: 4 },
          { name: "grade", label: "Grade" },
          { name: "notes", label: "Notes", type: "textarea" },
        ]}
        renderItem={(item) => (
          <div>
            <p className="text-sm font-medium">{str(item, "degree")}</p>
            <p className="text-xs text-zinc-500">
              {[str(item, "institution_name") || str(item, "institution"), str(item, "field_of_study")].filter(Boolean).join(" · ")}
              {item.start_year ? ` · ${String(item.start_year)}–${item.currently_studying ? "present" : String(item.end_year ?? "")}` : ""}
            </p>
            {item.cgpa ? <p className="mt-1"><Badge>CGPA {String(item.cgpa)}</Badge></p> : null}
          </div>
        )}
      />

      <CrudSection
        title="Experience"
        description="Years of experience is computed automatically from these entries."
        endpoint="/profile/experiences"
        emptyMessage="No experience yet."
        fields={[
          { name: "job_title", label: "Job title", required: true },
          { name: "company", label: "Company", required: true },
          { name: "employment_type", label: "Employment type", type: "select", options: EMPLOYMENT.map((t) => ({ value: t, label: labelize(t) })) },
          { name: "location", label: "Location" },
          { name: "start_date", label: "Start date", type: "date", required: true },
          { name: "end_date", label: "End date (blank = current)", type: "date" },
          { name: "description", label: "Description", type: "textarea" },
          { name: "achievements", label: "Achievements", type: "textarea" },
        ]}
        renderItem={(item) => (
          <div>
            <p className="text-sm font-medium">
              {str(item, "job_title")} <span className="font-normal text-zinc-500">· {str(item, "company")}</span>
            </p>
            <p className="text-xs text-zinc-500">
              {str(item, "start_date")} — {item.is_current || !item.end_date ? "present" : str(item, "end_date")}
              {item.employment_type ? ` · ${labelize(String(item.employment_type))}` : ""}
            </p>
          </div>
        )}
      />

      <CrudSection
        title="Projects"
        endpoint="/profile/projects"
        emptyMessage="No projects yet."
        fields={[
          { name: "name", label: "Project name", required: true },
          { name: "role", label: "Your role" },
          { name: "url", label: "URL", placeholder: "https://…" },
          { name: "repository_url", label: "Repository URL", placeholder: "https://…" },
          { name: "tech_stack", label: "Tech stack (comma separated)", type: "tags" },
          { name: "started_at", label: "Started", type: "date" },
          { name: "completed_at", label: "Completed", type: "date" },
          { name: "description", label: "Description", type: "textarea" },
        ]}
        renderItem={(item) => (
          <div>
            <p className="text-sm font-medium">{str(item, "name")}</p>
            <p className="text-xs text-zinc-500">
              {[str(item, "role"), Array.isArray(item.tech_stack) ? (item.tech_stack as string[]).join(", ") : ""].filter(Boolean).join(" · ")}
            </p>
          </div>
        )}
      />

      <CrudSection
        title="Certifications"
        endpoint="/profile/certifications"
        emptyMessage="No certifications yet."
        fields={[
          { name: "name", label: "Certification", required: true },
          { name: "issuer", label: "Issuer" },
          { name: "credential_id", label: "Credential ID" },
          { name: "issued_at", label: "Issued", type: "date" },
          { name: "expires_at", label: "Expires", type: "date" },
          { name: "url", label: "URL", placeholder: "https://…" },
        ]}
        renderItem={(item) => (
          <div>
            <p className="text-sm font-medium">{str(item, "name")}</p>
            <p className="text-xs text-zinc-500">
              {[str(item, "issuer"), str(item, "issued_at") && `Issued ${str(item, "issued_at")}`].filter(Boolean).join(" · ")}
            </p>
          </div>
        )}
      />

      <CrudSection
        title="Languages"
        endpoint="/profile/languages"
        emptyMessage="No languages yet."
        fields={[
          { name: "language", label: "Language", required: true, placeholder: "e.g. Amharic" },
          { name: "proficiency", label: "Proficiency", type: "select", required: true, options: LEVELS.map((l) => ({ value: l, label: labelize(l) })) },
          { name: "speaking_level", label: "Speaking", type: "select", options: LEVELS.map((l) => ({ value: l, label: labelize(l) })) },
          { name: "listening_level", label: "Listening", type: "select", options: LEVELS.map((l) => ({ value: l, label: labelize(l) })) },
          { name: "reading_level", label: "Reading", type: "select", options: LEVELS.map((l) => ({ value: l, label: labelize(l) })) },
          { name: "writing_level", label: "Writing", type: "select", options: LEVELS.map((l) => ({ value: l, label: labelize(l) })) },
        ]}
        renderItem={(item) => (
          <p className="text-sm">
            <span className="font-medium">{str(item, "language")}</span>{" "}
            <span className="text-zinc-500">· {labelize(str(item, "proficiency"))}</span>
          </p>
        )}
      />
    </div>
  );
}
