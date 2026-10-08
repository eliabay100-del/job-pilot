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

  return (
    <Card>
      <SectionHeader
        title="CV versions"
        description="PDF, DOC or DOCX up to 5 MB. Files are stored privately; parsing arrives in a later phase."
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
                className="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
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
                  <Badge tone={cv.parse_status === "pending" ? "amber" : "blue"}>{cv.parse_status}</Badge>
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
