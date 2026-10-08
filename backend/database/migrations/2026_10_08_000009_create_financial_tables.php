<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financial platform data. Rules:
 * - Plans/prices live in the database (never hard-coded in the frontend).
 * - Money records are immutable: no destructive updates to transactions/invoices/ledger.
 * - Subscriptions activate ONLY from server-side verified payments/webhooks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // free|pro|career_premium|employer_basic|university
            $table->string('name');
            $table->enum('audience', ['candidate', 'employer', 'university']);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor')->default(0); // ETB cents (1 ETB = 100)
            $table->char('currency', 3)->default('ETB');
            $table->enum('billing_interval', ['monthly', 'yearly', 'once'])->default('monthly');
            $table->unsignedSmallInteger('ai_credits_per_period')->default(0);
            $table->unsignedSmallInteger('max_active_applications')->nullable(); // null = unlimited
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['audience', 'is_active']);
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');              // e.g. cv_tailoring, cover_letters, advanced_matching
            $table->jsonb('value')->nullable();         // bool or numeric limit
            $table->timestamps();
            $table->unique(['plan_id', 'feature_key']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();   // B2B
            $table->foreignId('university_profile_id')->nullable();                        // FK added in universities migration
            $table->enum('status', ['trialing', 'active', 'past_due', 'grace', 'expired', 'cancelled'])->default('active');
            $table->boolean('auto_renew')->default(false); // manual renewal is the safe Ethiopian default
            $table->timestamp('current_period_started_at');
            $table->timestamp('current_period_ends_at');
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'current_period_ends_at']);
        });

        Schema::create('subscription_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('ETB');
            $table->enum('status', ['open', 'paid', 'failed', 'waived', 'refunded'])->default('open');
            $table->timestamps();
            $table->unique(['subscription_id', 'cycle_number']);
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('reference_id')->unique();   // our reference sent to provider
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('ETB');
            $table->enum('status', ['pending', 'checkout', 'completed', 'failed', 'expired', 'cancelled'])->default('pending');
            $table->string('checkout_url', 600)->nullable();
            $table->string('payer_email')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // Immutable ledger-style record of verified money movements.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_reference', 190)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->enum('type', ['charge', 'refund'])->default('charge');
            $table->enum('status', ['pending', 'verified', 'failed']);
            $table->string('method', 40)->nullable();   // telebirr|chapa_card|bank_transfer|...
            $table->jsonb('verification_payload')->nullable(); // raw verified response (audit trail)
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['provider', 'provider_reference']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_type', 80);
            $table->string('idempotency_key')->unique();  // provider event id / hash of payload
            $table->jsonb('payload');
            $table->boolean('signature_valid')->default(false);
            $table->enum('status', ['received', 'processed', 'ignored_duplicate', 'rejected'])->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('vat_minor')->default(0); // Ethiopian VAT 15% when applicable
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('ETB');
            $table->enum('status', ['draft', 'issued', 'paid', 'void'])->default('issued');
            $table->string('bill_to_name');
            $table->string('bill_to_tin', 40)->nullable(); // Ethiopian Taxpayer Identification Number
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->enum('status', ['requested', 'approved', 'executed', 'rejected'])->default('requested');
            $table->text('reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');                       // cv_tailoring|cover_letters|advanced_matching|job_alerts|...
            $table->jsonb('value');                      // true/false or numeric quota
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'key']);
        });

        // B2B manual payment evidence (invoice/bank transfer flows).
        Schema::create('b2b_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('university_profile_id')->nullable(); // FK later
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('ETB');
            $table->string('evidence_path')->nullable();  // bank slip upload (private storage)
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected', 'refunded'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'b2b_payments', 'entitlements', 'refunds', 'invoices', 'webhook_events',
            'payment_transactions', 'payment_intents', 'subscription_cycles',
            'subscriptions', 'plan_features', 'plans',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
