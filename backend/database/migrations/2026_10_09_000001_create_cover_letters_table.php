<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cover_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_cv_version_id')->nullable()->constrained('cv_versions')->nullOnDelete();
            $table->longText('content');
            $table->string('status', 24)->default('draft');
            $table->string('provider', 32);
            $table->string('model', 80);
            $table->string('prompt_version', 40);
            $table->json('context_refs')->nullable();
            $table->timestamps();
            $table->index(['candidate_profile_id', 'created_at']);
            $table->index(['job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_letters');
    }
};