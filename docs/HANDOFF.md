# Handoff — Phase 2 candidate platform (2026-10-08)

Branch: `ethiopian-ai-career-platform-a625b`. Previous PRs merged: #8 (frontend scaffold), #9 (Phase 2 backend foundation — models, policies, migrations).

## Done in this session

**Backend (`backend/`)** — Phase 2 API, 54 routes under `/api/v1`:

- Profile: `GET|PUT /profile`, `GET /profile/{profile}/photo`, `GET|PUT /profile/preferences`
- Sub-entities (full CRUD + policies): `profile/education`, `profile/experiences`, `profile/skills` (no `show`), `profile/projects`, `profile/certifications`, `profile/languages` (no `show`)
- CV: `GET /cv`, `POST /cv/upload` (throttle 10/min), `GET|DELETE /cv/{cv}`, `GET /cv/{cv}/download`
- Documents: `GET|POST /documents` (throttle 20/min), `GET /documents/{document}/download`, `DELETE /documents/{document}`
- Taxonomy (read-only Layer 1): `skills` (`?q=`), `education-levels`, `institutions` (`?q=`), `industries`, `job-categories`, `job-roles` (`?job_category_id=`)

Structure follows the existing convention: thin controllers in `app/Http/Controllers/Api/V1/`, domain actions in `app/Domain/*/Actions/`, Form Requests, API Resources, `Gate::policy` registration in `AppServiceProvider`.

Two deterministic-Layer-2 rules from SPEC section 2 are enforced in code:

- `RecomputeYearsExperience` merges overlapping experience intervals and derives `years_experience`. It is never accepted from user input. Called on experience store/update/destroy.
- `StoreUploadedFile` writes to the private `local` disk under `documents/{user_id}/{uuid}.{ext}`, records sha256, and derives the stored extension from the **validated MIME type**, not the client filename.

**Frontend (`frontend/`)** — `src/lib/api.ts` (typed client, error envelope → `ApiError`, authenticated blob downloads), `src/lib/auth.tsx`, `src/components/ui.tsx`, generic `src/components/CrudSection.tsx`, plus `/login`, `/register`, `/dashboard` (overview + profile-strength), `/dashboard/profile`, `/dashboard/cv`. `tsc --noEmit` and `npm run build` both pass.

**Seeders** — `TaxonomySeeder` (9 education levels, 30 skills + aliases, 16 industries, 8 job categories / ~45 roles, 14 Ethiopian institutions) and `DemoCandidateSeeder` (`demo@jobpilot.test` / `Password!123`). Both idempotent (`updateOrCreate` / `firstOrCreate`), so admin edits survive re-runs.

**Tests** — 40 feature tests (191 assertions) across `tests/Feature/Auth/`, `tests/Feature/Candidates/` and `tests/Feature/CV/`, covering auth requirements, validation, cross-user 403s, years recomputation, version numbering, private storage, download streaming and the JSON error envelope. All green on PostgreSQL.

**Docs** — `docs/API.md` documents conventions, the error envelope and every Phase 1/2 endpoint.

## Verified (2026-10-09)

Everything above now runs and has been exercised for real:

1. Portable PostgreSQL 16.15 is up via `infra/setup-portable-postgres.sh` (databases `jobpilot` + `jobpilot_test` on `127.0.0.1:5432`). All 15 migrations pass; `migrate:fresh --seed` is idempotent.
2. `cd backend && php artisan test` → **40 passed (191 assertions)**.
3. Browser walkthrough against the live API: login → dashboard (real `years_experience`), profile edit + save persists, CV upload (real multipart) → listed → downloaded → deleted, logout, and the logged-out `/dashboard` → `/login` guard. No console errors.
4. Phase 2 rows in `docs/IMPLEMENTATION_PLAN.md` are marked Complete.

Bugs found and fixed during verification:

- `User::ensureCandidateProfile()` cached a null `HasOne`, so the second request in a session re-inserted and hit `candidate_profiles_user_id_unique`. Now `firstOrCreate` + `setRelation`.
- `CandidateEducation` resolved to table `candidate_education` (Laravel treats "education" as uncountable). Explicit `$table` added.
- API Resources returned 201 for lazily created rows on `GET /profile` and `PUT /profile/preferences`; statuses are now pinned to 200.
- The framework default `redirectGuestsTo(route('login'))` threw `RouteNotFoundException` inside the auth middleware for any client that omitted `Accept: application/json`, turning 401 into 500. `bootstrap/app.php` now sets `redirectGuestsTo(fn () => null)` plus `shouldRenderJsonWhen`.
- Policy denials returned the generic envelope code; `FORBIDDEN` (403) is now emitted and asserted.
- Non-numeric route IDs (`/api/v1/cv/undefined`) reached Postgres as a bigint bind and 500ed. `AppServiceProvider::boot()` registers `Route::pattern(..., '[0-9]+')` for every model-bound parameter, so they 404.
- `StoreUploadedFile` derived the on-disk extension from the client filename; it now derives it from the content-guessed MIME that the `mimetypes` rule validated, falling back to `.bin`.
- Next.js 16 (`cacheComponents: true`) flagged `/dashboard` with `instant-unrendered-segment`: the auth-gated layout returned early during prerender and dropped the page segment from the static shell. The layout now renders its chrome and `children` while the session resolves.

## Environment notes

- PHP is Laravel Herd's: `/c/Users/ThinkPad/.config/herd/bin/php.bat`. Bare `php` is not on PATH in Git Bash.
- pgvector is absent locally; migration `000007` guards the `embedding` columns behind a `pg_extension` check, so migrations still pass.
- `php artisan test --filter=A|B` does not work here: `php.bat` goes through `cmd.exe`, which reads `|` as a pipe. Run one filter or the whole suite.
- **`php artisan serve` cannot accept file uploads on this machine.** Symfony `Process` strips `TMP`/`TEMP` from the child env on Windows, and PHP then cannot create its upload temp file ("File upload error - unable to create a temporary file"). Run the dev server directly instead:
  `cd backend/public && php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
- `infra/setup-portable-postgres.sh`: `initdb` wants `--auth=scram-sha-256` (hyphen before 256), and `pg_ctl start` never returns under Git Bash, so the script backgrounds it and polls `pg_isready`.

## Known gaps (intentional, later phases)

- Malware scanning is deferred to Phase 12; uploads are recorded with `scan_status = 'skipped'`.
- CV parsing (`parse_status` stays `pending`) arrives in Phase 5.
- `docker-compose.yml` has no `backend`/`frontend` app services yet (Phase 1 scope). Docker Desktop is installed but its daemon does not start on this machine, so local dev uses Herd + portable PostgreSQL.
