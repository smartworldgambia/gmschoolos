<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 - payment workflows (additive only; nothing is dropped or rewritten).
 *
 *  - billing_payments        : evidence, rejection, gateway session, charged amount/currency/FX, lifecycle timestamps
 *  - billing_events          : optional link to the payment an audit entry belongs to
 *  - billing_webhook_events  : idempotency ledger for Stripe / Wave webhooks (unique provider + event id)
 *  - platform_bank_accounts  : bank details the Platform Administrator publishes to schools
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_payments', function (Blueprint $table) {
            $table->foreignId('initiated_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();

            // Gateway session (Stripe Checkout / Wave Checkout).
            $table->string('provider', 20)->nullable()->after('method');
            $table->string('provider_reference', 120)->nullable()->after('provider');
            $table->text('checkout_url')->nullable()->after('provider_reference');
            $table->timestamp('provider_confirmed_at')->nullable();

            // What the gateway actually charges (minor units of charged_currency) - never silently converted.
            $table->unsignedBigInteger('charged_amount')->nullable()->after('currency');
            $table->string('charged_currency', 3)->nullable()->after('charged_amount');
            $table->decimal('fx_rate', 18, 8)->nullable()->after('charged_currency');

            // Bank-transfer evidence (private disk). Evidence is NOT proof of payment.
            $table->string('evidence_path')->nullable();
            $table->string('evidence_original_name', 190)->nullable();
            $table->string('evidence_mime', 100)->nullable();
            $table->unsignedInteger('evidence_size')->nullable();
            $table->timestamp('evidence_uploaded_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->unique(['provider', 'provider_reference'], 'billing_payments_provider_ref_unique');
            $table->index(['status', 'expires_at'], 'billing_payments_status_expiry_index');
        });

        Schema::table('billing_events', function (Blueprint $table) {
            $table->unsignedBigInteger('billing_payment_id')->nullable()->after('school_subscription_id')->index();
        });

        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id', 120);
            $table->string('type', 80)->nullable();
            $table->unsignedBigInteger('billing_payment_id')->nullable()->index();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('status', 20)->default('received'); // received | processed | ignored | failed
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->string('payload_hash', 64)->nullable(); // sha256 of the raw body (the body itself is not stored)
            $table->text('error')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id'], 'billing_webhook_events_unique');
            $table->index(['provider', 'status']);
        });

        Schema::create('platform_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 120);
            $table->string('account_name', 160);
            $table->string('account_number', 60);
            $table->string('branch', 120)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('currency', 3)->default('GMD');
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_bank_accounts');
        Schema::dropIfExists('billing_webhook_events');

        Schema::table('billing_events', function (Blueprint $table) {
            $table->dropIndex(['billing_payment_id']);
            $table->dropColumn('billing_payment_id');
        });

        Schema::table('billing_payments', function (Blueprint $table) {
            $table->dropUnique('billing_payments_provider_ref_unique');
            $table->dropIndex('billing_payments_status_expiry_index');
            $table->dropConstrainedForeignId('initiated_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'provider', 'provider_reference', 'checkout_url', 'provider_confirmed_at',
                'charged_amount', 'charged_currency', 'fx_rate',
                'evidence_path', 'evidence_original_name', 'evidence_mime', 'evidence_size', 'evidence_uploaded_at',
                'rejection_reason', 'rejected_at', 'cancelled_at', 'failed_at', 'failure_reason', 'refunded_at', 'expires_at',
            ]);
        });
    }
};
