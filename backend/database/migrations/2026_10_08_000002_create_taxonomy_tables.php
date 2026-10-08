<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Ethiopian job taxonomy: skills (+aliases), industries, categories, roles,
 * education levels. Managed by admins; used by matching and search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            // Embedding vector lives in a dedicated column added conditionally
            // (pgvector optional in dev). See migration 000007.
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('skill_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->string('alias')->unique();
            $table->string('locale', 8)->nullable();
            $table->timestamps();
        });

        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('job_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('job_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('job_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->foreignId('job_category_id')->nullable()->constrained('job_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Rank lets us compare "required >= diploma" deterministically.
            $table->unsignedTinyInteger('rank');
            $table->timestamps();
        });

        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32)->default('university'); // university|college|school|training_center
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->default('Ethiopia');
            $table->string('website')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->unique(['name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('education_levels');
        Schema::dropIfExists('job_roles');
        Schema::dropIfExists('job_categories');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('skill_aliases');
        Schema::dropIfExists('skills');
    }
};
