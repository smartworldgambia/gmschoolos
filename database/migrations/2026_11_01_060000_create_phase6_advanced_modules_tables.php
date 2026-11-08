<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('leave_type'); // annual, sick, unpaid, other
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('days')->default(1);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('period_label'); // e.g. 2025-03
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft'); // draft, posted, paid
            $table->unsignedInteger('gross_total')->default(0);
            $table->unsignedInteger('net_total')->default(0);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'period_label']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedInteger('basic_salary')->default(0);
            $table->unsignedInteger('allowances')->default(0);
            $table->unsignedInteger('deductions')->default(0);
            $table->unsignedInteger('net_pay')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'staff_id']);
        });

        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('isbn')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('copies_total')->default(1);
            $table->unsignedInteger('copies_available')->default(1);
            $table->string('status')->default('available');
            $table->timestamps();
            $table->index(['school_id', 'title']);
        });

        Schema::create('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('loaned_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->string('status')->default('out'); // out, returned, overdue
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('plate_number')->nullable();
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('area')->nullable();
            $table->time('morning_pickup')->nullable();
            $table->time('afternoon_drop')->nullable();
            $table->unsignedInteger('fee_monthly')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('transport_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('stop_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['transport_route_id', 'student_id']);
        });

        Schema::create('boarding_hostels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gender')->nullable(); // male, female, mixed
            $table->string('warden_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('boarding_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boarding_hostel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->unsignedSmallInteger('occupied')->default(0);
            $table->string('status')->default('available');
            $table->timestamps();
        });

        Schema::create('boarding_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boarding_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['boarding_room_id', 'student_id', 'status']);
        });

        Schema::create('safeguarding_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->string('category'); // welfare, bullying, abuse_concern, other
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('open'); // open, investigating, closed
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_name')->nullable();
            $table->text('summary');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'reference']);
            $table->index(['school_id', 'status', 'severity']);
        });

        Schema::create('safeguarding_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('safeguarding_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_confidential')->default(true);
            $table->timestamps();
        });

        // Separate reporting store (OLAP-ish facts; laggy by design vs OLTP)
        Schema::create('report_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('fact_key'); // students.active, fees.collected, attendance.rate, ...
            $table->string('grain')->default('school_day'); // school_day, school_month, platform_day
            $table->date('captured_on');
            $table->json('dimensions')->nullable();
            $table->decimal('value_numeric', 14, 2)->nullable();
            $table->string('value_text')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'fact_key', 'grain', 'captured_on'], 'report_facts_unique');
            $table->index(['fact_key', 'captured_on']);
        });

        Schema::create('lti_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('client_id')->nullable();
            $table->string('deployment_id')->nullable();
            $table->string('launch_url');
            $table->string('auth_login_url')->nullable();
            $table->text('public_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('scorm_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('version')->default('1.2'); // 1.2, 2004
            $table->string('entry_point')->nullable();
            $table->string('package_path')->nullable();
            $table->string('status')->default('ready'); // uploading, ready, failed
            $table->json('manifest')->nullable();
            $table->timestamps();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('lti_tool_id')->nullable()->after('resource_path')->constrained('lti_tools')->nullOnDelete();
            $table->foreignId('scorm_package_id')->nullable()->after('lti_tool_id')->constrained('scorm_packages')->nullOnDelete();
            $table->string('external_launch_url')->nullable()->after('scorm_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lti_tool_id');
            $table->dropConstrainedForeignId('scorm_package_id');
            $table->dropColumn('external_launch_url');
        });

        Schema::dropIfExists('scorm_packages');
        Schema::dropIfExists('lti_tools');
        Schema::dropIfExists('report_facts');
        Schema::dropIfExists('safeguarding_notes');
        Schema::dropIfExists('safeguarding_cases');
        Schema::dropIfExists('boarding_allocations');
        Schema::dropIfExists('boarding_rooms');
        Schema::dropIfExists('boarding_hostels');
        Schema::dropIfExists('transport_assignments');
        Schema::dropIfExists('transport_routes');
        Schema::dropIfExists('transport_vehicles');
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('library_books');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('staff_leave_requests');
    }
};
