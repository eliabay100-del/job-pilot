<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications + employer recruitment pipeline.
 * Status history is append-only via application_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('status', [
                'saved', 'applied', 'viewed', 'screening', 'assessment',
                'interview', 'final_interview', 'offer', 'hired', 'rejected', 'withdrawn',
            ])->default('applied');
            $table->text('cover_letter')->nullable();
            $table->foreignId('cv_version_id')->nullable(); // FK added after cv_versions exists
            $table->decimal('match_score', 5, 2)->nullable(); // snapshot at apply time
            $table->string('apply_source', 24)->default('platform'); // platform|external|manual
            $table->string('external_apply_url')->nullable();
            $table->timestamps();
            $table->unique(['job_id', 'candidate_profile_id']);
            $table->index(['company_id', 'status']);
            $table->index(['candidate_profile_id', 'status']);
        });

        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->string('source', 32)->default('system'); // system|employer|candidate|gmail_sync
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
            $table->index(['application_id', 'occurred_at']);
        });

        // Employer customization: pipeline stages per company.
        Schema::create('recruitment_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('maps_to_status', 32); // one of application statuses
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'position']);
        });

        Schema::create('candidate_shortlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['job_id', 'application_id']);
        });

        Schema::create('candidate_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('candidate_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 16)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('application_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_tag_id')->constrained('candidate_tags')->cascadeOnDelete();
            $table->unique(['application_id', 'candidate_tag_id']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 60)->default('Interview');
            $table->enum('mode', ['in_person', 'phone', 'video', 'other'])->default('video');
            $table->string('location_or_link', 500)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->jsonb('panel_user_ids')->nullable();
            $table->text('notes')->nullable();
            $table->enum('outcome', ['pending', 'passed', 'failed', 'no_show'])->default('pending');
            $table->timestamps();
            $table->index(['company_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'interviews', 'application_tags', 'candidate_tags', 'candidate_notes',
            'candidate_shortlists', 'recruitment_stages', 'application_events', 'applications',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
