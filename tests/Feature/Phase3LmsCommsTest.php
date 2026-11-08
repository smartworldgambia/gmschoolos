<?php

namespace Tests\Feature;

use App\Jobs\ProcessStudentExportBatch;
use App\Models\Course;
use App\Models\ExportBatch;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase3LmsCommsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $studentUser;

    protected Student $student;

    protected Course $course;

    protected Lesson $lesson;

    protected Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->school = School::query()->create([
            'name' => 'LMS School',
            'slug' => 'lms-school',
            'status' => 'active',
        ]);
        app(TenantContext::class)->set($this->school);

        $this->studentUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $this->studentUser->assignRole('student');

        $this->student = Student::query()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->studentUser->id,
            'student_number' => 'LMS-1',
            'first_name' => 'Amadou',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $this->course = Course::query()->create([
            'school_id' => $this->school->id,
            'title' => 'Math',
            'code' => 'M1',
            'status' => 'published',
        ]);

        $this->lesson = Lesson::query()->create([
            'school_id' => $this->school->id,
            'course_id' => $this->course->id,
            'title' => 'Intro',
            'content_type' => 'text',
            'content' => 'Hello',
            'sort_order' => 1,
        ]);

        $this->quiz = Quiz::query()->create([
            'school_id' => $this->school->id,
            'course_id' => $this->course->id,
            'title' => 'Check',
            'max_score' => 2,
            'status' => 'published',
        ]);

        QuizQuestion::query()->create([
            'school_id' => $this->school->id,
            'quiz_id' => $this->quiz->id,
            'prompt' => '2+2?',
            'type' => 'multiple_choice',
            'options' => ['3', '4', '5'],
            'correct_answer' => '4',
            'points' => 2,
            'sort_order' => 1,
        ]);

        // Phase 7: learners reach a course only through an active enrolment in a published course.
        \App\Models\CourseEnrolment::query()->create(['school_id' => $this->school->id, 'course_id' => $this->course->id, 'student_id' => $this->student->id, 'status' => 'active', 'source' => 'manual', 'enrolled_at' => now()]);
    }

    public function test_student_can_mark_lesson_complete_and_progress_updates(): void
    {
        $this->actingAs($this->studentUser)
            ->post(route('lms.lessons.complete', $this->lesson))
            ->assertRedirect();

        $this->assertDatabaseHas('lesson_completions', [
            'lesson_id' => $this->lesson->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->studentUser)
            ->get(route('lms.courses.show', $this->course))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lms/CourseShow')
                ->where('progressPercent', 100)
            );
    }

    public function test_student_can_submit_quiz_attempt(): void
    {
        $question = QuizQuestion::query()->first();

        $this->actingAs($this->studentUser)
            ->post(route('lms.quizzes.start', $this->quiz))
            ->assertRedirect();

        $attempt = QuizAttempt::query()->first();
        $this->assertSame('in_progress', $attempt->status);

        $this->actingAs($this->studentUser)
            ->post(route('lms.attempts.submit', $attempt), [
                'answers' => [$question->id => '4'],
            ])
            ->assertRedirect(route('lms.quizzes.show', $this->quiz));

        $attempt = QuizAttempt::query()->first();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(2, (int) $attempt->score);
    }

    public function test_export_is_queued_for_admin(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        $this->actingAs($admin)
            ->post(route('exports.store'), ['type' => 'students'])
            ->assertRedirect();

        Queue::assertPushed(ProcessStudentExportBatch::class);
        $this->assertDatabaseHas('export_batches', [
            'school_id' => $this->school->id,
            'type' => 'students',
            'status' => 'pending',
        ]);
    }

    public function test_user_receives_database_notification(): void
    {
        Notification::fake();

        $this->studentUser->notify(new SchoolAnnouncement(
            'Hello',
            'Body text',
            '/dashboard',
            'info'
        ));

        Notification::assertSentTo($this->studentUser, SchoolAnnouncement::class);
    }
}
