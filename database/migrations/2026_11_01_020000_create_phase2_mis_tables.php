<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('building')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('room')->constrained()->nullOnDelete();
        });

        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('application_number');
            $table->string('status')->default('submitted'); // draft, submitted, under_review, accepted, rejected, enrolled
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('religion')->nullable();
            $table->foreignId('preferred_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_email')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'application_number']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // students
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft'); // draft, published, archived
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->foreignId('examination_id')->nullable()->after('term_id')->constrained()->nullOnDelete();
            $table->boolean('is_published')->default(false)->after('remark');
        });

        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->unsignedInteger('expected_amount')->default(0);
            $table->unsignedInteger('collected_amount')->default(0);
            $table->unsignedInteger('variance_amount')->default(0);
            $table->string('status')->default('open'); // open, matched, variance, closed
            $table->text('notes')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'reference']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_reconciliation_id')->nullable()->after('receipt_sent')->constrained()->nullOnDelete();
            $table->string('external_reference')->nullable()->after('method');
            $table->boolean('is_reconciled')->default(false)->after('receipt_sent');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_reconciliation_id');
            $table->dropColumn(['external_reference', 'is_reconciled']);
        });

        Schema::dropIfExists('payment_reconciliations');

        Schema::table('grades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('examination_id');
            $table->dropColumn('is_published');
        });

        Schema::dropIfExists('examinations');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('admission_applications');

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });

        Schema::dropIfExists('rooms');
    }
};
