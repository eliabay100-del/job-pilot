<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('base_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('job_source_id')->nullable()->constrained('job_sources')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('requirements')->nullable();
            $table->text('qualifications')->nullable();
            $table->text('benefits')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->default('Ethiopia');
            $table->enum('work_mode', ['onsite', 'hybrid', 'remote'])->default('onsite');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'])->default('full_time');
            $table->enum('seniority', ['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])->nullable();
            $table->foreignId('job_category_id')->nullable()->constrained('job_categories')->nullOnDelete();
            $table->foreignId('job_role_id')->nullable()->constrained('job_roles')->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained('industries')->nullOnDelete();
            $table->foreignId('min_education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->integer('experience_years_min')->nullable();
            $table->integer('experience_years_max')->nullable();
            $table->integer('salary_min_monthly')->nullable();
            $table->integer('salary_max_monthly')->nullable();
            $table->string('salary_currency')->default('ETB');
            $table->boolean('salary_negotiable')->default(false);
            $table->date('application_deadline')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'published', 'expired', 'closed', 'archived'])->default('draft');
            $table->boolean('is_sponsored')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('content_fingerprint')->nullable();
            $table->integer('risk_score')->nullable();
            $table->enum('risk_level', ['low', 'review', 'high'])->nullable();
            $table->integer('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
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

        // Moved here from migration ...000007: these reference jobs, which exists only now.
        Schema::table('cv_versions', function (Blueprint $table) {
            $table->foreignId('tailored_for_job_id')->nullable()->constrained('jobs')->nullOnDelete();
        });

        Schema::create('job_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2);          // 0..100 deterministic+semantic blend
            $table->jsonb('component_scores');               // skills/experience/education/seniority/location/work_mode/preference/semantic
            $table->jsonb('matched_skills');                 // ids + names
            $table->jsonb('missing_skills');                 // required-but-missing
            $table->jsonb('weak_areas');
            $table->jsonb('hard_requirement_flags');         // e.g. experience_below_min
            $table->string('recommendation', 240)->nullable();
            $table->string('model_version', 80)->nullable();  // weights/config version used
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['candidate_profile_id', 'job_id']);
            $table->index(['candidate_profile_id', 'overall_score']);
        });

        // FIX: Only execute PostgreSQL full-text search parameters if driver is NOT sqlite
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE jobs ADD COLUMN IF NOT EXISTS search_vector tsvector
                GENERATED ALWAYS AS (
                    setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                    setweight(to_tsvector('simple', coalesce(description, '')), 'B') ||
                    setweight(to_tsvector('simple', coalesce(city, '')), 'C')
                ) STORED;
            ");

            DB::statement('CREATE INDEX IF NOT EXISTS jobs_search_vector_idx ON jobs USING gin(search_vector);');
            
            // Optional pgvector extension embedding column check
            if ($this->pgvectorAvailable()) {
                DB::statement('ALTER TABLE jobs ADD COLUMN IF NOT EXISTS embedding vector(1536);');
            }
        }
    }

    private function pgvectorAvailable(): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }
        return (bool) DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            if ($this->pgvectorAvailable()) {
                DB::statement('ALTER TABLE jobs DROP COLUMN IF EXISTS embedding;');
            }
            DB::statement('DROP INDEX IF EXISTS jobs_search_vector_idx;');
            DB::statement('ALTER TABLE jobs DROP COLUMN IF EXISTS search_vector;');
        }

        Schema::dropIfExists('job_matches');

        Schema::table('cv_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tailored_for_job_id');
        });

        Schema::dropIfExists('job_source_records');
        Schema::dropIfExists('saved_jobs');
        Schema::dropIfExists('job_skills');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_sources');
    }
};
