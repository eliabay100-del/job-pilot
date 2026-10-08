<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI domain: conversations, usage accounting, credit ledger.
 * All AI accounting is server-side only (never trust the client).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 160)->nullable();     // career_assistant|interview_prep|job_analysis|...
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');
            $table->jsonb('citations')->nullable();   // platform data referenced (tool-based retrieval)
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 64);           // cv_parse|cv_tailor|cover_letter|chat|embedding|jd_improve|candidate_summary
            $table->string('provider', 32);
            $table->string('model', 80);
            $table->string('prompt_version', 40)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('credits_consumed')->default(0);
            $table->boolean('success')->default(true);
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->jsonb('context_refs')->nullable(); // ids of jobs/cvs used (auditability)
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['operation', 'created_at']);
        });

        Schema::create('ai_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 64);
            $table->unsignedInteger('credits_requested');
            $table->unsignedInteger('credits_charged')->default(0);
            $table->enum('status', ['reserved', 'committed', 'refunded', 'failed'])->default('reserved');
            $table->foreignId('ai_usage_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('ai_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('delta_credits');                    // +grant / -consume
            $table->string('reason', 64);                        // plan_monthly_grant|admin_grant|usage|refund
            $table->foreignId('ai_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('balance_after');         // denormalized for fast reads
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['ai_credit_ledger', 'ai_transactions', 'ai_usage', 'ai_messages', 'ai_conversations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
