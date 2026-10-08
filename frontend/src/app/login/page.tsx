"use client";

import { useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { Button, ErrorText, Field, Input } from "@/components/ui";

export default function LoginPage() {
  const router = useRouter();
  const { login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await login(email, password);
      router.push("/dashboard");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Login failed. Try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="flex flex-1 items-center justify-center px-6 py-16">
      <div className="w-full max-w-sm">
        <h1 className="text-2xl font-bold tracking-tight">Welcome back</h1>
        <p className="mt-1 text-sm text-zinc-500">Log in to your JobPilot account.</p>

        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <Field label="Email">
            <Input
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </Field>
          <Field label="Password">
            <Input
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </Field>
          <ErrorText error={error} />
          <Button type="submit" disabled={busy} className="w-full">
            {busy ? "Logging in…" : "Log in"}
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-zinc-500">
          No account?{" "}
          <Link href="/register" className="font-medium text-blue-700 hover:underline">
            Register
          </Link>
        </p>
      </div>
    </main>
  );
}
