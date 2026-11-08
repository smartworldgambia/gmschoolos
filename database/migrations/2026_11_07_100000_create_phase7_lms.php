<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 - native LMS. Extends the thin Phase 3 tables (courses, lessons, assignments, quizzes) and adds
 * enrolment, materials, submissions, question banks, announcements, discussions and teacher feedback.
 * Every table is tenant-owned (school_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('subject_id')->constrained('course_categories')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('objectives')->nullable();
        });

        Schema::table('course_sections', function (Blueprint $table) {
            $table->text('description')->nullable();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->string('status', 20)->default('published');
            $table->timestamp('publish_at')->nullable();
            $table->json('objectives')->nullable();
            $table->json('audience_class_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->string('status', 20)->default('published');
            $table->timestamp('publish_at')->nullable();
            $table->string('late_policy', 10)->default('allow'); // allow (flagged late) | block
            $table->timestamp('closes_at')->nullable();           // hard cut-off, regardless of policy
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('accepts_text')->default(true);
            $table->boolean('accepts_files')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('status', 20)->default('published');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->string('release_mode', 20)->default('immediate'); // immediate | after_close | scheduled | manual
            $table->timestamp('release_at')->nullable();
            $table->timestamp('results_released_at')->nullable();
            $table->boolean('show_correct_answers')->default(true);
            $table->string('grade_method', 10)->default('highest');   // highest | latest | average
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->json('accepted_answers')->nullable();
            $table->boolean('case_sensitive')->default(false);
            $table->text('explanation')->nullable();
            $table->unsignedBigInteger('quiz_attempt_id')->nullable()->index(); // snapshot of a randomly drawn bank question
            $table->unsignedBigInteger('bank_question_id')->nullable();
        });
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->text('prompt')->change();
            $table->string('correct_answer', 500)->nullable()->change(); // essays have no key
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->timestamp('due_at')->nullable();
            $table->json('question_order')->nullable();
            $table->json('option_orders')->nullable();
            $table->string('grading_status', 20)->default('graded'); // graded | needs_grading
            $table->boolean('timed_out')->default(false);
            $table->timestamp('graded_at')->nullable();
        });
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->decimal('score', 7, 2)->nullable()->change();
            $table->decimal('max_score', 7, 2)->nullable()->change();
        });

        Schema::table('quiz_attempt_answers', function (Blueprint $table) {
            $table->boolean('needs_grading')->default(false);
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
        });
        Schema::table('quiz_attempt_answers', function (Blueprint $table) {
            $table->text('answer')->nullable()->change();
            $table->decimal('points_awarded', 7, 2)->default(0)->change();
        });

        Schema::create('course_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'school_class_id']);
        });

        Schema::create('course_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'user_id']);
        });

        Schema::create('course_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('active'); // active | withdrawn
            $table->string('source', 12)->default('manual'); // manual | class
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'student_id']);
            $table->index(['school_id', 'student_id', 'status']);
        });

        Schema::create('lms_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('owner_type', 20);       // lesson | assignment | submission
            $table->unsignedBigInteger('owner_id');
            $table->string('type', 12)->default('file'); // file | document | video | link
            $table->string('title', 190)->nullable();
            $table->string('url', 500)->nullable();      // link / hosted video (https only)
            $table->string('path')->nullable();          // private disk, random name
            $table->string('original_name', 190)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->text('body')->nullable();
            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->string('status', 12)->default('submitted'); // submitted | graded | returned
            $table->decimal('score', 7, 2)->nullable();
            $table->json('rubric_scores')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'student_id', 'attempt_no'], 'submission_attempt_unique');
        });

        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 190);
            $table->boolean('is_shared')->default(false); // visible to every teacher of the school
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // multiple_choice | true_false | short_answer | essay
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->string('correct_answer', 500)->nullable();
            $table->json('accepted_answers')->nullable();
            $table->boolean('case_sensitive')->default(false);
            $table->unsignedSmallInteger('points')->default(1);
            $table->text('explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_bank_pulls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('count');
            $table->timestamps();
            $table->unique(['quiz_id', 'question_bank_id']);
        });

        Schema::create('course_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 190);
            $table->text('body');
            $table->timestamp('publish_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'publish_at']);
        });

        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 12)->default('discussion'); // discussion | question
            $table->string('title', 190);
            $table->text('body');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->unsignedBigInteger('answer_post_id')->nullable();
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'last_post_at']);
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forum_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('course_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->unique(['course_id', 'student_id']);
        });
    }

    public function down(): void
    {
        foreach (['course_feedback', 'forum_posts', 'forum_threads', 'course_announcements', 'quiz_bank_pulls', 'bank_questions',
            'question_banks', 'assignment_submissions', 'lms_files', 'course_enrolments', 'course_teachers', 'course_audiences'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
