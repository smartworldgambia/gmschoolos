<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name');
            $table->string('program_band', 40)->default('default');
            $table->json('config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'key']);
        });

        Schema::create('academic_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // termly | annual | final
            $table->string('status', 20)->default('draft');
            $table->string('reference', 40);
            $table->string('verification_token', 32);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('academic_reports')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('academic_report_templates')->nullOnDelete();
            $table->json('payload');
            $table->decimal('overall_average', 6, 2)->nullable();
            $table->string('overall_grade', 8)->nullable();
            $table->boolean('incomplete')->default(false);
            $table->text('class_teacher_comment')->nullable();
            $table->text('headteacher_comment')->nullable();
            $table->string('behaviour', 40)->nullable();
            $table->string('participation', 40)->nullable();
            $table->string('promotion_status', 40)->nullable();
            $table->string('promotion_decision', 40)->nullable();
            $table->boolean('allow_incomplete')->default(false);
            $table->string('revision_reason')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->date('issue_date')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'reference']);
            $table->unique(['verification_token']);
            $table->index(['school_id', 'type', 'status']);
            $table->index(['school_id', 'student_id', 'type', 'academic_year_id', 'term_id'], 'academic_reports_student_period_index');
        });

        Schema::create('academic_report_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_report_id')->constrained('academic_reports')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('comment', 500)->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['academic_report_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_report_events');
        Schema::dropIfExists('academic_reports');
        Schema::dropIfExists('academic_report_templates');
    }
};
