<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\Course;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Lms\AssignmentService;
use App\Services\Lms\CourseService;
use App\Services\Lms\QuizService;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared fixtures for the Phase 7 LMS suites. */
abstract class Phase7TestCase extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected User $teacher;

    protected SchoolClass $class;

    protected CourseService $courses;

    protected QuizService $quizzes;

    protected AssignmentService $assignments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        $this->school = School::query()->create(['name' => 'Alpha School', 'slug' => 'alpha', 'status' => 'active', 'website_published' => true]);
        app(TenantContext::class)->set($this->school);
        $this->admin = $this->user('school_admin');
        $this->teacher = $this->user('teacher');
        $this->class = $this->classNamed('Grade 10A');
        $this->courses = app(CourseService::class);
        $this->quizzes = app(QuizService::class);
        $this->assignments = app(AssignmentService::class);
    }

    protected function user(string $role, ?School $school = null): User
    {
        $user = User::factory()->create(['school_id' => ($school ?? $this->school)->id, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    protected function classNamed(string $name, ?School $school = null): SchoolClass
    {
        $school ??= $this->school;
        $level = Level::query()->withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id, 'name' => 'Level '.$name], ['code' => 'L'.substr(md5($name), 0, 4), 'sort_order' => 1]);

        return SchoolClass::query()->withoutGlobalScopes()->create(['school_id' => $school->id, 'level_id' => $level->id, 'name' => $name, 'capacity' => 60]);
    }

    /** A learner with a login (role student) and a student record. @return array{0:User,1:Student} */
    protected function learner(?SchoolClass $class = null, string $first = 'Awa'): array
    {
        static $n = 0;
        $n++;
        $user = $this->user('student');
        $student = Student::query()->create([
            'school_id' => $this->school->id, 'user_id' => $user->id, 'student_number' => 'STU-L-'.$n, 'first_name' => $first.$n, 'last_name' => 'Jallow',
            'gender' => 'female', 'date_of_birth' => '2009-01-01', 'school_class_id' => ($class ?? $this->class)->id, 'status' => 'active',
        ]);

        return [$user, $student];
    }

    protected function course(array $over = []): Course
    {
        $this->actAsManager();

        return $this->courses->saveCourse($this->admin, null, $over + ['title' => 'Mathematics', 'status' => 'published', 'teacher_id' => $this->teacher->id]);
    }

    protected function actAsManager(): void
    {
        app(TenantContext::class)->set($this->school);
    }

    protected function enrol(Course $course, Student ...$students): void
    {
        $this->courses->enrol($this->admin, $course, array_map(fn ($s) => $s->id, $students));
    }

    protected function lesson(Course $course, array $over = []): Lesson
    {
        return $this->courses->saveLesson($this->admin, $course, null, $over + ['title' => 'Intro', 'content_type' => 'text', 'content' => 'Hello', 'status' => 'published']);
    }

    protected function assignment(Course $course, array $over = []): Assignment
    {
        return $this->assignments->save($this->admin, $course, null, $over + [
            'title' => 'Essay', 'max_score' => 10, 'status' => 'published', 'late_policy' => 'allow', 'max_attempts' => 1, 'accepts_text' => true, 'accepts_files' => false,
        ]);
    }

    protected function subject(string $name = 'Mathematics'): Subject
    {
        return Subject::query()->create(['school_id' => $this->school->id, 'name' => $name, 'code' => strtoupper(substr($name, 0, 3))]);
    }

    protected function allocate(User $teacher, Subject $subject, ?SchoolClass $class = null): void
    {
        ClassSubject::query()->create(['school_id' => $this->school->id, 'school_class_id' => ($class ?? $this->class)->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'periods_per_week' => 3]);
    }

    protected function otherSchool(string $slug = 'beta'): School
    {
        return School::query()->create(['name' => 'Beta School', 'slug' => $slug, 'status' => 'active', 'website_published' => true]);
    }

    /** Act as another user; the real middleware must bind the tenant itself. */
    protected function actAs(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user);
    }
}
