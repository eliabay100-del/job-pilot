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

## CV (Phase 2)

| Method | Path | Description |
|---|---|---|
| GET | `/cv` | Paginated CV versions (newest first) with document metadata. |
| POST | `/cv/upload` | Upload CV (multipart `file`; pdf/doc/docx ≤ 5 MB, MIME verified; optional `title`). Creates a `Document` + `CvVersion` (`kind=uploaded`, `parse_status=pending`, auto-incremented `version_number`). Rate limited. |
| GET | `/cv/{id}` | One CV version. |
| GET | `/cv/{id}/download` | Access-controlled download of the backing file. |
| DELETE | `/cv/{id}` | Delete version; backing file deleted when unreferenced. |

File security (SPEC §38): extension + MIME + size validation, generated UUID filenames (original name kept for display only), sha256 recorded, private `local` disk (never the public filesystem), downloads only through authorized endpoints. Malware scanning hooks land in Phase 12 (`scan_status` column). CV parsing is Phase 5.

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

## Seed data (development)

`php artisan db:seed` runs `TaxonomySeeder` (skills/aliases, industries, categories, roles, education levels, institutions), `DemoCandidateSeeder` (demo job seeker: `demo@jobpilot.test` / `Password!123` with a filled profile) and `DemoJobsSeeder` (four verified companies and eleven jobs across categories, seniorities and cities, including one expired and one held-for-review listing).
