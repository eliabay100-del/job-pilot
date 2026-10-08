<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies + employer accounts (Phase 9 foundation, needed early so jobs can exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('country', 120)->default('Ethiopia');
            $table->enum('size', ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'])->nullable();
            $table->enum('type', ['private', 'government', 'ngo', 'international_org', 'startup', 'quasi_government'])
                ->default('private');
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->jsonb('social_links')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['verification_status', 'country']);
        });

        Schema::create('company_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('membership_role', ['owner', 'admin', 'recruiter'])->default('recruiter');
            $table->enum('status', ['active', 'invited', 'revoked'])->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('company_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('license_document_path')->nullable();
            $table->string('business_license_number', 120)->nullable();
            $table->string('tax_identification_number', 120)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_verifications');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('companies');
    }
};
