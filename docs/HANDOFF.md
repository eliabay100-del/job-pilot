# Handoff — verify-postgres session (2026-10-08)

## Done
- Fresh branch `verify-postgres` from main (ae818cc).
- Verified migrations on **native PostgreSQL 15** (local, no Docker), db `jobpilot`, user `jobpilot`.
- Fixed the real FK-order bug merged in PR #4: migration 000007 referenced `jobs` (created in 000011). Moved `job_matches`, `cv_versions.tailored_for_job_id` FK, and restored dropped tables `job_skills`, `saved_jobs`, `job_source_records` into 000011 with matching down() reversals. Restored jsonb columns. No schema weakened (rule 2 respected; see report).
- `.env.example`: QUEUE_CONNECTION=redis. Removed stray `backend/phase-1-foundation-fixed`.
- migrate:fresh --force: all 13 DONE. rollback + remigrate: OK. \dt = 77 tables; job_skills/saved_jobs/job_source_records/job_matches/queue_jobs/jobs all present. pgvector absent locally; conditional guards work.
- php artisan test: 2 passed (baseline only).

## Broken / caveats
- The previously reported auth API, policies, 37 models, seeders are NOT on main (lost in earlier rewrites). Only 5 models exist. Phase 1 Steps 2b-2g must be redone on top of this branch.

## Next step
Re-run Step 2a (models for all 77-table schema), then 2b policies, 2c Sanctum auth API, tests, seeders. Verify every merge on PostgreSQL, never SQLite.
