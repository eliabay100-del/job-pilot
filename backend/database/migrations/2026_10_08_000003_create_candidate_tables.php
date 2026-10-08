<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidate platform data (Phase 2 core tables).
 * All rows belong to a user; private by default (least-privilege access).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline', 160)->nullable();
            $table->text('summary')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();   // Ethiopian region, e.g. Addis Ababa
            $table->string('country', 120)->default('Ethiopia');
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'undisclosed'])->default('undisclosed');
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('years_experience')->default(0); // computed deterministically
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index(['city', 'region']);
        });

        Schema::create('candidate_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institution_name')->nullable(); // free text if not in taxonomy yet
            $table->string('degree', 160);                   // e.g. BSc Software Engineering
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_of_study', 160)->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->decimal('cgpa', 3, 2)->nullable();       // Ethiopian scale /4.00
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['candidate_profile_id', 'end_year']);
        });

        Schema::create('candidate_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('job_title', 160);
            $table->string('company', 160);
            $table->string('location', 160)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();            // null = current
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['candidate_profile_id', 'start_date']);
        });

        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(3); // 1..5 self-assessed proficiency
            $table->unsignedTinyInteger('years_using')->nullable();
            $table->string('source', 24)->default('manual');  // manual|cv_parsed|github
            $table->decimal('confidence', 3, 2)->default(1.00);
            $table->timestamps();
            $table->unique(['candidate_profile_id', 'skill_id']);
        });

        Schema::create('candidate_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('source', 24)->default('manual');  // manual|github|cv_parsed
            $table->jsonb('tech_stack')->nullable();
            $table->date('started_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('issuer', 160)->nullable();
            $table->string('credential_id', 160)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('language', 80);
            $table->enum('proficiency', ['basic', 'working', 'professional', 'native'])->default('working');
            $table->timestamps();
            $table->unique(['candidate_profile_id', 'language']);
        });

        Schema::create('candidate_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('work_modes')->default('[]');     // ["onsite","hybrid","remote"]
            $table->jsonb('locations')->default('[]');      // cities/regions
            $table->jsonb('employment_types')->default('[]');
            $table->jsonb('industries')->default('[]');
            $table->jsonb('job_category_ids')->default('[]');
            $table->unsignedBigInteger('salary_min_monthly_eth')->nullable(); // ETB
            $table->boolean('open_to_remote')->default(false);
            $table->boolean('willing_to_relocate')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'candidate_preferences', 'candidate_languages', 'candidate_certifications',
            'candidate_projects', 'candidate_skills', 'candidate_experiences',
            'candidate_educations', 'candidate_profiles',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
