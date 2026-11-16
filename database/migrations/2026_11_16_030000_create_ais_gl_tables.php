<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant AIS (Accounting Information System): chart of accounts, journals, lines, maps, period locks.
 * All tables are school-scoped. Amounts are integer GMD (same as fees/payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('type', 20); // asset|liability|equity|income|expense
            $table->string('subtype', 32)->nullable(); // cash|bank|receivable|payable|fee_income|payroll|cogs|other
            $table->foreignId('parent_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'type']);
        });

        Schema::create('gl_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->date('date');
            $table->string('memo')->nullable();
            $table->string('status', 20)->default('draft'); // draft|posted|reversed
            $table->string('source', 32)->default('manual');
            $table->nullableMorphs('source_ref'); // source_ref_type + source_ref_id
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reverses_journal_id')->nullable()->constrained('gl_journals')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'number']);
            $table->index(['school_id', 'status', 'date']);
            $table->index(['school_id', 'source', 'source_ref_id']);
        });

        Schema::create('gl_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('gl_journals')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->unsignedInteger('debit')->default(0);
            $table->unsignedInteger('credit')->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'account_id']);
        });

        Schema::create('gl_account_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40); // cash|bank|fees_receivable|fee_income|discounts|payroll_expense|salaries_payable|ap|inventory
            $table->foreignId('account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'key']);
        });

        Schema::create('gl_period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7); // YYYY-MM
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_period_locks');
        Schema::dropIfExists('gl_account_maps');
        Schema::dropIfExists('gl_lines');
        Schema::dropIfExists('gl_journals');
        Schema::dropIfExists('gl_accounts');
    }
};
