<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs domain: sources, jobs, job skills, saved jobs.
 * Sponsored/featured jobs must be clearly labeled (business rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('kind', ['employer_direct', 'partner_api', 'rss', 'aggregator', 'public_page', 'admin_import', 'csv']);
            $table->string('base_url')->nullable();
            $table->jsonb('config')->nullable();       // fetch config (never secrets inline; use encrypted vault later)
            $table->boolean('respect_robots')->default(true);
            $table->unsignedSmallInteger('rate_limit_per_hour')->nullable();
            $table->enum('status', ['active', 'paused', 'blocked'])->default('active');
            $table->timestamps();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('job_source_id')->nullable()->constrained('job_sources')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->jsonb('responsibilities')->nullable();
            $table->jsonb('requirements')->nullable();
            $table->jsonb('qualifications')->nullable();
            $table->jsonb('benefits')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('country', 120)->default('Ethiopia');
            $table->enum('work_mode', ['onsite', 'hybrid', 'remote'])->default('onsite');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'])
                ->default('full_time');
            $table->enum('seniority', ['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])
                ->nullable();
            $table->foreignId('job_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_role_id')->nullable()->constrained('job_roles')->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('min_education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->unsignedTinyInteger('experience_years_min')->nullable();
            $table->unsignedTinyInteger('experience_years_max')->nullable();
            // Salary stored in minor units to avoid float issues. ETB monthly by default.
            $table->unsignedBigInteger('salary_min_monthly')->nullable();
            $table->unsignedBigInteger('salary_max_monthly')->nullable();
            $table->char('salary_currency', 3)->default('ETB');
            $table->boolean('salary_negotiable')->default(false);
            $table->date('application_deadline')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'published', 'expired', 'closed', 'archived'])
                ->default('draft');
            $table->boolean('is_sponsored')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            // Ingestion lineage
            $table->string('source_url')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('content_fingerprint', 64)->nullable(); // sha256 of normalized content
            $table->unsignedTinyInteger('risk_score')->nullable();  // 0-100 fraud/risk score
            $table->enum('risk_level', ['low', 'review', 'high'])->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['city', 'work_mode']);
            $table->index(['company_id', 'status']);
            $table->index('content_fingerprint');
            $table->index('application_deadline');
        });

        Schema::create('job_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);   // vs nice-to-have
            $table->unsignedTinyInteger('min_years')->nullable();
            $table->timestamps();
            $table->unique(['job_id', 'skill_id']);
        });

        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'job_id']);
        });

        // Moved here from migration ...000010 because it references job_sources and jobs.
        Schema::create('job_source_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 190)->nullable();
            $table->string('source_url', 600);
            $table->jsonb('raw_payload')->nullable();
            $table->enum('status', ['new', 'parsed', 'duplicate', 'rejected', 'error'])->default('new');
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['job_source_id', 'status']);
            $table->unique(['job_source_id', 'source_url'], 'job_source_records_unique_url');
        });

        // PostgreSQL full-text search column + GIN index (driver: pgsql).
        \DB::statement("ALTER TABLE jobs ADD COLUMN IF NOT EXISTS search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(description, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(city, '')), 'C')
            ) STORED;");
        \DB::statement('CREATE INDEX IF NOT EXISTS jobs_search_vector_idx ON jobs USING GIN (search_vector);');

        // Optional pgvector embedding for the jobs table (see docs/DATABASE.md).
        if ($this->pgvectorAvailable()) {
            \DB::statement('ALTER TABLE jobs ADD COLUMN IF NOT EXISTS embedding vector(1536);');
        }
    }

    private function pgvectorAvailable(): bool
    {
        return (bool) \DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
    }

    public function down(): void
    {
        if ($this->pgvectorAvailable()) {
            \DB::statement('ALTER TABLE jobs DROP COLUMN IF EXISTS embedding;');
        }
        \DB::statement('DROP INDEX IF EXISTS jobs_search_vector_idx;');
        Schema::dropIfExists('job_source_records');
        Schema::dropIfExists('saved_jobs');
        Schema::dropIfExists('job_skills');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_sources');
    }
};
