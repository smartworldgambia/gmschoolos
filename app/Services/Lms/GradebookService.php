<?php

namespace App\Services\Lms;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\CourseFeedback;
use App\Models\Grade;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * One place that turns assignments, quizzes and (published) examination results into a course gradebook.
 *
 *  - An assignment counts once a teacher has graded the learner's LATEST graded attempt.
 *  - A quiz counts by its grade method (highest / latest / average) over fully marked attempts.
 *  - Learners only ever see what they are entitled to see: graded assignments, quiz marks after the release rule is met.
 *  - The course percentage = points earned / points possible over the items that already have a result. It is a teaching aid
 *    and is NOT an official examination mark; published examination results are shown separately, unchanged.
 */
class GradebookService
{
    public function __construct(protected QuizService $quizzes, protected LmsAccess $access) {}

    /**
     * @return array{columns:array<int,array<string,mixed>>,rows:array<int,array<string,mixed>>}
     */
    public function course(Course $course, bool $forLearner, ?Student $only = null): array
    {
        $assignments = Assignment::query()->where('course_id', $course->id)
            ->when($forLearner, fn ($q) => $q->where('status', 'published')->where(fn ($w) => $w->whereNull('publish_at')->orWhere('publish_at', '<=', now())))
            ->orderBy('id')->get();
        $quizzes = Quiz::query()->where('course_id', $course->id)->when($forLearner, fn ($q) => $q->where('status', 'published'))->orderBy('id')->get();

        $enrolments = CourseEnrolment::query()->where('course_id', $course->id)->where('status', 'active')
            ->when($only, fn ($q) => $q->where('student_id', $only->id))->get();
        $students = Student::query()->with('schoolClass:id,name')->whereIn('id', $enrolments->pluck('student_id'))->orderBy('first_name')->orderBy('last_name')->get();
        $studentIds = $students->pluck('id');

        $subs = AssignmentSubmission::query()->whereIn('assignment_id', $assignments->pluck('id'))->whereIn('student_id', $studentIds)
            ->orderBy('attempt_no')->get()->groupBy(fn ($s) => $s->student_id.'-'.$s->assignment_id);
        $feedback = CourseFeedback::query()->where('course_id', $course->id)->whereIn('student_id', $studentIds)->get()->keyBy('student_id');
        $exams = $course->subject_id
            ? Grade::query()->with(['examination:id,name', 'term:id,name'])->where('subject_id', $course->subject_id)->where('is_published', true)
                ->whereIn('student_id', $studentIds)->get()->groupBy('student_id')
            : collect();

        $completions = LessonCompletion::query()->where('course_id', $course->id)->whereIn('student_id', $studentIds)->get()->groupBy('student_id');
        $lessons = $course->lessons()->get();

        $columns = [];
        foreach ($assignments as $a) {
            $columns[] = ['key' => 'a'.$a->id, 'type' => 'assignment', 'id' => $a->id, 'title' => $a->title, 'max' => $a->max_score, 'url' => route('lms.assignments.show', $a)];
        }
        foreach ($quizzes as $q) {
            $columns[] = ['key' => 'q'.$q->id, 'type' => 'quiz', 'id' => $q->id, 'title' => $q->title, 'max' => null, 'url' => route('lms.quizzes.show', $q)];
        }

        $rows = [];
        foreach ($students as $student) {
            $cells = [];
            $earned = 0.0;
            $possible = 0.0;

            foreach ($assignments as $a) {
                $attempts = $subs->get($student->id.'-'.$a->id, collect());
                $graded = $attempts->where('status', 'graded')->last();
                $latest = $attempts->last();
                $cell = ['state' => $attempts->isEmpty() ? 'missing' : ($graded ? 'graded' : 'submitted'), 'score' => null, 'max' => $a->max_score, 'late' => (bool) $latest?->is_late];
                if ($graded) {
                    $cell['score'] = (float) $graded->score;
                    $earned += (float) $graded->score;
                    $possible += (float) $a->max_score;
                }
                $cells['a'.$a->id] = $cell;
            }

            foreach ($quizzes as $quiz) {
                $res = $this->quizzes->result($quiz, $student);
                $visible = ! $forLearner || $quiz->resultsReleased();
                $cell = ['state' => $res['attempts'] === 0 ? 'missing' : ($res['pending'] ? 'pending' : 'graded'), 'percent' => null, 'score' => null, 'max' => null, 'pending' => $res['pending']];
                if ($visible && $res['percent'] !== null) {
                    $cell['percent'] = $res['percent'];
                    $cell['score'] = $res['score'];
                    $cell['max'] = $res['max'];
                    $earned += (float) $res['score'];
                    $possible += (float) $res['max'];
                } elseif (! $visible && $res['attempts'] > 0) {
                    $cell['state'] = 'withheld';
                }
                $cells['q'.$quiz->id] = $cell;
            }

            $visibleLessons = $lessons->filter(fn ($l) => $this->access->lessonVisible($l, $student));
            $done = ($completions->get($student->id) ?? collect())->whereIn('lesson_id', $visibleLessons->pluck('id'))->count();
            $en = $enrolments->firstWhere('student_id', $student->id);

            $rows[] = [
                'student' => ['id' => $student->id, 'name' => $student->full_name, 'number' => $student->student_number, 'class' => $student->schoolClass?->name],
                'cells' => $cells,
                'percent' => $possible > 0 ? round($earned / $possible * 100, 1) : null,
                'progress' => $visibleLessons->count() > 0 ? (int) round($done / $visibleLessons->count() * 100) : null,
                'feedback' => $feedback->get($student->id)?->body,
                'last_activity' => $en?->last_activity_at?->toDateTimeString(),
                'exams' => ($exams->get($student->id) ?? collect())->map(fn ($g) => [
                    'exam' => $g->examination?->name, 'term' => $g->term?->name, 'total' => $g->total !== null ? (float) $g->total : null, 'letter' => $g->letter_grade,
                ])->values()->all(),
            ];
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /** Teacher's marking queue: ungraded latest submissions and attempts that wait for manual marking. */
    public function pending(Course $course): array
    {
        $assignmentIds = Assignment::query()->where('course_id', $course->id)->pluck('id');
        $quizIds = Quiz::query()->where('course_id', $course->id)->pluck('id');

        return [
            'submissions' => AssignmentSubmission::query()->whereIn('assignment_id', $assignmentIds)->where('status', 'submitted')->count(),
            'attempts' => QuizAttempt::query()->whereIn('quiz_id', $quizIds)->where('status', 'submitted')->where('grading_status', 'needs_grading')->count(),
        ];
    }

    public function activeStudents(Course $course): Collection
    {
        return CourseEnrolment::query()->where('course_id', $course->id)->where('status', 'active')->pluck('student_id');
    }
}
