"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { useAuth } from "@/lib/auth";
import { Button, Spinner, cx } from "@/components/ui";

const nav = [
  { href: "/dashboard", label: "Overview" },
  { href: "/dashboard/profile", label: "Profile" },
  { href: "/dashboard/cv", label: "CV & Documents" },
];

export default function DashboardLayout({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [loading, user, router]);

  // Render the chrome and `children` while the session resolves rather than
  // returning early: with cacheComponents Next.js prerenders this tree to build
  // the static shell, and an early return drops the page segment from it
  // ("instant-unrendered-segment"). Only the confirmed-logged-out case — which
  // is already on its way to /login — renders nothing.
  if (!loading && !user) return null;

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-6 py-8 md:flex-row">
      <aside className="md:w-56 md:shrink-0">
        {user ? (
          <div className="flex items-center justify-between md:block">
            <div>
              <p className="text-sm font-semibold">{user.name}</p>
              <p className="text-xs text-zinc-500">{user.email}</p>
              {user.email_verified_at === null && (
                <p className="mt-1 text-xs text-amber-600">Email not verified</p>
              )}
            </div>
            <Button variant="ghost" size="sm" className="mt-2" onClick={() => void logout().then(() => router.replace("/"))}>
              Log out
            </Button>
          </div>
        ) : (
          <Spinner label="Checking your session…" />
        )}
        <nav className="mt-4 flex gap-1 overflow-x-auto md:mt-6 md:flex-col">
          {nav.map((item) => {
            const active =
              item.href === "/dashboard"
                ? pathname === item.href
                : pathname.startsWith(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cx(
                  "whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium",
                  active
                    ? "bg-blue-50 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                    : "text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800",
                )}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>
      </aside>
      <main className="min-w-0 flex-1">{children}</main>
    </div>
  );
}
