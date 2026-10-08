<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications, connected accounts (Telegram/GitHub/Gmail), portfolios,
 * universities, reports, audit logs, analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- Notifications ----------
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);                 // job_alert|application_update|interview_reminder|payment_receipt|...
            $table->jsonb('data');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('channels')->default(json_encode(['in_app' => true, 'email' => true, 'telegram' => false]));
            $table->jsonb('categories')->default(json_encode(['job_alerts' => true, 'applications' => true, 'payments' => true, 'career_tips' => false]));
            $table->unsignedTinyInteger('frequency')->default(1); // 0 instant,1 daily digest,2 weekly
            $table->string('quiet_hours_start', 5)->nullable();   // "22:00"
            $table->string('quiet_hours_end', 5)->nullable();
            $table->timestamps();
        });

        // ---------- Connected accounts (OAuth-linked, tokens encrypted at rest) ----------
        Schema::create('connected_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['telegram', 'github', 'google_gmail']);
            $table->string('external_user_id', 190)->nullable();  // telegram chat id / github login id
            $table->string('username', 190)->nullable();
            $table->text('access_token_encrypted')->nullable();   // Crypt::encrypted string
            $table->text('refresh_token_encrypted')->nullable();
            $table->string('scopes', 600)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['connected', 'revoked'])->default('connected');
            $table->timestamp('verified_at')->nullable();          // e.g. Telegram ownership check completed
            $table->timestamps();
            $table->unique(['user_id', 'provider']);
        });

        // ---------- Portfolios ----------
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username_slug')->unique();     // platform.com/u/{slug}
            $table->boolean('is_public')->default(true);
            $table->string('theme', 40)->default('minimal');
            $table->jsonb('sections_order')->nullable();
            $table->unsignedInteger('visits_count')->default(0);
            $table->timestamps();
        });

        Schema::create('portfolio_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_project_id')->nullable()->constrained('candidate_projects')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });

        Schema::create('portfolio_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('url', 600);
            $table->string('kind', 30)->default('other'); // github|linkedin|website|telegram|other
            $table->timestamps();
        });

        Schema::create('portfolio_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('referrer', 300)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->timestamps();
            $table->index(['portfolio_id', 'created_at']);
        });

        Schema::create('github_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('github_username', 120);
            $table->string('github_user_id', 60);
            $table->jsonb('profile_snapshot')->nullable();
            $table->jsonb('repos_snapshot')->nullable();      // public repos metadata only by default
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id']);
        });

        // ---------- Universities ----------
        Schema::create('university_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('career_center_description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('website')->nullable();
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('university_profile_id')->references('id')->on('university_profiles')->nullOnDelete();
        });
        Schema::table('b2b_payments', function (Blueprint $table) {
            $table->foreign('university_profile_id')->references('id')->on('university_profiles')->nullOnDelete();
        });

        Schema::create('university_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('faculty', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('university_cohorts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('university_departments')->cascadeOnDelete();
            $table->string('name', 120);              // "SE 2017 E.C."
            $table->unsignedSmallInteger('graduation_year_ec')->nullable(); // Ethiopian Calendar year
            $table->timestamps();
        });

        // Membership links a student/graduate candidate to a cohort WITHOUT exposing private data.
        Schema::create('university_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained('university_cohorts')->cascadeOnDelete();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->enum('member_type', ['student', 'graduate'])->default('student');
            $table->enum('consent_status', ['pending', 'granted', 'declined'])->default('pending'); // privacy gate
            $table->timestamps();
            $table->unique(['cohort_id', 'candidate_profile_id']);
        });

        Schema::create('career_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->enum('kind', ['article', 'template', 'event', 'opportunity'])->default('article');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });

        // Aggregated employability outcomes (privacy-safe: no per-student disclosure).
        Schema::create('graduate_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_cohort_id')->constrained('university_cohorts')->cascadeOnDelete();
            $table->string('metric_key', 60);         // employed_6m | further_study | awaiting
            $table->unsignedInteger('count');
            $table->date('measured_on');
            $table->timestamps();
            $table->unique(['university_cohort_id', 'metric_key', 'measured_on']);
        });

        // ---------- Reports / moderation / risk ----------
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type', 60);    // job|company|user
            $table->unsignedBigInteger('reportable_id');
            $table->enum('reason', ['scam', 'fake', 'discriminatory', 'expired', 'duplicate', 'offensive', 'other']);
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'reviewing', 'resolved_valid', 'resolved_invalid'])->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['reportable_type', 'reportable_id', 'status']);
        });

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 40);       // company|job|user
            $table->unsignedBigInteger('subject_id');
            $table->unsignedTinyInteger('score');       // 0..100
            $table->enum('level', ['low', 'review', 'high']);
            $table->jsonb('factors');                  // which deterministic rules fired
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        // ---------- Audit + analytics ----------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type', 60)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 400)->nullable();
            $table->string('request_id', 40)->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60);              // registration, cv_uploaded, job_applied, ...
            $table->jsonb('properties')->nullable();
            $table->string('locale', 8)->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
        });

        // NOTE: job_source_records is created in migration ...000011 (job tables),
        // because it references job_sources and jobs, which are created there.
    }

    public function down(): void
    {
        foreach ([
            'analytics_events', 'audit_logs', 'risk_scores', 'reports',
            'graduate_outcomes', 'career_resources', 'university_memberships', 'university_cohorts',
            'university_departments', 'b2b_payment_fk_placeholder_none',
        ] as $t) {
            if ($t !== 'b2b_payment_fk_placeholder_none') {
                Schema::dropIfExists($t);
            }
        }
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropForeign(['university_profile_id']));
        Schema::table('b2b_payments', fn (Blueprint $t) => $t->dropForeign(['university_profile_id']));
        Schema::dropIfExists('university_profiles');
        Schema::dropIfExists('github_connections');
        Schema::dropIfExists('portfolio_visits');
        Schema::dropIfExists('portfolio_links');
        Schema::dropIfExists('portfolio_projects');
        Schema::dropIfExists('portfolios');
        Schema::dropIfExists('connected_accounts');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
