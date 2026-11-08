<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('max_students')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->unsignedInteger('max_exports_per_day')->nullable();
            $table->unsignedInteger('max_api_requests_per_hour')->nullable();
            $table->boolean('is_premium_isolated')->default(false);
            $table->json('overrides')->nullable();
            $table->timestamps();
            $table->unique('school_id');
        });

        Schema::create('usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('captured_on');
            $table->unsignedInteger('students_count')->default(0);
            $table->unsignedInteger('staff_count')->default(0);
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('courses_count')->default(0);
            $table->unsignedInteger('storage_mb')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('export_count')->default(0);
            $table->unsignedInteger('import_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'captured_on']);
            $table->index('captured_on');
        });

        Schema::create('platform_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('severity')->default('warning'); // info, warning, critical
            $table->string('source'); // health, quota, queue, backup, billing
            $table->string('title');
            $table->text('message');
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['severity', 'resolved_at']);
        });

        Schema::create('backup_validation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status'); // passed, failed, skipped
            $table->string('trigger')->default('manual'); // manual, scheduled
            $table->foreignId('ran_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('checks')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->string('billing_email')->nullable()->after('email');
        });

        Schema::table('school_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('amount_monthly')->nullable()->after('status');
            $table->string('billing_cycle')->default('monthly')->after('amount_monthly');
            $table->timestamp('last_invoiced_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('school_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['amount_monthly', 'billing_cycle', 'last_invoiced_at']);
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'billing_email']);
        });

        Schema::dropIfExists('backup_validation_runs');
        Schema::dropIfExists('platform_alerts');
        Schema::dropIfExists('usage_snapshots');
        Schema::dropIfExists('school_quotas');
    }
};
