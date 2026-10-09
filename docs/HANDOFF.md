# Handoff — Phase 2 candidate platform, Phase 3 jobs, Phase 4 matching (2026-10-09)

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

## Phase 3 — jobs, search, ingestion (2026-10-09)

**Backend** — models and policies for `Company`, `CompanyUser`, `CompanyVerification`, `Job`,
`JobSkill`, `JobSource`, `JobSourceRecord`, `SavedJob`; `JobPolicy` (published-only reads,
author/company-member preview of unpublished listings, saving only published jobs). The
`SearchJobs` action uses the migration-generated `search_vector` tsvector + GIN index with a bound
`plainto_tsquery('simple', ?)` and `ts_rank` ordering, plus indexed predicates for every SPEC 18
filter: location, company, work mode, employment type, seniority, salary band (negotiable listings
always pass a floor), experience band, industry/category/role, education rank, skills,
posted-within, deadline, four sorts and a stable id tie-break. Endpoints: `GET /jobs`,
`GET /jobs/{id}` (view counter + `is_saved`), `POST|DELETE /jobs/{id}/save`, `GET /saved-jobs`,
`GET /companies/{slug}`.

**Ingestion (SPEC 16–17)** — `JobSourceAdapter` contract with `CsvJobSourceAdapter`,
`NormalizeJobRecord` (enum/money/deadline validation + content fingerprint), `IngestJobSource`
(raw record upsert → validate → fingerprint dedupe → publish rule: known company ⇒
`published`/`risk_level=low`, brand-new company ⇒ `pending_review`/`review` for the Phase 7
moderation queue) and `php artisan jobs:ingest {source} --path=`.

**Frontend** — `/dashboard/jobs` (keyword/location/company search, work-mode/employment/seniority
chips, advanced filters for salary band, experience, category/industry/education and posted-within,
sort, per-page and pagination), `/dashboard/jobs/[id]` (full listing + verified-employer card),
`/dashboard/saved`, nav entries and a dashboard CTA. Filters live in the query string through
`useSyncExternalStore` (`src/lib/useJobFilters.ts`) so a search is shareable and survives reloads;
`src/lib/jobs.ts` holds the typed API surface.

**Tests** — `tests/Feature/Jobs/` adds JobSearchTest, SavedJobTest, CompanyTest and
JobIngestionTest. Suite total: **65 passed (289 assertions)** on PostgreSQL.

**Verified in browser** — full-text keyword search, combined chip/advanced filters, URL
round-trips, pagination, save → saved list → unsave, detail page with employer card, graceful 403
on a held listing, and a production build with every route prerendering.

Bugs found and fixed during Phase 3:

- `JobController::index` merged a paginator's `resolve()` into the envelope, which dropped `data`
  and `meta` from the response; it now returns `data` plus an explicit `meta` block.
- `SavedJobController::store` called `firstOrCreate(['job_id' => …])` on a `BelongsToMany`, which
  queried a nonexistent `jobs.job_id` column; replaced with `syncWithoutDetaching` and a 201/200
  split on whether a row was attached.
- `JobResource` probed `$this->attributes` (always null on a resource proxy) for `is_saved`; it now
  reads `$this->resource->getAttribute('is_saved')`.
- The industry filter only matched `jobs.industry_id`, missing ingested listings that inherit the
  industry from their employer; it now also matches through `companies.industry_id`.
- A `JobSource` created via `firstOrCreate` appeared inactive because Postgres fills the
  `is_active` column default but the in-memory model does not; the model declares the same default.
- Next 16 prerender errors on the dynamic job route: `usePathname` in the dashboard layout and
  `useParams` in the detail page are runtime-only URL data, so the nav now sits behind `<Suspense>`
  and the detail page streams behind a boundary.
- The frontend sent array filters as `work_modes=remote`, which Laravel rejects (array expected);
  `buildJobQuery` now emits `work_modes[]=` and `parseFilters` accepts both spellings.

## Phase 4 — deterministic matching + explanation (2026-10-09)

**Engine** — `app/Domain/Matching/`: `ScoreJobMatch` scores eight components (skills, experience,
education, seniority, location, work mode, preference, semantic) from database facts only, each
0–100 or `null` when it cannot be evaluated. A null component drops its weight and the rest are
renormalized, so missing data never masquerades as a bad fit; when nothing at all is scoreable the
result carries an `insufficient_data` flag and a recommendation that says so. `ComponentScore` and
`MatchResult` are readonly value objects — `MatchResult` derives `overallScore`
(`clamp(weighted − penalty, 0, 100)`) and the recommendation from the components it is given, so
there is one source of truth for the arithmetic. Every number comes from
`config/matching.php` (weights, penalties with a cap, skill credit, experience band, seniority
ladder, location/work-mode/preference values, recommendation bands).

Semantic similarity sits behind the `SemanticSimilarityProvider` contract; the bound
implementation is `NullSemanticSimilarityProvider` (returns null, name `none`) because pgvector is
absent locally. Binding a real provider plus raising the weight is the only change needed to turn
embeddings on.

**Persistence** — `JobMatch` model + `job_matches` table (unique per candidate profile and job).
`ComputeJobMatch::handle()` scores and upserts one row; `handleMany()` scores a job set and
`upsert()`s in chunks of 200. `php artisan matching:compute [--profile=] [--job=] [--limit=]`
scores in bulk and prints a summary table; it is idempotent.

**API** — `GET /matches` (`?limit=`, `?min_score=`) and `GET /jobs/{job}/match`, both in
`MatchController`. Every request re-scores from current data and stores the result, so a score can
never go stale relative to the profile. `JobMatchResource` returns `{ job, match }` together, where
`match` carries `overall_score`, `weighted_score`, `penalty`, `recommendation`, per-component
`component_scores` (score, weight, applied, human `detail`), `matched_skills`, `missing_skills`,
`weak_areas`, `hard_requirement_flags`, `model_version` and `computed_at`. `JobMatchPolicy` keeps
candidates on their own rows; the job itself is still gated by `JobPolicy`.

