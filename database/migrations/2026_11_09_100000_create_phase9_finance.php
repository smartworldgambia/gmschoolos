<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9A - school fees and finance (parents pay the SCHOOL; this is separate from the SaaS subscription tables).
 * Money is stored as whole Dalasi (unsigned integers), exactly like the existing invoices and payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 30)->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        // One line of the fee schedule: a category, an amount, and where it applies (year, optional term, optional class).
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->unsignedInteger('amount');
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['school_id', 'academic_year_id', 'term_id']);
        });

        // A charge for one learner only (a trip, a lost book, transport or boarding for the term...).
        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 190);
            $table->unsignedInteger('amount');
            $table->string('status', 12)->default('pending');   // pending | invoiced | cancelled
            $table->string('source', 80)->nullable();           // e.g. transport:3:12 / boarding:2:12 keeps automatic billing idempotent
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'student_id', 'status']);
            $table->unique(['student_id', 'source']);
        });

        // Discounts, scholarships and waivers need a second person's approval before they touch an invoice.
        Schema::create('fee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                          // discount | scholarship | waiver
            $table->string('basis', 8);                          // percent | fixed
            $table->unsignedInteger('value');
            $table->foreignId('fee_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->string('status', 12)->default('pending');    // pending | approved | rejected | revoked
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 12)->default('manual');     // manual | structure
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('adjustments_total')->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedSmallInteger('reminder_count')->default(0);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('kind', 12)->default('fee');          // fee | discount | scholarship | waiver
            $table->foreignId('fee_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_fee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_adjustment_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_on');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('amount_paid')->default(0);
            $table->timestamps();
            $table->unique(['invoice_id', 'number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 12)->default('confirmed');  // pending | confirmed | rejected | voided
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payer_name', 150)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->index(['school_id', 'status']);
        });

        // The SCHOOL's own ways of collecting fees (shown to parents). Never mixes with the platform's SaaS billing credentials,
        // and holds no gateway secrets: payments are confirmed by a person.
        Schema::create('school_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);                        // cash | wave | bank_transfer | other
            $table->string('label', 120);
            $table->text('instructions')->nullable();
            $table->string('account_name', 150)->nullable();
            $table->string('account_number', 80)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_payment_methods');

        Schema::table('payments', function (Blueprint $table) {
            foreach (['submitted_by', 'decided_by', 'voided_by'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropIndex(['school_id', 'status']);
            $table->dropColumn(['status', 'payer_name', 'notes', 'evidence_path', 'decided_at', 'decision_note', 'voided_at', 'void_reason']);
        });

        Schema::dropIfExists('invoice_installments');

        Schema::table('invoice_items', function (Blueprint $table) {
            foreach (['fee_category_id', 'fee_structure_id', 'student_fee_id', 'fee_adjustment_id'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn('kind');
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['academic_year_id', 'term_id', 'cancelled_by', 'created_by'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['source', 'subtotal', 'adjustments_total', 'notes', 'cancelled_at', 'cancel_reason', 'reminded_at', 'reminder_count']);
        });

        Schema::dropIfExists('fee_adjustments');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_categories');
    }
};
