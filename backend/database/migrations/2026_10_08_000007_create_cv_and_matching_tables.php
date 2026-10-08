<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents / CV versions, matching engine output, embeddings (pgvector when available).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('disk', 32)->default('local');       // storage disk (private)
            $table->string('path');                              // never in public filesystem
            $table->string('original_name');                     // display only; safe filename generated
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64)->index();
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'skipped'])->default('pending');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cv_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160)->default('Main CV');
            $table->enum('kind', ['uploaded', 'platform', 'tailored'])->default('uploaded');
            $table->foreignId('tailored_for_job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->jsonb('structured_data')->nullable();   // parsed CV JSON (source of truth after confirmation)
            $table->enum('parse_status', ['pending', 'parsing', 'needs_confirmation', 'confirmed', 'failed'])
                ->default('pending');
            $table->decimal('parse_confidence', 3, 2)->nullable();
            $table->text('parse_error')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->timestamps();
            $table->index(['candidate_profile_id', 'parse_status']);
        });

        // NOTE: applications.cv_version_id (column + FK) is created in migration
        // 2026_10_08_000012_create_application_tables.php, because the applications
        // table does not exist yet at this point. Do not add forward references here.

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

        // Semantic layer: pgvector embeddings are OPTIONAL. They are added only when
        // the 'vector' extension is installed; migrations must not fail without it.
        // See docs/DATABASE.md ("Optional pgvector embeddings").
        if ($this->pgvectorAvailable()) {
            DB::statement('ALTER TABLE skills ADD COLUMN IF NOT EXISTS embedding vector(1536);');
            DB::statement('ALTER TABLE candidate_profiles ADD COLUMN IF NOT EXISTS embedding vector(1536);');
            // jobs table is created later (migration ...000011); its embedding column
            // is added there under the same conditional check.
        }
    }

    private function pgvectorAvailable(): bool
    {
        return (bool) DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
    }

    public function down(): void
    {
        if ($this->pgvectorAvailable()) {
            DB::statement('ALTER TABLE skills DROP COLUMN IF EXISTS embedding;');
            DB::statement('ALTER TABLE candidate_profiles DROP COLUMN IF EXISTS embedding;');
        }
        Schema::dropIfExists('job_matches');
        Schema::dropIfExists('cv_versions');
        Schema::dropIfExists('documents');
    }
};
