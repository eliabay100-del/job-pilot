# API

Versioned REST API consumed by the web app and future mobile/PWA clients. Spec sections 39, 61.
Base URL: `/api/v1`. Auth: Bearer token (Laravel Sanctum). This document is filled in as phases are implemented.

## Conventions

- All request/response bodies are JSON (`multipart/form-data` for uploads).
- Successful responses: `{ "success": true, "data": ... }` or an API Resource envelope `{ "data": ... }`.
- Errors use one standard envelope (see `app/Exceptions/ApiExceptionHandler.php`):

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "code": "VALIDATION_FAILED",
  "errors": { "field": ["..."] },
  "request_id": "uuid"
}
```

Machine codes: `VALIDATION_FAILED` (422), `UNAUTHENTICATED` (401), `FORBIDDEN` (403), `NOT_FOUND` (404), `HTTP_ERROR` (other 4xx), `INTERNAL_ERROR` (500).
Authorization failures return 403. Collection endpoints paginate (`?page=`) and include a `meta`
block (`current_page`, `per_page`, `total`, `last_page`, `has_more_pages`); taxonomies support
`?q=` search and `?limit=`.

## Auth (Phase 1)

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | - | Register. `name`, `email`, `password`, optional `role` (self-assigned admin roles rejected). Creates candidate profile for job seekers; sends email verification. |
| POST | `/auth/login` | - | Login. Returns `user` + Sanctum `token`. |
| POST | `/auth/logout` | Bearer | Revoke current token. |
| GET | `/auth/me` | Bearer | Current user. |
| GET | `/auth/email/verify/{id}/{hash}` | Bearer + signed | Verify email. |
| POST | `/auth/email/verification-notification` | Bearer | Resend verification email. |

Auth routes are rate limited (`throttle:6,1`).

## Candidate profile (Phase 2)

All routes require Bearer auth. Ownership is enforced by policies — users only ever reach their own records (403 otherwise).

| Method | Path | Description |
|---|---|---|
| GET | `/profile` | Own candidate profile (auto-created on first access), includes `preference`. |
| PUT/PATCH | `/profile` | Update profile. Optional `photo` upload (multipart, image ≤ 2 MB). `years_experience` is server-computed, never accepted from input. |
| GET | `/profile/{profile}/photo` | Stream own profile photo (access-controlled). |
| GET | `/profile/preferences` | Job preferences. |
| PUT/PATCH | `/profile/preferences` | Upsert job preferences (work modes, locations, employment types, salary min, remote/relocate flags, desired role/industry/category ids). |

### Profile sub-entities (full CRUD unless noted)

| Resource | Base path | Notes |
|---|---|---|
| Education | `/profile/education` | `degree` required (or `institution_id`); CGPA 0–4. |
| Experience | `/profile/experiences` | `job_title`, `company`, `start_date` required; `end_date` ≥ `start_date`; each write recomputes `years_experience` deterministically (merged intervals). |
| Skills | `/profile/skills` | Attach taxonomy `skill_id` with `level` 1–5; unique per profile. No show endpoint. |
| Projects | `/profile/projects` | `name` required; `tech_stack` array. |
| Certifications | `/profile/certifications` | `name` required; `expires_at` > `issued_at`. |
| Languages | `/profile/languages` | Unique language per profile; proficiency `basic|working|professional|native` + per-skill levels. No show endpoint. |

## CV (Phase 2 + Phase 5)

| Method | Path | Description |
|---|---|---|
| GET | `/cv` | Paginated CV versions (newest first) with document metadata. |
| POST | `/cv/upload` | Upload CV (multipart `file`; pdf/doc/docx ≤ 5 MB, MIME verified; optional `title`). Creates a `Document` + `CvVersion` (`kind=uploaded`, `parse_status=pending`, auto-incremented `version_number`). Rate limited. |
| GET | `/cv/{id}` | One CV version. |
| POST | `/cv/{id}/parse` | Extract readable, labeled CV fields into `structured_data` with `parse_status=needs_confirmation`; never maps fields into the candidate profile automatically. Rate limited. |
| POST | `/cv/{id}/confirm` | Confirm selected extracted fields with `{ "fields": ["name", "location", "summary"] }`; maps only those supported profile fields and marks the version `confirmed`. Account email and phone are never changed. Rate limited. |
| POST | `/cv/{id}/tailor` | Create a traceable `kind=tailored` version for `{ "target_job_id": 123 }`; reorders existing profile skills and experience only. Rate limited. |
| GET | `/cv/{id}/download` | Access-controlled download of the backing file. |
| DELETE | `/cv/{id}` | Delete version; backing file deleted when unreferenced. |

File security (SPEC §38): extension + MIME + size validation, generated UUID filenames (original name kept for display only), sha256 recorded, private `local` disk (never the public filesystem), downloads only through authorized endpoints. Malware scanning hooks land in Phase 12 (`scan_status` column). The current Phase 5 parser is a conservative local fallback; hosted provider adapters and usage accounting remain open.

## Documents (Phase 2)

| Method | Path | Description |
|---|---|---|
| GET | `/documents` | Paginated own documents (path/disk/sha never exposed). |
| POST | `/documents` | Upload (pdf/doc/docx/png/jpg ≤ 10 MB). |
| GET | `/documents/{id}/download` | Access-controlled download. |
| DELETE | `/documents/{id}` | Delete record + file. |

## Taxonomy (Phase 2, read-only)

Authenticated lookups for typeaheads/selects. `skills` and `institutions` support `?q=` (case-insensitive) and `?limit=` (≤ 100).

| Path | Returns |
|---|---|
| `/taxonomy/skills` | Active skills `id, name, slug`. |
| `/taxonomy/education-levels` | Ordered by rank. |
| `/taxonomy/institutions` | `id, name, type, city`. |
| `/taxonomy/industries` | Active industries. |
| `/taxonomy/job-categories` | Active categories. |
| `/taxonomy/job-roles` | Active roles, optional `?job_category_id=`. |

## Jobs (Phase 3)

All routes require Bearer auth. Reads are limited to published, unexpired jobs by `JobPolicy`;
an author or an active company member can also view their own unpublished listings (403 otherwise).

| Method | Path | Description |
|---|---|---|
| GET | `/jobs` | Search published jobs. Returns `{ success, data: JobSummary[], meta }`. |
| GET | `/jobs/{id}` | One job with description blocks, taxonomy refs, `source_url`, `views_count` (incremented per view) and `is_saved`. |
| POST | `/jobs/{id}/save` | Save/bookmark. Idempotent: 201 on first save, 200 afterwards. |
| DELETE | `/jobs/{id}/save` | Remove the bookmark. |
| GET | `/saved-jobs` | Own saved jobs (published only; expired listings drop out automatically). |
| GET | `/companies/{slug}` | Company profile with `jobs_count` of published listings. |

### Search parameters (GET /jobs)

| Parameter | Type | Notes |
|---|---|---|
| `q` | string | Full-text search over the generated `search_vector` (`plainto_tsquery('simple', …)`, ts_rank ordering). |
| `location` | string | Case-insensitive match on city or region. |
| `company` | string | Case-insensitive match on company name. |
| `work_modes[]`, `employment_types[]`, `seniorities[]` | enum lists | Laravel array params (`work_modes[]=remote`). |
| `salary_min`, `salary_max` | int | Floor matches when `salary_max_monthly >= salary_min` or the salary is negotiable; ceiling matches when `salary_min_monthly <= salary_max` or is null. |
| `experience_years` | int | Keeps jobs whose min/max band contains the value (null bounds always pass). |
| `industry_id`, `job_category_id`, `job_role_id` | int | Industry also matches through the company's industry. |
| `education_level_id` | int | Candidate level: keeps jobs whose `min_education_level` rank is ≤ the given level's rank (or null). |
| `skill_ids[]` | int list | Job must carry at least one of the skills. |
| `posted_within_days` | int | `published_at` within N days. |
| `deadline_before` | date | `application_deadline` on or before the date. |
| `sort` | `relevance\|newest\|salary_desc\|deadline` | Default `relevance` (falls back to newest without `q`). |
| `page`, `per_page` | int | `per_page` 1–50, default 15. |

`meta` carries `current_page`, `per_page`, `total`, `last_page`, `has_more_pages`. Invalid filter
values return 422 `VALIDATION_FAILED` with per-field errors.

### Ingestion (server-side, SPEC 16–17)

`php artisan jobs:ingest {source=csv} --path=…` fetches a feed through a source adapter
(`CsvJobSourceAdapter`), normalizes and validates each row, deduplicates by content fingerprint
(sha256 of company + title + city + first 500 chars of the cleaned description) and publishes:
valid rows for already-known companies go straight to `published` with `risk_level=low`; rows that
would create a brand-new company are held at `pending_review` with `risk_level=review` for the
Phase 7 moderation queue. Every run is recorded in `job_source_records`
(`parsed` / `duplicate` / `rejected` with the validation error).

## Matching (Phase 4)

Deterministic scoring (SPEC §19): every point comes from database facts, and every score ships
with the arithmetic behind it. Both routes require Bearer auth and always score against the
caller's own candidate profile (auto-created on first access).

| Method | Path | Description |
|---|---|---|
| GET | `/matches` | Every published, unexpired job scored against your profile, ranked by score. Returns `{ success, data: [{ job, match }], meta }`. |
| GET | `/jobs/{id}/match` | One job scored against your profile. Returns `{ success, data: { job, match } }`. |

### Parameters (GET /matches)

| Parameter | Type | Notes |
|---|---|---|
| `limit` | int | 1–100, default 20. Applied after ranking. |
| `min_score` | numeric | 0–100. Drops matches scoring below the value. |

`meta` carries `total` (matches returned before/after filtering), `returned`, `scored_jobs`
(published jobs the engine ran against) and `model_version`. Scoring more than
`matching.max_jobs_per_request` (500) listings per request is capped server-side; use
`php artisan matching:compute` to precompute the full set into `job_matches`.

### Match payload

```json
{
  "job": { "id": 2, "title": "Full Stack Developer", "company": { "name": "Gebeya Inc." } },
  "match": {
    "overall_score": 88.5,
    "weighted_score": 92.5,
    "penalty": 4,
    "recommendation": "Strong candidate. Apply.",
    "component_scores": {
      "skills": { "score": 75, "weight": 30, "applied": true, "detail": "6 of 8 weighted skill points." },
      "semantic": { "score": null, "weight": 0, "applied": false, "detail": "Semantic fit unavailable (none)." }
    },
    "matched_skills": [{ "id": 7, "name": "Laravel", "is_required": true, "level": 4, "years_using": 3, "min_years": 2 }],
    "missing_skills": [{ "id": 12, "name": "TypeScript", "is_required": true, "min_years": null }],
    "weak_areas": [{ "type": "skill_years", "skill_id": 7, "name": "Laravel", "message": "…" }],
    "hard_requirement_flags": ["missing_required_skills"],
    "model_version": "deterministic-v1",
    "computed_at": "2026-10-09T10:25:46+03:00"
  }
}
```

Components are `skills`, `experience`, `education`, `seniority`, `location`, `work_mode`,
`preference` and `semantic`. A component that cannot be evaluated returns `score: null` with
`applied: false` and a `detail` explaining what is missing; its weight drops out and the remaining
weights are renormalized rather than dragging the score down. `overall_score` is
`clamp(weighted_score − penalty, 0, 100)`. Flags: `missing_required_skills`,
`experience_below_min`, `education_below_min`, `insufficient_data` (nothing on either side could be
scored — the recommendation then says so instead of presenting 0 as a verdict).

Weights, penalties, ladders and recommendation bands all live in `backend/config/matching.php`; no
score is hard-coded. The `semantic` component reads a `SemanticSimilarityProvider` binding, which
is the `NullSemanticSimilarityProvider` (always unscoreable, weight 0) until pgvector embeddings
land — swapping the binding is the only change needed to turn it on.

Writes go to `job_matches` (unique per candidate + job) through
`App\Domain\Matching\Actions\ComputeJobMatch`, so a score is stored on read and recomputed on the
next request. `php artisan matching:compute [--profile=] [--job=] [--limit=]` scores in bulk and is
idempotent (upsert). Authorization: `JobMatchPolicy` keeps a candidate on their own rows, and the
job itself is gated by `JobPolicy`, so a held or expired listing 403s/404s like the rest of the
jobs API.

## Seed data (development)

`php artisan db:seed` runs `TaxonomySeeder` (skills/aliases, industries, categories, roles, education levels, institutions), `DemoCandidateSeeder` (demo job seeker: `demo@jobpilot.test` / `Password!123` with a filled profile) and `DemoJobsSeeder` (four verified companies and eleven jobs across categories, seniorities and cities, including one expired and one held-for-review listing).
