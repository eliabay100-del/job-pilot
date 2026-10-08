"use client";

import { useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { Button, ErrorText, Field, Input } from "@/components/ui";

export default function RegisterPage() {
  const router = useRouter();
  const { register, login } = useAuth();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setBusy(true);
    try {
      await register(name, email, password);
      // Log the new user straight in so they land on their dashboard.
      await login(email, password);
      router.push("/dashboard");
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        if (err.errors) setFieldErrors(err.errors);
      } else {
        setError("Registration failed. Try again.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="flex flex-1 items-center justify-center px-6 py-16">
      <div className="w-full max-w-sm">
        <h1 className="text-2xl font-bold tracking-tight">Create your account</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Start building your career profile in minutes.
        </p>

        <form onSubmit={onSubmit} className="mt-6 space-y-4">
          <Field label="Full name" error={fieldErrors.name?.[0]}>
            <Input
              required
              autoComplete="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </Field>
          <Field label="Email" error={fieldErrors.email?.[0]}>
            <Input
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </Field>
          <Field label="Password" error={fieldErrors.password?.[0]}>
            <Input
              type="password"
              required
              minLength={8}
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </Field>
          <ErrorText error={error} />
          <Button type="submit" disabled={busy} className="w-full">
            {busy ? "Creating account…" : "Register"}
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-zinc-500">
          Already registered?{" "}
          <Link href="/login" className="font-medium text-blue-700 hover:underline">
            Log in
          </Link>
        </p>
      </div>
    </main>
  );
}
