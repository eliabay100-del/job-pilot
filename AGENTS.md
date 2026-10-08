# Instructions for coding agents (Claude Code, etc.)

The full product specification is in `docs/SPEC.md`. Read it before changing anything.

Rules:
1. Follow the phases in `docs/IMPLEMENTATION_PLAN.md`, in order. Do not jump ahead.
2. Stack: Laravel (modular monolith, `backend/`), Next.js + TypeScript + Tailwind (`frontend/`), PostgreSQL, Redis, S3-compatible storage.
3. The database is the source of truth; AI never decides factual platform data.
4. Money records are immutable; verify payments server-side only.
5. A feature is done only when DB, backend, API, frontend, validation, authorization, tests, and docs all exist.
6. Update the status table in `docs/IMPLEMENTATION_PLAN.md` as work completes.
7. Never commit secrets. Use `.env.example`.
