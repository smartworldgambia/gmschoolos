<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: annual-only subscription billing.
 *
 *  - school_type_prices      : one configurable annual price per school type, with history (append-only)
 *  - school_subscriptions    : lifecycle fields (trial / active / expired / suspended), exactly one per school
 *  - billing_payments        : annual subscription payments (NOT school fees paid by parents)
 *  - billing_events          : audit trail of every billing state change
 *  - billing_notifications   : reminder ledger (dedupe + delivery status)
 *
 * No data is deleted. Legacy monthly columns (amount_monthly, billing_cycle default) are left in place
 * but no longer written; every existing subscription is normalised to the annual cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_type_prices', function (Blueprint $table) {
            $table->id();
            $table->string('school_type', 40);
            $table->unsignedBigInteger('amount'); // whole currency units (GMD)
            $table->string('currency', 3)->default('GMD');
            $table->decimal('tax_rate', 5, 2)->nullable(); // optional tax, percent
            $table->date('effective_from');
            $table->string('status', 20)->default('active'); // active | inactive
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_type', 'status', 'effective_from']);
        });

        Schema::table('school_subscriptions', function (Blueprint $table) {
            $table->string('school_type', 40)->nullable()->after('subscription_plan_id');
            $table->unsignedBigInteger('annual_amount')->nullable()->after('school_type');
            $table->string('currency', 3)->default('GMD')->after('annual_amount');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('currency');
            $table->string('payment_status', 40)->default('not_required_during_trial')->after('status');
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('reactivated_at')->nullable();
        });

        // Tiered plans are retired: a subscription no longer points at a plan.
        Schema::table('school_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_plan_id')->nullable()->change();
        });

        $this->normaliseExistingSubscriptions();

        // Exactly one subscription per school. Only add the constraint when the data already satisfies it.
        $duplicates = DB::table('school_subscriptions')
            ->select('school_id')
            ->groupBy('school_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicates === 0) {
            Schema::table('school_subscriptions', function (Blueprint $table) {
                $table->unique('school_id', 'school_subscriptions_one_per_school');
            });
        }

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_subscription_id')->nullable()->constrained('school_subscriptions')->nullOnDelete();
            $table->string('reference', 60)->unique();
            $table->string('method', 30); // cash | bank_transfer | wave | stripe
            $table->unsignedBigInteger('amount'); // subtotal for the annual period (price snapshot)
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount'); // amount + tax actually due/paid
            $table->string('currency', 3)->default('GMD');
            // pending | pending_verification | verified | rejected | failed | refunded
            $table->string('status', 30)->default('pending_verification');
            $table->string('school_type', 40)->nullable();
            $table->string('transaction_reference', 120)->nullable();
            $table->string('receipt_number', 60)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
            $table->index(['method', 'status']);
            $table->unique(['method', 'transaction_reference'], 'billing_payments_method_txn_unique');
        });

        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_subscription_id')->nullable()->constrained('school_subscriptions')->nullOnDelete();
            $table->string('type', 60);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['school_id', 'type']);
        });

        Schema::create('billing_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_subscription_id')->constrained('school_subscriptions')->cascadeOnDelete();
            $table->string('kind', 60);       // e.g. trial_7, paid_30, trial_expired, suspended, activated
            $table->string('period_key', 40); // the expiry date the reminder relates to
            $table->string('status', 20)->default('queued'); // queued | sent | failed
            $table->unsignedSmallInteger('recipients')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['school_subscription_id', 'kind', 'period_key'], 'billing_notifications_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_notifications');
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('billing_payments');

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS school_subscriptions_one_per_school');
        } elseif (Schema::hasIndex('school_subscriptions', 'school_subscriptions_one_per_school')) {
            Schema::table('school_subscriptions', function (Blueprint $table) {
                $table->dropUnique('school_subscriptions_one_per_school');
            });
        }

        Schema::table('school_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'school_type', 'annual_amount', 'currency', 'tax_rate', 'payment_status',
                'trial_starts_at', 'trial_ends_at', 'current_period_start', 'current_period_end',
                'renews_at', 'activated_at', 'expired_at', 'suspended_at', 'reactivated_at',
            ]);
        });

        Schema::dropIfExists('school_type_prices');
    }

    /**
     * Existing rows: legacy statuses become lifecycle statuses, every cycle becomes annual.
     */
    private function normaliseExistingSubscriptions(): void
    {
        $known = ['nursery', 'primary', 'basic_secondary', 'senior_secondary', 'combined', 'international', 'other'];

        foreach (DB::table('school_subscriptions')->get() as $row) {
            $category = DB::table('schools')->where('id', $row->school_id)->value('category');
            $type = in_array($category, $known, true) ? $category : 'other';

            $status = match ($row->status) {
                'trialing', 'trial' => 'trial',
                'active' => 'active',
                'past_due' => 'expired',
                'cancelled', 'canceled' => 'cancelled',
                default => $row->status,
            };

            $update = [
                'school_type' => $type,
                'status' => $status,
                'billing_cycle' => 'annual',
            ];

            if ($status === 'trial') {
                $update['payment_status'] = 'not_required_during_trial';
                $update['trial_starts_at'] = $row->starts_at;
                $update['trial_ends_at'] = $row->ends_at;
            } elseif ($status === 'active') {
                $update['payment_status'] = 'paid';
                $update['activated_at'] = $row->starts_at;
                $update['current_period_start'] = $row->starts_at;
                $update['current_period_end'] = $row->ends_at;
                $update['renews_at'] = $row->ends_at;
            } else {
                $update['payment_status'] = 'unpaid';
                $update['expired_at'] = $row->ends_at;
            }

            DB::table('school_subscriptions')->where('id', $row->id)->update($update);
        }
    }
};
