# Architecture

## Style
Modular monolith: one Laravel application organized by domain (`backend/app/Domain/*`), plus a Next.js frontend. Redis for cache and queues, PostgreSQL as source of truth, S3-compatible object storage for files.

## Intelligence layers
1. **Platform data** - PostgreSQL is the source of truth.
2. **Deterministic logic** - exact things: experience years, skill overlap, location, salary, status, permissions, payments.
3. **Semantic intelligence** - embeddings (pgvector) for similar skills, jobs, and roles.
4. **Generative AI** - CV parsing/tailoring, cover letters, explanations, assistant. Never the source of truth for facts.

## Principles
- Thin controllers; logic in Services/Actions; Policies for authorization; Form Requests for validation.
- Provider abstractions for AI, payments, search, storage, notifications.
- AI calls run on queues, never blocking normal requests.
- Financial records are immutable; payments verified server-side via webhooks.
- Privacy by design: least-privilege access, universities never see private candidate data by default.

See `docs/SPEC.md` for the full requirements.
