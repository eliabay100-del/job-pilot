"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api";
import { Badge, Button, Card, EmptyState, ErrorText, Field, Input, SectionHeader } from "@/components/ui";

type CvDocument = {
  id: number;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  scan_status: string;
};

type CvVersion = {
  id: number;
  title: string;
  kind: string;
  version_number: number;
  parse_status: string;
  parse_confidence: string | null;
  parse_error?: string | null;
  structured_data?: {
    name?: string | null;
    email?: string | null;
    phone?: string | null;
    location?: string | null;
    summary?: string | null;
  } | null;
  document: CvDocument | null;
  created_at: string;
};

type DocumentItem = {
  id: number;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  created_at: string;
};

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function CvSection() {
  const [versions, setVersions] = useState<CvVersion[]>([]);
  const [file, setFile] = useState<File | null>(null);
  const [title, setTitle] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [parsingId, setParsingId] = useState<number | null>(null);

  const load = useCallback(() => {
    api.get<CvVersion[]>("/cv").then((data) => setVersions(Array.isArray(data) ? data : []));
  }, []);

  useEffect(load, [load]);

  async function upload(e: FormEvent) {
    e.preventDefault();
    if (!file) return;
    setBusy(true);
    setError(null);
    try {
      const fd = new FormData();
      fd.append("file", file);
      if (title.trim() !== "") fd.append("title", title.trim());
      await api.upload("/cv/upload", fd);
      setFile(null);
      setTitle("");
      load();
    } catch (err) {
      if (err instanceof ApiError && err.errors?.file) setError(err.errors.file.join(" "));
      else setError(err instanceof ApiError ? err.message : "Upload failed.");
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    await api.del(`/cv/${id}`);
    load();
  }

  async function parse(id: number) {
    setParsingId(id);
    setError(null);
    try {
      await api.post(`/cv/${id}/parse`);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not parse this CV.");
    } finally {
      setParsingId(null);
    }
  }

  async function confirm(id: number, data: CvVersion["structured_data"]) {
    const fields = ["name", "location", "summary"].filter((field) => Boolean(data?.[field as keyof NonNullable<typeof data>]));
    if (fields.length === 0) return;
    setParsingId(id);
    setError(null);
    try {
      await api.post(`/cv/${id}/confirm`, { fields });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not confirm the extracted details.");
    } finally {
      setParsingId(null);
    }
  }

  return (
    <Card>
      <SectionHeader
        title="CV versions"
        description="Upload a private PDF, DOC or DOCX, then review the extracted details before they reach your profile."
      />
      <form onSubmit={upload} className="flex flex-wrap items-end gap-3">
        <div className="min-w-52 flex-1">
          <Field label="CV file">
            <Input
              type="file"
              accept=".pdf,.doc,.docx,application/pdf,application/msword"
              required
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            />
          </Field>
        </div>
        <div className="min-w-40 flex-1">
          <Field label="Title (optional)">
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Main CV" />
          </Field>
        </div>
        <Button type="submit" disabled={busy || !file}>
          {busy ? "Uploading…" : "Upload"}
        </Button>
      </form>
      <ErrorText error={error} />

      <div className="mt-4">
        {versions.length === 0 ? (
          <EmptyState message="No CV uploaded yet." />
        ) : (
          <ul className="space-y-2">
            {versions.map((cv) => (
              <li
                key={cv.id}
                className="flex flex-col gap-3 rounded-lg border border-zinc-200 px-3 py-3"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">
                    {cv.title} <span className="text-zinc-400">· v{cv.version_number}</span>
                  </p>
                  <p className="text-xs text-zinc-500">
                    {cv.document ? `${cv.document.original_name} · ${formatBytes(cv.document.size_bytes)}` : "no file"} ·{" "}
                    {new Date(cv.created_at).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  <Badge tone={cv.parse_status === "needs_confirmation" ? "green" : cv.parse_status === "failed" ? "amber" : "blue"}>
                    {cv.parse_status.replace("_", " ")}
                  </Badge>
                  {(cv.parse_status === "pending" || cv.parse_status === "failed") && (
                    <Button size="sm" variant="secondary" disabled={parsingId === cv.id} onClick={() => void parse(cv.id)}>
                      {parsingId === cv.id ? "Reading…" : "Parse CV"}
                    </Button>
                  )}
                  {cv.document && (
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() =>
                        void api.download(`/cv/${cv.id}/download`, cv.document?.original_name ?? "cv")
                      }
                    >
                      Download
                    </Button>
                  )}
                  <Button size="sm" variant="ghost" className="text-red-600" onClick={() => void remove(cv.id)}>
                    Delete
                  </Button>
                </div>
                {cv.parse_status === "needs_confirmation" && cv.structured_data && (
                  <div className="mt-3 rounded-md border border-blue-100 bg-blue-50/70 px-3 py-2 text-xs text-blue-950">
                    <p className="font-semibold">Review extracted details</p>
                    <p className="mt-1 text-blue-900/75">
                      {[cv.structured_data.name, cv.structured_data.email, cv.structured_data.phone, cv.structured_data.location]
                        .filter(Boolean)
                        .join(" · ") || "No labeled contact details were found."}
                    </p>
                    <p className="mt-1 text-blue-900/60">Nothing is added to your profile automatically.</p>
                    <Button size="sm" className="mt-2" disabled={parsingId === cv.id} onClick={() => void confirm(cv.id, cv.structured_data)}>
                      {parsingId === cv.id ? "Confirming…" : "Confirm selected details"}
                    </Button>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </Card>
  );
}

function DocumentsSection() {
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api
      .get<DocumentItem[] | { data: DocumentItem[] }>("/documents")
      .then((data) => setDocuments(Array.isArray(data) ? data : data.data));
  }, []);

  useEffect(load, [load]);

  async function upload(e: FormEvent) {
    e.preventDefault();
    if (!file) return;
    setBusy(true);
    setError(null);
    try {
      const fd = new FormData();
      fd.append("file", file);
      await api.upload("/documents", fd);
      setFile(null);
      load();
    } catch (err) {
      if (err instanceof ApiError && err.errors?.file) setError(err.errors.file.join(" "));
      else setError(err instanceof ApiError ? err.message : "Upload failed.");
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    await api.del(`/documents/${id}`);
    load();
  }

  return (
    <Card>
      <SectionHeader
        title="Documents"
        description="Certificates, portfolios, transcripts — PDF, DOC(X), PNG or JPG up to 10 MB."
      />
      <form onSubmit={upload} className="flex flex-wrap items-end gap-3">
        <div className="min-w-52 flex-1">
          <Field label="File">
            <Input
              type="file"
              accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
              required
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            />
          </Field>
        </div>
        <Button type="submit" disabled={busy || !file}>
          {busy ? "Uploading…" : "Upload"}
        </Button>
      </form>
      <ErrorText error={error} />

      <div className="mt-4">
        {documents.length === 0 ? (
          <EmptyState message="No documents yet." />
        ) : (
          <ul className="space-y-2">
            {documents.map((doc) => (
              <li
                key={doc.id}
                className="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{doc.original_name}</p>
                  <p className="text-xs text-zinc-500">
                    {formatBytes(doc.size_bytes)} · {new Date(doc.created_at).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex shrink-0 gap-2">
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => void api.download(`/documents/${doc.id}/download`, doc.original_name)}
                  >
                    Download
                  </Button>
                  <Button size="sm" variant="ghost" className="text-red-600" onClick={() => void remove(doc.id)}>
                    Delete
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Card>
  );
}

export default function CvPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold tracking-tight">CV & Documents</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Upload and manage your CV versions and supporting documents. All files are stored
          privately and downloaded through your session only.
        </p>
      </div>
      <CvSection />
      <DocumentsSection />
    </div>
  );
}
