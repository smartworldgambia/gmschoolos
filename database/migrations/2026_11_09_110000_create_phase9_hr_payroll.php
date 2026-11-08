<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 9B - HR (positions, contracts, documents, performance) and configurable payroll with payslips. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'title']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $table->date('hired_on')->nullable();
            $table->date('exited_on')->nullable();
            $table->string('employment_type', 20)->nullable();           // permanent | contract | part_time | volunteer
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->text('bank_name')->nullable();                       // encrypted (cast) - payroll destination
            $table->text('bank_account')->nullable();                    // encrypted (cast)
        });

        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_type', 20);                         // permanent | fixed_term | part_time | probation
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('basic_salary')->default(0);         // per payroll period (the school pays monthly or as it configures)
            $table->string('status', 12)->default('active');             // active | ended | terminated
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'staff_id', 'status']);
        });

        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('type', 20);                                  // contract | id | certificate | cv | other
            $table->string('title', 190);
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('period_label', 40);
            $table->unsignedTinyInteger('rating');                       // 1..5
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('goals')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'staff_id']);
        });

        Schema::table('staff_leave_requests', function (Blueprint $table) {
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        // School-defined pay components. The platform ships NO tax, pension or social-security rates: the school (or its accountant)
        // enters whatever applies to it, and every figure on a payslip comes from these rows.
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 30);
            $table->string('kind', 12);                                  // allowance | deduction
            $table->string('calc', 20);                                  // fixed | percent_of_basic
            $table->decimal('value', 10, 2)->default(0);
            $table->string('applies_to', 10)->default('all');            // all | assigned
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('payroll_component_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->decimal('value_override', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['payroll_component_id', 'staff_id']);
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference', 120)->nullable();
            $table->string('notes', 500)->nullable();
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->unsignedInteger('gross_pay')->default(0);
            $table->json('lines')->nullable();                            // the payslip breakdown, frozen when the run is created
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', fn (Blueprint $t) => $t->dropColumn(['gross_pay', 'lines']));
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['paid_at', 'payment_reference', 'notes']);
        });
        Schema::dropIfExists('payroll_component_staff');
        Schema::dropIfExists('payroll_components');
        Schema::table('staff_leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applied_by');
            $table->dropColumn(['decision_note', 'cancelled_at']);
        });
        Schema::dropIfExists('performance_records');
        Schema::dropIfExists('staff_documents');
        Schema::dropIfExists('staff_contracts');
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('job_position_id');
            $table->dropColumn(['hired_on', 'exited_on', 'employment_type', 'emergency_contact_name', 'emergency_contact_phone', 'bank_name', 'bank_account']);
        });
        Schema::dropIfExists('job_positions');
    }
};
