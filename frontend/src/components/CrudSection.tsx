"use client";

import { useCallback, useEffect, useState, type ReactNode } from "react";
import { api, ApiError } from "@/lib/api";
import { Button, Card, EmptyState, ErrorText, Field, Input, SectionHeader, Select, Textarea } from "@/components/ui";

export type FieldDef = {
  name: string;
  label: string;
  type?: "text" | "textarea" | "number" | "date" | "select" | "tags";
  options?: Array<string | { value: string; label: string }>;
  required?: boolean;
  placeholder?: string;
  min?: number;
  max?: number;
  half?: boolean;
};

type Item = Record<string, unknown> & { id: number };

type FormState = Record<string, string>;

function toFormState(item: Item | null, fields: FieldDef[]): FormState {
  const state: FormState = {};
  for (const f of fields) {
    const value = item?.[f.name];
    if (Array.isArray(value)) state[f.name] = value.join(", ");
    else if (value === null || value === undefined) state[f.name] = "";
    else state[f.name] = String(value);
  }
  return state;
}

function toPayload(form: FormState, fields: FieldDef[]): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  for (const f of fields) {
    const raw = (form[f.name] ?? "").trim();
    if (f.type === "tags") {
      payload[f.name] = raw === "" ? [] : raw.split(",").map((s) => s.trim()).filter(Boolean);
    } else if (f.type === "number") {
      payload[f.name] = raw === "" ? null : Number(raw);
    } else if (f.type === "date") {
      payload[f.name] = raw === "" ? null : raw;
    } else {
      payload[f.name] = raw === "" ? null : raw;
    }
  }
  return payload;
}

export function CrudSection({
  title,
  description,
  endpoint,
  fields,
  renderItem,
  emptyMessage,
  onChanged,
}: {
  title: string;
  description?: string;
  endpoint: string;
  fields: FieldDef[];
  renderItem: (item: Item) => ReactNode;
  emptyMessage: string;
  onChanged?: () => void;
}) {
  const [items, setItems] = useState<Item[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<"new" | number | null>(null);
  const [form, setForm] = useState<FormState>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const data = await api.get<Item[] | { data: Item[] }>(endpoint);
      setItems(Array.isArray(data) ? data : data.data);
    } finally {
      setLoading(false);
    }
  }, [endpoint]);

  useEffect(() => {
    void load();
  }, [load]);

  function startNew() {
    setForm(toFormState(null, fields));
    setEditing("new");
    setError(null);
  }

  function startEdit(item: Item) {
    setForm(toFormState(item, fields));
    setEditing(item.id);
    setError(null);
  }

  async function submit() {
    setBusy(true);
    setError(null);
    try {
      const payload = toPayload(form, fields);
      if (editing === "new") await api.post(endpoint, payload);
      else await api.put(`${endpoint}/${editing}`, payload);
      setEditing(null);
      await load();
      onChanged?.();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Save failed.");
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    setBusy(true);
    try {
      await api.del(`${endpoint}/${id}`);
      await load();
      onChanged?.();
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <SectionHeader
        title={title}
        description={description}
        action={
          editing === null ? (
            <Button size="sm" variant="secondary" onClick={startNew}>
              Add
            </Button>
          ) : undefined
        }
      />

      {loading ? (
        <p className="py-4 text-sm text-zinc-500">Loading…</p>
      ) : items.length === 0 && editing === null ? (
        <EmptyState message={emptyMessage} />
      ) : (
        <ul className="space-y-2">
          {items.map((item) => (
            <li
              key={item.id}
              className="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700"
            >
              <div className="min-w-0 flex-1">{renderItem(item)}</div>
              <div className="flex shrink-0 gap-1">
                <Button size="sm" variant="ghost" onClick={() => startEdit(item)}>
                  Edit
                </Button>
                <Button size="sm" variant="ghost" className="text-red-600" onClick={() => void remove(item.id)}>
                  Delete
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {editing !== null && (
        <div className="mt-4 rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
          <div className="grid gap-3 sm:grid-cols-2">
            {fields.map((f) => {
              const value = form[f.name] ?? "";
              const control =
                f.type === "textarea" ? (
                  <Textarea
                    value={value}
                    placeholder={f.placeholder}
                    onChange={(e) => setForm({ ...form, [f.name]: e.target.value })}
                  />
                ) : f.type === "select" ? (
                  <Select value={value} onChange={(e) => setForm({ ...form, [f.name]: e.target.value })}>
                    <option value="">—</option>
                    {(f.options ?? []).map((opt) => {
                      const { value: v, label } =
                        typeof opt === "string" ? { value: opt, label: opt } : opt;
                      return (
                        <option key={v} value={v}>
                          {label}
                        </option>
                      );
                    })}
                  </Select>
                ) : (
                  <Input
                    type={f.type === "number" ? "number" : f.type === "date" ? "date" : "text"}
                    value={value}
                    required={f.required}
                    min={f.min}
                    max={f.max}
                    placeholder={f.placeholder}
                    onChange={(e) => setForm({ ...form, [f.name]: e.target.value })}
                  />
                );
              return (
                <div key={f.name} className={f.type === "textarea" || f.half === false ? "sm:col-span-2" : ""}>
                  <Field label={f.label}>{control}</Field>
                </div>
              );
            })}
          </div>
          <div className="mt-3 flex items-center gap-2">
            <Button size="sm" disabled={busy} onClick={() => void submit()}>
              {busy ? "Saving…" : "Save"}
            </Button>
            <Button size="sm" variant="ghost" onClick={() => setEditing(null)}>
              Cancel
            </Button>
            <ErrorText error={error} />
          </div>
        </div>
      )}
    </Card>
  );
}
