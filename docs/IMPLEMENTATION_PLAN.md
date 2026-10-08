# Implementation Plan

Statuses: Not Started, In Progress, Blocked, Testing, Complete.
A feature is Complete only when DB, backend, API, frontend, validation, authorization, error handling, tests, and docs all exist.

| Phase | Feature | Status | Backend | Frontend | Database | Tests | Docs | Depends on |
|---|---|---|---|---|---|---|---|---|
| 1 | Foundation: Docker, auth, roles, API structure, design system | Not Started | - | - | - | - | - | - |
| 2 | Candidate platform: profile, education, experience, skills, CV, documents | Not Started | - | - | - | - | - | 1 |
| 3 | Jobs: companies, jobs, categories, sources, ingestion, search, filters | Not Started | - | - | - | - | - | 1 |
| 4 | Matching: deterministic, embeddings, score, explanation | Not Started | - | - | - | - | - | 2, 3 |
| 5 | AI: CV parser, tailor, cover letter, assistant, interview prep | Not Started | - | - | - | - | - | 2, 4 |
| 6 | Applications: save, apply, tracker, reminders | Not Started | - | - | - | - | - | 3 |
| 7 | Notifications: email, Telegram, in-app | Not Started | - | - | - | - | - | 6 |
| 8 | Payments: plans, subscriptions, provider abstraction, webhooks, entitlements | Not Started | - | - | - | - | - | 1 |
| 9 | Employer: verification, posting, applicants, pipeline | Not Started | - | - | - | - | - | 3, 6 |
| 10 | University: institutions, cohorts, career center, analytics | Not Started | - | - | - | - | - | 2 |
| 11 | Integrations: Gmail, GitHub, more payment providers | Not Started | - | - | - | - | - | 6, 8 |
| 12 | Production hardening: security, perf, a11y, AI eval, backups, monitoring | Not Started | - | - | - | - | - | all |

## MVP scope (from spec section 65)

Auth, candidate profile, CV upload and parsing, skills/education/experience, job database, search and filters, AI matching with explanation, save job, application tracking, basic career assistant, email and Telegram notifications, admin dashboard, employer job posting, basic subscriptions, payment provider abstraction.

Deferred past MVP: Gmail and GitHub integrations, advanced portfolio, university platform, advanced employer candidate search, advanced analytics, multiple payment providers, advanced interview simulation.

## Assumptions log

- Laravel + Next.js split (backend/ and frontend/) per spec section 10.
- PostgreSQL with pgvector for embeddings; Postgres full-text search first.
