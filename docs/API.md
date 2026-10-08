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
Authorization failures return 403. Collection endpoints paginate (`?page=`), taxonomies support `?q=` search and `?limit=`.

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

## Seed data (development)

`php artisan db:seed` runs `TaxonomySeeder` (skills/aliases, industries, categories, roles, education levels, institutions) and `DemoCandidateSeeder` (demo job seeker: `demo@jobpilot.test` / `Password!123` with a filled profile).
