<?php

namespace App\Services\Lms;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseTeacher;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may do what in a course. FAIL-CLOSED:
 *
 *  - manager : holds `lms.manage` (every course of the school);
 *  - teacher : holds `lms.teach` AND is the lead or a co-teacher of THIS course;
 *  - learner : a learner-portal user whose student record has an ACTIVE enrolment in a PUBLISHED course;
 *  - anyone else (other staff, guardians, un-enrolled students) gets nothing.
 *
 * Tenant isolation comes from the tenant-scoped models; another school's course never resolves.
 */
class LmsAccess
{
    public const MANAGER = 'manager';

    public const TEACHER = 'teacher';

    public const LEARNER = 'learner';

    /** The learner record of a user, or null. Administrators and teachers are never treated as learners. */
    public function student(User $user): ?Student
    {
        return $user->isLearnerPortal() ? $user->studentProfile : null;
    }

    public function enrolment(Course $course, Student $student): ?CourseEnrolment
    {
        return CourseEnrolment::query()->where('course_id', $course->id)->where('student_id', $student->id)->where('status', 'active')->first();
    }

    public function roleFor(User $user, Course $course): ?string
    {
        if ($user->can('lms.manage')) {
            return self::MANAGER;
        }
        if ($user->can('lms.teach') && $this->teachesCourse($user, $course)) {
            return self::TEACHER;
        }
        $student = $this->student($user);
        if ($student && $course->status === 'published' && $this->enrolment($course, $student)) {
            return self::LEARNER;
        }

        return null;
    }

    public function teachesCourse(User $user, Course $course): bool
    {
        return (int) $course->teacher_id === (int) $user->id
            || CourseTeacher::query()->where('course_id', $course->id)->where('user_id', $user->id)->exists();
    }

    /** Abort 403 unless the user has one of the roles; returns the role. */
    public function authorize(User $user, Course $course, array $roles = [self::MANAGER, self::TEACHER, self::LEARNER]): string
    {
        $role = $this->roleFor($user, $course);
        abort_unless($role !== null && in_array($role, $roles, true), 403);

        return $role;
    }

    public static function authors(?string $role): bool
    {
        return in_array($role, [self::MANAGER, self::TEACHER], true);
    }

    // ------------------------------------------------------------------ learner visibility

    public function lessonVisible(Lesson $lesson, Student $student): bool
    {
        if (! $lesson->isLive()) {
            return false;
        }
        $classes = array_map('intval', (array) ($lesson->audience_class_ids ?? []));

        return $classes === [] || in_array((int) $student->school_class_id, $classes, true);
    }

    /** @return Collection<int,Lesson> */
    public function visibleLessons(Course $course, Student $student): Collection
    {
        return $course->lessons()->get()->filter(fn (Lesson $l) => $this->lessonVisible($l, $student))->values();
    }

    public function assignmentVisible(Assignment $assignment): bool
    {
        return $assignment->isLive();
    }

    public function quizVisible(Quiz $quiz): bool
    {
        return $quiz->isPublished();
    }

    /** Record that a learner did something (inactivity analytics). */
    public function touch(Course $course, Student $student): void
    {
        CourseEnrolment::query()->where('course_id', $course->id)->where('student_id', $student->id)->update(['last_activity_at' => now()]);
    }
}
