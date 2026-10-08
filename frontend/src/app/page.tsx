import Link from "next/link";

const features = [
  {
    title: "One strong profile",
    body: "Education, experience, skills, projects and certifications — structured so employers and our matcher can read it.",
  },
  {
    title: "AI job matching",
    body: "Deterministic scoring plus semantic matching explains why a job fits you, and which skills are missing.",
  },
  {
    title: "CV intelligence",
    body: "Upload your CV, let the platform parse and tailor it per job, and track every version in one place.",
  },
  {
    title: "Application tracking",
    body: "Save jobs, apply, and never miss a deadline or follow-up with reminders.",
  },
];

export default function Home() {
  return (
    <div className="flex flex-1 flex-col">
      <header className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <span className="text-lg font-bold tracking-tight">
            Job<span className="text-blue-700">Pilot</span>
          </span>
          <nav className="flex items-center gap-2">
            <Link
              href="/login"
              className="rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
              Log in
            </Link>
            <Link
              href="/register"
              className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
            >
              Get started
            </Link>
          </nav>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-6">
        <section className="py-20 text-center">
          <h1 className="mx-auto max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">
            Your AI-powered career platform for Ethiopia
          </h1>
          <p className="mx-auto mt-5 max-w-2xl text-lg text-zinc-600 dark:text-zinc-300">
            Build a structured profile, get matched to real jobs with transparent
            explanations, tailor your CV, and track every application.
          </p>
          <div className="mt-8 flex items-center justify-center gap-3">
            <Link
              href="/register"
              className="rounded-lg bg-blue-700 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-800"
            >
              Create your profile
            </Link>
            <Link
              href="/login"
              className="rounded-lg border border-zinc-300 bg-white px-6 py-3 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
            >
              I have an account
            </Link>
          </div>
        </section>

        <section className="grid gap-5 pb-20 sm:grid-cols-2 lg:grid-cols-4">
          {features.map((f) => (
            <div
              key={f.title}
              className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"
            >
              <h3 className="text-sm font-semibold">{f.title}</h3>
              <p className="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{f.body}</p>
            </div>
          ))}
        </section>
      </main>

      <footer className="border-t border-zinc-200 py-6 text-center text-xs text-zinc-500 dark:border-zinc-800">
        JobPilot — built for the Ethiopian job market.
      </footer>
    </div>
  );
}