**Frontend** — `/dashboard/matches` (minimum-score and top-N controls, re-score, ranked cards with
save button, and a `MatchPanel` per job) plus a "Your match" card on `/dashboard/jobs/[id]`.
`src/components/MatchPanel.tsx` renders the score bar, recommendation, flag badges, strong/missing
skills, weak areas and a `<details>` disclosure "How this score was calculated" that lists every
component with its score, weight and detail, ending in
"Weighted score X − N points of hard-requirement penalties = Y. Model …, computed …".
`src/lib/matches.ts` holds the types and fetchers.

**Tests** — `tests/Feature/Matching/MatchScoringTest.php` (19 tests) pins the arithmetic: perfect
alignment, required vs nice-to-have skill penalties, half credit below a minimum years requirement,
experience below/above the band, education below/missing/unranked, the seniority and location
ladders, work mode, preference averaging, unscoreable components dropping out of the weight, the
penalty floor at 0, config-driven weights, semantic unavailable vs a bound stub provider, and the
matched-skill evidence shape. `MatchApiTest.php` (12 tests) covers auth, ranking, storage,
published-only, limit/min_score/validation, auto-created profile, single-job breakdown and upsert,
403 on a held listing, 404 on unknown and non-numeric ids, cross-user isolation and the compute
command. Suite total: **96 passed (440 assertions)** on PostgreSQL.

**Verified in browser** — logged in as the demo candidate, `/dashboard/matches` renders nine ranked
cards (89% → 34%) with flags, strong/missing skills, recommendations and the meta line
"Showing 9 of 9 matches · 9 published jobs scored · model deterministic-v1"; the disclosure expands
to the full component breakdown; the job detail page shows the same score under "Your match". No
console errors. `tsc --noEmit`, `npm run lint` and `npm run build` all clean.

Bugs found and fixed during Phase 4:

- `JobMatch::upsert()` silently wrote PHP arrays into jsonb columns: Eloquent's `upsert` does not
  apply model casts. `ComputeJobMatch::handleMany` now `json_encode`s every jsonb value itself.
- `json_encode(100.0)` emits `100`, so JSON assertions written with `assertSame` failed on an
  int/float mismatch. Those two assertions compare numerically instead.
- Adding `'company'` to the numeric `Route::pattern` list broke `CompanyTest`: companies resolve by
  slug (`getRouteKeyName()`), so the constraint turned every company route into a 404. Reverted and
  documented in `AppServiceProvider`.
- The demo candidate's education row had `education_level_id = NULL`, which scored education 0 and
  added an 8-point penalty to every match — a misleading verdict caused by missing data. Two fixes:
  the engine now returns unscoreable (null) with an explanatory `detail` when education exists but
  no row carries a taxonomy level, and `DemoCandidateSeeder` sets Bachelor on create and backfills
  a null level on re-run.

## Environment notes

- PHP is Laravel Herd's: `/c/Users/ThinkPad/.config/herd/bin/php.bat`. Bare `php` is not on PATH in Git Bash.
- pgvector is absent locally; migration `000007` guards the `embedding` columns behind a `pg_extension` check, so migrations still pass.
- `php artisan test --filter=A|B` does not work here: `php.bat` goes through `cmd.exe`, which reads `|` as a pipe. Run one filter or the whole suite.
- **`php artisan serve` cannot accept file uploads on this machine.** Symfony `Process` strips `TMP`/`TEMP` from the child env on Windows, and PHP then cannot create its upload temp file ("File upload error - unable to create a temporary file"). Run the dev server directly instead:
  `cd backend/public && php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
- `infra/setup-portable-postgres.sh`: `initdb` wants `--auth=scram-sha-256` (hyphen before 256), and `pg_ctl start` never returns under Git Bash, so the script backgrounds it and polls `pg_isready`.
- Auth routes are throttled at 6/min. Reloading dashboard pages quickly in dev can 429 `/auth/me`, which the SPA reads as "logged out" and redirects to `/login`. Expected behaviour — wait out the window instead of debugging the session.
- Browse the frontend at `http://localhost:3000`, never `http://127.0.0.1:3000`: Next 16 dev blocks cross-origin dev resources (HMR websocket, fonts) unless the host is in `allowedDevOrigins`, and the app then never hydrates — forms submit natively and no API call is made.
- Do not run `npm run build` while `next dev` is up; it corrupts the running dev server. The stale process keeps holding port 3000 (the restart exits "Port 3000 is in use"), so kill it first: `taskkill //PID <n> //F` in Git Bash.

## Known gaps (intentional, later phases)

- Malware scanning is deferred to Phase 12; uploads are recorded with `scan_status = 'skipped'`.
- CV parsing (`parse_status` stays `pending`) arrives in Phase 5.
- Held (`pending_review`) ingested listings wait for the Phase 7 admin moderation queue; employer posting/management is Phase 9.
- The `semantic` match component is stubbed (`NullSemanticSimilarityProvider`, weight 0) until pgvector is installed; the `embedding` columns exist behind migration `000007`'s extension guard.
- Matches are scored on read and upserted; there is no scheduled bulk recompute yet. `php artisan matching:compute` covers it manually until Phase 12 adds queue/scheduler wiring.
- `docker-compose.yml` has no `backend`/`frontend` app services yet (Phase 1 scope). Docker Desktop is installed but its daemon does not start on this machine, so local dev uses Herd + portable PostgreSQL.
