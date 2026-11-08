<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 - academic and student management. ADDITIVE ONLY: no existing column is dropped or retyped and
 * no existing row is rewritten (legacy timetable slots stay published; legacy grades keep their values).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------- student records
        Schema::table('students', function (Blueprint $table) {
            $table->string('previous_school')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('emergency_contact_relationship', 80)->nullable();
            $table->text('medical_notes')->nullable();   // restricted: students.view_sensitive
            $table->text('welfare_notes')->nullable();   // restricted: students.view_sensitive
            $table->date('withdrawn_on')->nullable();
            $table->string('withdrawal_reason')->nullable();
        });

        // Per-school atomic counters (permanent student numbers).
        Schema::create('school_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('admission_application_id')->nullable()->constrained('admission_applications')->cascadeOnDelete();
            $table->string('type', 40); // birth_certificate, previous_report, transfer_letter, medical, photo, other
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'student_id']);
        });

        // History of every class / status movement. Rows are never deleted.
        Schema::create('student_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30); // enrolled, promoted, repeated, class_changed, withdrawn, transferred_out, graduated, reinstated
            $table->foreignId('from_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('to_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->date('effective_on');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'student_id']);
        });

        // ---------------------------------------------------------------- admissions
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->string('source', 20)->default('walk_in'); // online | walk_in
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->string('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('previous_school')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('submitted_ip', 45)->nullable();
        });

        // ---------------------------------------------------------------- academic structure
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
        });

        // Curriculum (which subjects a class studies) + subject-teacher allocation.
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('periods_per_week')->nullable();
            $table->timestamps();
            $table->unique(['school_class_id', 'subject_id']);
            $table->index(['school_id', 'teacher_id']);
        });

        // Holidays, examination periods, admission periods, results publication dates, events.
        Schema::create('academic_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // holiday, exam_period, admission_period, results_publication, event
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'starts_on']);
        });

        // ---------------------------------------------------------------- attendance
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('timetable_slot_id')->nullable()->constrained('timetable_slots')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->time('arrived_at')->nullable();
            $table->time('left_at')->nullable();
            $table->timestamp('guardian_notified_at')->nullable();
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('reason');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('attended_on');
            $table->string('status', 20); // present, absent, late, on_leave, excused
            $table->string('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'attended_on']);
            $table->index(['school_id', 'attended_on']);
        });

        // ---------------------------------------------------------------- timetable
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->boolean('is_published')->default(true); // legacy rows stay visible; new slots are created as drafts
            $table->timestamp('published_at')->nullable();
        });

        Schema::create('teacher_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'teacher_id']);
        });

        // ---------------------------------------------------------------- examinations and results
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('grading_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->string('letter', 10);
            $table->decimal('min_score', 5, 2);
            $table->string('remark')->nullable();
            $table->timestamps();
            $table->index(['grading_scale_id', 'min_score']);
        });

        Schema::create('assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('type', 10); // ca | exam
            $table->decimal('weight', 5, 2);     // contribution to the 0-100 total
            $table->decimal('max_score', 6, 2);  // marks a teacher enters out of
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('grade_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('assessment_category_id')->constrained('assessment_categories')->cascadeOnDelete();
            $table->decimal('score', 6, 2);
            $table->timestamps();
            $table->unique(['grade_id', 'assessment_category_id']);
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->string('type', 30)->default('end_of_term'); // continuous_assessment, mid_term, end_of_term, other
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('returned_reason')->nullable();
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->timestamp('revised_at')->nullable(); // set when a published result is corrected
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Append-only result-change history.
        Schema::create('grade_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignId('examination_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30); // entered, updated, corrected_after_publication
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'examination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_changes');

        Schema::table('grades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entered_by');
            $table->dropColumn('revised_at');
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['type', 'submitted_at', 'approved_at', 'returned_reason']);
        });

        Schema::dropIfExists('grade_scores');
        Schema::dropIfExists('assessment_categories');
        Schema::dropIfExists('grading_bands');
        Schema::dropIfExists('grading_scales');
        Schema::dropIfExists('teacher_unavailabilities');

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('period_id');
            $table->dropColumn(['is_published', 'published_at']);
        });

        Schema::dropIfExists('periods');
        Schema::dropIfExists('staff_attendance');
        Schema::dropIfExists('attendance_corrections');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timetable_slot_id');
            $table->dropColumn(['reason', 'arrived_at', 'left_at', 'guardian_notified_at']);
        });

        Schema::dropIfExists('academic_events');
        Schema::dropIfExists('class_subjects');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('head_user_id');
        });

        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn([
                'source', 'waitlist_position', 'decision_reason', 'decided_at', 'previous_school',
                'address', 'emergency_contact_name', 'emergency_contact_phone', 'submitted_ip',
            ]);
        });

        Schema::dropIfExists('student_movements');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('school_sequences');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'previous_school', 'address', 'emergency_contact_name', 'emergency_contact_phone',
                'emergency_contact_relationship', 'medical_notes', 'welfare_notes', 'withdrawn_on', 'withdrawal_reason',
            ]);
        });
    }
};
