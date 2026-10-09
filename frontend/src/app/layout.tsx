import type { Metadata } from "next";
import "./globals.css";
import { AuthProvider } from "@/lib/auth";

export const metadata: Metadata = {
  title: "JobPilot — AI-powered Ethiopian career platform",
  description:
    "Build your profile, upload your CV, and get AI-matched to Ethiopian jobs.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="flex min-h-full flex-col bg-[#f6f8fb] text-zinc-900">
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
