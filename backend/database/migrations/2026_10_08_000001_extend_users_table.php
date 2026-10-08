<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform data: users table extensions.
 *
 * Role model (RBAC): one primary role per user account, enforced server-side
 * via policies/middleware. See docs/SECURITY.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('locale', 8)->default('en')->after('password');
            $table->enum('role', [
                'job_seeker',
                'employer_admin',
                'employer_recruiter',
                'university_admin',
                'university_staff',
                'moderator',
                'admin',
                'super_admin',
            ])->default('job_seeker')->after('locale');
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active')->after('role');
            $table->timestamp('last_seen_at')->nullable()->after('status');
            $table->softDeletes();

            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'locale', 'role', 'status', 'last_seen_at']);
        });
    }
};
