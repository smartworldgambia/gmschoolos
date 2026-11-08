<?php

namespace App\Services\Lms;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;

/** Course analytics: enrolment, lesson completion, assignment completion, quiz attempts, grades and inactive learners. */
class ProgressService
{
    public function __construct(protected LmsAccess $access, protected GradebookService $gradebook) {}

    public static function inactiveDays(): int
    {
        return max(1, (int) config('schoolos.lms.inactive_days', 14));
    }

    /** @return array{summary:array<string,mixed>,rows:array<int,array<string,mixed>>} */
    public function course(Course $course): array
    {
        $days = self::inactiveDays();
        $cutoff = now()->subDays($days);

        $enrolments = CourseEnrolment::query()->where('course_id', $course->id)->get();
        $active = $enrolments->where('status', 'active');
        $students = Student::query()->with('schoolClass:id,name')->whereIn('id', $active->pluck('student_id'))->orderBy('first_name')->orderBy('last_name')->get();

        $lessons = $course->lessons()->get();
        $assignmentIds = Assignment::query()->where('course_id', $course->id)->where('status', 'published')->pluck('id');
        $quizIds = Quiz::query()->where('course_id', $course->id)->where('status', 'published')->pluck('id');
        $completions = LessonCompletion::query()->where('course_id', $course->id)->get()->groupBy('student_id');
        $submitted = AssignmentSubmission::query()->whereIn('assignment_id', $assignmentIds)->get()->groupBy('student_id');
        $attempts = QuizAttempt::query()->whereIn('quiz_id', $quizIds)->where('status', 'submitted')->get()->groupBy('student_id');
        $grades = collect($this->gradebook->course($course, false)['rows'])->keyBy(fn ($r) => $r['student']['id']);

        $rows = [];
        foreach ($students as $student) {
            $en = $active->firstWhere('student_id', $student->id);
            $visible = $lessons->filter(fn ($l) => $this->access->lessonVisible($l, $student));
            $done = ($completions->get($student->id) ?? collect())->whereIn('lesson_id', $visible->pluck('id'))->count();
            $subs = ($submitted->get($student->id) ?? collect())->pluck('assignment_id')->unique()->count();
            $last = $en->last_activity_at;
            $reference = $last ?? $en->enrolled_at;
            $rows[] = [
                'student' => ['id' => $student->id, 'name' => $student->full_name, 'number' => $student->student_number, 'class' => $student->schoolClass?->name],
                'lessons_done' => $done, 'lessons_total' => $visible->count(),
                'lesson_percent' => $visible->count() ? (int) round($done / $visible->count() * 100) : null,
                'assignments_done' => $subs, 'assignments_total' => $assignmentIds->count(),
                'quiz_attempts' => ($attempts->get($student->id) ?? collect())->count(),
                'grade_percent' => $grades->get($student->id)['percent'] ?? null,
                'last_activity' => $last?->toDateTimeString(),
                'inactive' => $reference === null || $reference->lt($cutoff),
            ];
        }

        $collection = collect($rows);

        return [
            'summary' => [
                'enrolled' => $active->count(), 'withdrawn' => $enrolments->where('status', 'withdrawn')->count(),
                'lessons' => $lessons->filter(fn ($l) => $l->isLive())->count(),
                'avg_lesson_percent' => $collection->whereNotNull('lesson_percent')->avg('lesson_percent') !== null ? (int) round($collection->whereNotNull('lesson_percent')->avg('lesson_percent')) : null,
                'assignment_completion' => $collection->sum('assignments_total') > 0 ? (int) round($collection->sum('assignments_done') / $collection->sum('assignments_total') * 100) : null,
                'quiz_attempts' => $collection->sum('quiz_attempts'),
                'avg_grade' => $collection->whereNotNull('grade_percent')->avg('grade_percent') !== null ? round($collection->whereNotNull('grade_percent')->avg('grade_percent'), 1) : null,
                'inactive' => $collection->where('inactive', true)->count(), 'inactive_days' => $days,
                'pending' => $this->gradebook->pending($course),
            ],
            'rows' => $rows,
        ];
    }
}
