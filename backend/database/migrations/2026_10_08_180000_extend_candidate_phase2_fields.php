<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('display_name', 120)->nullable()->after('user_id');
            $table->string('current_job_title', 160)->nullable()->after('years_experience');
            $table->string('current_company', 160)->nullable()->after('current_job_title');
            $table->string('preferred_job_title', 160)->nullable()->after('current_company');
            $table->string('preferred_location', 160)->nullable()->after('preferred_job_title');
            $table->enum('remote_preference', ['onsite', 'hybrid', 'remote', 'flexible'])->default('flexible')->after('preferred_location');
            $table->enum('employment_type_preference', ['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'])->nullable()->after('remote_preference');
            $table->unsignedBigInteger('expected_salary_monthly_etb')->nullable()->after('employment_type_preference');
            $table->string('availability', 120)->nullable()->after('expected_salary_monthly_etb');
        });

        Schema::table('candidate_educations', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('field_of_study');
            $table->date('end_date')->nullable()->after('start_date');
            $table->boolean('currently_studying')->default(false)->after('end_date');
            $table->string('grade', 40)->nullable()->after('cgpa');
            $table->text('description')->nullable()->after('notes');
            $table->jsonb('achievements')->nullable()->after('description');
        });

        Schema::table('candidate_experiences', function (Blueprint $table) {
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship', 'temporary', 'freelance'])->nullable()->after('company');
            $table->text('achievements')->nullable()->after('description');
            $table->text('responsibilities')->nullable()->after('achievements');
        });

        Schema::table('candidate_projects', function (Blueprint $table) {
            $table->string('role', 160)->nullable()->after('description');
            $table->string('repository_url')->nullable()->after('url');
            $table->text('achievements')->nullable()->after('completed_at');
        });

        Schema::table('candidate_languages', function (Blueprint $table) {
            $table->enum('speaking_level', ['basic', 'working', 'professional', 'native'])->nullable()->after('proficiency');
            $table->enum('listening_level', ['basic', 'working', 'professional', 'native'])->nullable()->after('speaking_level');
            $table->enum('reading_level', ['basic', 'working', 'professional', 'native'])->nullable()->after('listening_level');
            $table->enum('writing_level', ['basic', 'working', 'professional', 'native'])->nullable()->after('reading_level');
        });

        Schema::table('candidate_preferences', function (Blueprint $table) {
            $table->jsonb('desired_job_role_ids')->default('[]')->after('industries');
            $table->jsonb('desired_industry_ids')->default('[]')->after('desired_job_role_ids');
            $table->string('experience_level', 32)->nullable()->after('salary_min_monthly_eth');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_preferences', function (Blueprint $table) {
            $table->dropColumn(['desired_job_role_ids', 'desired_industry_ids', 'experience_level']);
        });

        Schema::table('candidate_languages', function (Blueprint $table) {
            $table->dropColumn(['speaking_level', 'listening_level', 'reading_level', 'writing_level']);
        });

        Schema::table('candidate_projects', function (Blueprint $table) {
            $table->dropColumn(['role', 'repository_url', 'achievements']);
        });

        Schema::table('candidate_experiences', function (Blueprint $table) {
            $table->dropColumn(['employment_type', 'achievements', 'responsibilities']);
        });

        Schema::table('candidate_educations', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'currently_studying', 'grade', 'description', 'achievements']);
        });

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'current_job_title',
                'current_company',
                'preferred_job_title',
                'preferred_location',
                'remote_preference',
                'employment_type_preference',
                'expected_salary_monthly_etb',
                'availability',
            ]);
        });
    }
};
