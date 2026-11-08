<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Services\Communications\AnnouncementService;
use App\Services\Finance\FinanceAccess;
use App\Services\Lms\LmsAccess;
use App\Services\Messaging\MessagingService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Learner portal home. A learner sees only their own record: courses they are actively enrolled in (published courses
 * only), assignments and quizzes of those courses that are live, and results that have been released. Draft or unreleased
 * work and other learners' data never appear here.
 */
class DashboardController extends Controller
{
    use SharesSchoolShell;

    public function __invoke(AnnouncementService $announcements, MessagingService $messages, LmsAccess $lms): Response
    {
        $user = auth()->user();
        $student = $user->studentProfile;
        $student?->loadMissing('schoolClass:id,name');

        // exam results appear only once published; continuous-assessment grades (no examination) are released as they are entered
        $grades = $student
            ? Grade::query()->where('student_id', $student->id)->with('subject')
                ->where(fn ($q) => $q->where('is_published', true)->orWhereNull('examination_id'))->get()
            : collect();

        $avg = $grades->avg('total');
        $gpaProxy = $avg ? round($avg / 25, 1) : null;

        $attendanceTotal = $student ? AttendanceRecord::query()->where('student_id', $student->id)->count() : 0;
        $present = $student ? AttendanceRecord::query()->where('student_id', $student->id)->where('status', 'present')->count() : 0;

        $classId = $student?->school_class_id;

        $timetable = $classId
            ? TimetableSlot::query()->with(['subject:id,name', 'roomLocation:id,name'])->where('school_class_id', $classId)->where('is_published', true)
                ->where('day_of_week', now()->dayOfWeekIso)->orderBy('starts_at')->get()
                ->map(fn (TimetableSlot $slot) => [
                    'id' => $slot->id,
                    'subject' => $slot->subject?->name,
                    'starts_at' => substr((string) $slot->starts_at, 0, 5),
                    'ends_at' => substr((string) $slot->ends_at, 0, 5),
                    'room' => $slot->roomLocation?->name ?? $slot->room,
                    'class' => $student?->schoolClass?->name,
                    'is_published' => true,
                ])
            : [];

        $courseIds = $student
            ? CourseEnrolment::query()->where('student_id', $student->id)->where('status', 'active')->pluck('course_id')
            : collect();
        $courseIds = Course::query()->whereIn('id', $courseIds)->where('status', 'published')->pluck('id');

        $liveAssignments = Assignment::query()->whereIn('course_id', $courseIds)->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
        $submitted = $student ? AssignmentSubmission::query()->where('student_id', $student->id)->pluck('assignment_id') : collect();
        $open = (clone $liveAssignments)->whereNotIn('id', $submitted)->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()));

        $assignments = (clone $open)->orderBy('due_at')->limit(5)->get(['id', 'title', 'due_at', 'course_id'])
            ->map(fn (Assignment $a) => [
                'title' => $a->title,
                'due_at' => $a->due_at?->toDateString(),
                'due_label' => $a->due_at ? $a->due_at->diffForHumans() : null,
            ]);

        $liveQuizzes = Quiz::query()->whereIn('course_id', $courseIds)->where('status', 'published');
        $progress = $this->learnerProgress($student, $courseIds, $lms);

        return Inertia::render('Student/Dashboard', array_merge($this->shellProps(), [
            'stats' => [
                'classes' => $courseIds->count(),
                'pending_assignments' => (clone $open)->count(),
                'assignments_total' => (clone $liveAssignments)->count(),
                'upcoming_quizzes' => (clone $liveQuizzes)->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', now()))->count(),
                'quizzes_total' => (clone $liveQuizzes)->count(),
                'gpa' => $gpaProxy,
                'attendance_rate' => $attendanceTotal > 0 ? round(($present / $attendanceTotal) * 100, 1) : null,
                'average' => $avg ? round($avg, 1) : null,
                'unread_messages' => $messages->unreadCount($user),
                'fees_outstanding' => $this->feesOutstanding($user, $student),
            ],
            'timetable' => $timetable,
            'assignments' => $assignments,
            'results' => $grades->take(5)->map(fn (Grade $g) => [
                'subject' => $g->subject?->name,
                'total' => $g->total,
                'letter_grade' => $g->letter_grade,
            ])->values(),
            'progress' => $progress,
            'events' => $this->upcomingEvents(),
            'term' => Term::query()->where('is_current', true)->value('name'),
            'student' => $student ? [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'class' => $student->schoolClass?->name,
            ] : null,
            'announcements' => $announcements->feed($user, 3)->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => mb_substr($a->body, 0, 160),
                'unread' => $a->my_read_at === null, 'published_at' => $a->published_at?->toIso8601String(),
            ])->values(),
        ]));
    }

    /** Open invoice balance for this learner only — never another family's figures. */
    protected function feesOutstanding(mixed $user, ?Student $student): ?int
    {
        if (! $student || ! $user->can('fees.view') || ! FinanceAccess::canSee($user, (int) $student->id)) {
            return null;
        }

        return (int) Invoice::query()->where('student_id', $student->id)->where('status', '!=', 'cancelled')->get()->sum(fn (Invoice $i) => $i->balance());
    }

    /** Lesson / assignment / quiz counts from published work the learner can actually see. */
    protected function learnerProgress(?Student $student, $courseIds, LmsAccess $lms): array
    {
        $empty = [
            'percent' => null, 'lessons_done' => 0, 'lessons_total' => 0,
            'assignments_done' => 0, 'assignments_total' => 0, 'quizzes_done' => 0, 'quizzes_total' => 0,
            'continue_url' => route('lms.courses.index'),
        ];
        if (! $student || $courseIds->isEmpty()) {
            return $empty;
        }

        $courses = Course::query()->whereIn('id', $courseIds)->orderBy('title')->get();
        $completions = LessonCompletion::query()->where('student_id', $student->id)->whereIn('course_id', $courseIds)->get();
        $lessonDone = 0;
        $lessonTotal = 0;
        foreach ($courses as $course) {
            $visible = $lms->visibleLessons($course, $student);
            $lessonTotal += $visible->count();
            $lessonDone += $completions->whereIn('lesson_id', $visible->pluck('id'))->count();
        }

        $assignmentIds = Assignment::query()->whereIn('course_id', $courseIds)->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))->pluck('id');
        $assignDone = AssignmentSubmission::query()->where('student_id', $student->id)->whereIn('assignment_id', $assignmentIds)->pluck('assignment_id')->unique()->count();
        $quizIds = Quiz::query()->whereIn('course_id', $courseIds)->where('status', 'published')->pluck('id');
        $quizDone = QuizAttempt::query()->where('student_id', $student->id)->whereIn('quiz_id', $quizIds)->where('status', 'submitted')->pluck('quiz_id')->unique()->count();
        $first = $courses->first();

        return [
            'percent' => $lessonTotal ? (int) round($lessonDone / $lessonTotal * 100) : null,
            'lessons_done' => $lessonDone,
            'lessons_total' => $lessonTotal,
            'assignments_done' => $assignDone,
            'assignments_total' => $assignmentIds->count(),
            'quizzes_done' => $quizDone,
            'quizzes_total' => $quizIds->count(),
            'continue_url' => $first ? route('lms.courses.show', $first) : route('lms.courses.index'),
        ];
    }

    /** Published events that have not ended yet — same visibility as the student Events calendar. */
    protected function upcomingEvents()
    {
        return SchoolEvent::query()->with('venue:id,name')->where('status', 'published')
            ->where('ends_at', '>=', now())->orderBy('starts_at')->limit(3)->get()
            ->map(fn (SchoolEvent $e) => [
                'id' => $e->id, 'title' => $e->title, 'category' => $e->category,
                'starts_at' => $e->starts_at?->toIso8601String(), 'venue' => $e->venue?->name,
            ])->values();
    }
}
