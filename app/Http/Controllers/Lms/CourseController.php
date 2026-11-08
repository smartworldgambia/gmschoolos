<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\CourseCategory;
use App\Models\CourseEnrolment;
use App\Models\CourseSection;
use App\Models\CourseTeacher;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academics\TeacherScope;
use App\Services\Lms\CourseService;
use App\Services\Lms\LmsAccess;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    use UsesLms;

    public function __construct(protected CourseService $service, protected TeacherScope $scope) {}

    public function index(): Response
    {
        $user = auth()->user();
        $student = $this->me();
        $manager = $user->can('lms.manage');
        $teaching = $user->can('lms.teach');

        $query = Course::query()
            ->with(['teacher:id,name', 'subject:id,name', 'category:id,name', 'audiences.schoolClass:id,name'])
            ->withCount(['lessons', 'assignments', 'quizzes'])
            ->orderBy('title');
        if ($manager) {
            $mode = 'author';
        } elseif ($teaching) {
            $mode = 'author';
            $query->where(fn ($w) => $w->where('teacher_id', $user->id)->orWhereIn('id', CourseTeacher::query()->where('user_id', $user->id)->select('course_id')));
        } elseif ($student) {
            $mode = 'learner';
            $query->where('status', 'published')->whereIn('id', CourseEnrolment::query()->where('student_id', $student->id)->where('status', 'active')->select('course_id'));
        } else {
            $mode = 'none';
            $query->whereRaw('1 = 0');
        }
        $courses = $query->get();
        $courseIds = $courses->pluck('id');

        $done = $student ? LessonCompletion::query()->where('student_id', $student->id)->get()->groupBy('course_id') : collect();
        $enrolled = CourseEnrolment::query()->where('status', 'active')->whereIn('course_id', $courseIds)->get()->groupBy('course_id');
        $liveLessons = $student ? Lesson::query()->whereIn('course_id', $courseIds)->get()->groupBy('course_id') : collect();

        return Inertia::render('Lms/Index', $this->lmsPage([
            'mode' => $mode,
            'courses' => $courses->map(function (Course $c) use ($student, $done, $enrolled, $liveLessons) {
                $progress = null;
                if ($student) {
                    $visible = ($liveLessons->get($c->id) ?? collect())->filter(fn ($l) => $this->lms()->lessonVisible($l, $student));
                    $count = ($done->get($c->id) ?? collect())->whereIn('lesson_id', $visible->pluck('id'))->count();
                    $progress = $visible->count() ? (int) round($count / $visible->count() * 100) : null;
                }

                return [
                    'id' => $c->id, 'title' => $c->title, 'code' => $c->code, 'status' => $c->status, 'teacher' => $c->teacher?->name,
                    'subject' => $c->subject?->name, 'category' => $c->category?->name, 'lessons_count' => $c->lessons_count,
                    'assignments_count' => $c->assignments_count, 'quizzes_count' => $c->quizzes_count,
                    'enrolled' => ($enrolled->get($c->id) ?? collect())->count(), 'progress_percent' => $progress,
                    'classes' => $c->audiences->pluck('schoolClass.name')->filter()->unique()->values(),
                    'url' => route('lms.courses.show', $c),
                ];
            })->values(),
            'timeline' => $this->dashboardTimeline($courses, $mode === 'author', $student),
            'recent' => $this->dashboardRecent($courseIds, $mode === 'author'),
            'can' => ['create' => $manager || $teaching, 'banks' => $manager || $teaching, 'external' => $manager],
        ]));
    }

    // ------------------------------------------------------------------ create / edit

    public function create(): Response
    {
        abort_unless(auth()->user()->canAny(['lms.manage', 'lms.teach']), 403);

        return $this->form(null);
    }

    public function edit(Course $course): Response
    {
        $this->author($course);

        return $this->form($course);
    }

    protected function form(?Course $course): Response
    {
        $user = auth()->user();
        $manager = $user->can('lms.manage');

        $subjects = Subject::query()->orderBy('name')->get(['id', 'name', 'code']);
        if (! $manager) {
            $subjects = $subjects->filter(fn ($s) => $this->service->teachesSubject($user, $s->id))->values();
        }
        $classes = SchoolClass::query()->orderBy('name')->get(['id', 'name']);
        if (! $manager) {
            $allowed = $this->scope->classIds($user);
            $classes = $classes->filter(fn ($c) => $allowed->contains($c->id))->values();
        }
        $teachers = $manager
            ? User::query()->where('school_id', app(TenantContext::class)->id())->permission(['lms.teach', 'lms.manage'])->orderBy('name')->get(['id', 'name'])
            : collect();

        return Inertia::render('Lms/CourseForm', $this->lmsPage([
            'course' => $course ? [
                'id' => $course->id, 'title' => $course->title, 'code' => $course->code, 'description' => $course->description,
                'subject_id' => $course->subject_id, 'category_id' => $course->category_id, 'status' => $course->status,
                'objectives' => $course->objectives ?? [], 'teacher_id' => $course->teacher_id,
                'co_teacher_ids' => $course->coTeachers()->pluck('user_id')->all(),
                'audience_class_ids' => $course->audiences()->pluck('school_class_id')->all(),
            ] : null,
            'subjects' => $subjects, 'classes' => $classes, 'teachers' => $teachers,
            'categories' => CourseCategory::query()->orderBy('name')->get(['id', 'name']),
            'canAssignTeachers' => $manager,
        ]));
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'], 'code' => ['nullable', 'string', 'max:40'], 'description' => ['nullable', 'string', 'max:5000'],
            'subject_id' => ['nullable', $this->inSchool('subjects')], 'category_id' => ['nullable', $this->inSchool('course_categories')],
            'category_name' => ['nullable', 'string', 'max:120'], 'status' => ['required', 'in:'.implode(',', Course::STATUSES)],
            'objectives' => ['nullable', 'array', 'max:30'], 'objectives.*' => ['nullable', 'string', 'max:300'],
            'teacher_id' => ['nullable', 'integer'], 'co_teacher_ids' => ['nullable', 'array'], 'co_teacher_ids.*' => ['integer'],
            'audience_class_ids' => ['nullable', 'array'], 'audience_class_ids.*' => ['integer', $this->inSchool('school_classes')],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAny(['lms.manage', 'lms.teach']), 403);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $v) {
            $course = $this->service->saveCourse($request->user(), null, $v);

            return redirect()->route('lms.courses.show', $course)->with('success', 'ok.lms.courseSaved');
        });
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $course, $v) {
            $this->service->saveCourse($request->user(), $course, $v);

            return redirect()->route('lms.courses.show', $course)->with('success', 'ok.lms.courseSaved');
        });
    }

    // ------------------------------------------------------------------ show

    public function show(Course $course): Response
    {
        $role = $this->role($course);
        $author = LmsAccess::authors($role);
        $student = $author ? null : $this->me();
        $access = $this->lms();

        $lessons = $course->lessons()->get();
        if (! $author) {
            $lessons = $lessons->filter(fn ($l) => $access->lessonVisible($l, $student))->values();
        }
        $completed = $student ? LessonCompletion::query()->where('course_id', $course->id)->where('student_id', $student->id)->pluck('lesson_id') : collect();

        $lessonRow = fn (Lesson $l) => [
            'id' => $l->id, 'title' => $l->title, 'content_type' => $l->content_type, 'duration_minutes' => $l->duration_minutes,
            'status' => $l->status, 'publish_at' => $l->publish_at?->toDateTimeString(), 'completed' => $completed->contains($l->id),
            'restricted' => ! empty($l->audience_class_ids), 'url' => route('lms.lessons.show', $l),
        ];
        $sections = $course->sections()->get()->map(fn (CourseSection $s) => [
            'id' => $s->id, 'title' => $s->title, 'description' => $s->description,
            'lessons' => $lessons->where('course_section_id', $s->id)->map($lessonRow)->values(),
        ]);
        if (! $author) {
            $sections = $sections->filter(fn ($s) => $s['lessons']->isNotEmpty())->values();
        }
        $loose = $lessons->whereNull('course_section_id')->map($lessonRow)->values();

        $assignments = Assignment::query()->where('course_id', $course->id)->orderBy('due_at')->orderBy('id')->get();
        if (! $author) {
            $assignments = $assignments->filter(fn ($a) => $a->isLive())->values();
        }
        $mine = $student
            ? AssignmentSubmission::query()->whereIn('assignment_id', $assignments->pluck('id'))->where('student_id', $student->id)->orderBy('attempt_no')->get()->groupBy('assignment_id')
            : collect();
        $counts = $author
            ? AssignmentSubmission::query()->whereIn('assignment_id', $assignments->pluck('id'))->where('status', 'submitted')->selectRaw('assignment_id, count(*) n')->groupBy('assignment_id')->pluck('n', 'assignment_id')
            : collect();

        $quizzes = Quiz::query()->where('course_id', $course->id)->orderBy('id')->get();
        if (! $author) {
            $quizzes = $quizzes->filter(fn ($q) => $q->isPublished())->values();
        }
        $attempts = $student ? QuizAttempt::query()->whereIn('quiz_id', $quizzes->pluck('id'))->where('student_id', $student->id)->get()->groupBy('quiz_id') : collect();

        $progress = null;
        if ($student && $lessons->isNotEmpty()) {
            $progress = (int) round($completed->intersect($lessons->pluck('id'))->count() / $lessons->count() * 100);
        }

        return Inertia::render('Lms/CourseShow', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'details' => [
                'description' => $course->description, 'objectives' => $course->objectives ?? [], 'status' => $course->status,
                'teacher' => $course->teacher?->name, 'category' => $course->category?->name,
                'co_teachers' => User::query()->whereIn('id', CourseTeacher::query()->where('course_id', $course->id)->pluck('user_id'))->pluck('name'),
                'classes' => SchoolClass::query()->whereIn('id', $course->audiences()->pluck('school_class_id'))->pluck('name'),
            ],
            'sections' => $sections, 'looseLessons' => $loose,
            'assignments' => $assignments->map(function (Assignment $a) use ($mine, $counts, $author) {
                $my = $mine->get($a->id, collect());
                $graded = $my->where('status', 'graded')->last();
                $mineStatus = $author ? null : ($my->isEmpty() ? 'missing' : ($graded ? 'graded' : ($my->last()->status === 'returned' ? 'returned' : 'submitted')));
                $toMark = $author ? (int) ($counts[$a->id] ?? 0) : 0;

                return [
                    'id' => $a->id, 'title' => $a->title, 'due_at' => $a->due_at?->toDateTimeString(), 'max_score' => $a->max_score, 'status' => $a->status,
                    'publish_at' => $a->publish_at?->toDateTimeString(), 'url' => route('lms.assignments.show', $a),
                    'mine' => $mineStatus,
                    'work_state' => $this->assignmentWorkState($mineStatus, $a->due_at, $author, $toMark),
                    'score' => $graded?->score !== null ? (float) $graded->score : null, 'to_mark' => $author ? $toMark : null,
                ];
            })->values(),
            'quizzes' => $quizzes->map(function (Quiz $q) use ($attempts, $author) {
                $mineAttempts = $attempts->get($q->id) ?? collect();
                $mineStatus = $author ? null : ($mineAttempts->isEmpty() ? null : (($mineAttempts->contains(fn ($a) => $a->score !== null) && $q->resultsReleased()) ? 'graded' : 'submitted'));

                return [
                    'id' => $q->id, 'title' => $q->title, 'status' => $q->status, 'closes_at' => $q->closes_at?->toDateTimeString(),
                    'opens_at' => $q->opens_at?->toDateTimeString(), 'max_attempts' => $q->max_attempts,
                    'attempts_used' => $mineAttempts->count(), 'url' => route('lms.quizzes.show', $q),
                    'work_state' => $this->quizWorkState($mineStatus, $q->closes_at, $author, 0),
                ];
            })->values(),
            'announcements' => CourseAnnouncement::query()->where('course_id', $course->id)
                ->when(! $author, fn ($q) => $q->where(fn ($w) => $w->whereNull('publish_at')->orWhere('publish_at', '<=', now())))
                ->orderByDesc('is_pinned')->orderByDesc('id')->limit(3)->get()
                ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'is_pinned' => $a->is_pinned, 'publish_at' => $a->publish_at?->toDateTimeString(), 'created_at' => $a->created_at->toDateTimeString()]),
            'progressPercent' => $progress,
            'enrolledCount' => $author ? CourseEnrolment::query()->where('course_id', $course->id)->where('status', 'active')->count() : null,
        ]);
    }

    // ------------------------------------------------------------------ people / enrolment

    public function people(Course $course): Response
    {
        $role = $this->author($course);
        $enrolments = CourseEnrolment::query()->where('course_id', $course->id)->with('student.schoolClass:id,name')->orderBy('status')->orderBy('id')->get();
        $enrolledIds = $enrolments->where('status', 'active')->pluck('student_id');

        $candidates = Student::query()->with('schoolClass:id,name')->where('status', 'active')->whereNotIn('id', $enrolledIds);
        if (! auth()->user()->can('lms.manage')) {
            $candidates = $this->scope->limitStudents($candidates, auth()->user());
        }

        return Inertia::render('Lms/People', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'enrolments' => $enrolments->map(fn (CourseEnrolment $e) => [
                'id' => $e->id, 'status' => $e->status, 'source' => $e->source, 'enrolled_at' => $e->enrolled_at?->toDateString(),
                'name' => $e->student?->full_name, 'number' => $e->student?->student_number, 'class' => $e->student?->schoolClass?->name,
            ])->values(),
            'candidates' => $candidates->orderBy('first_name')->orderBy('last_name')->limit(500)->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name, 'number' => $s->student_number, 'class' => $s->schoolClass?->name])->values(),
            'audienceCount' => $course->audiences()->count(),
        ]);
    }

    public function enrol(Request $request, Course $course): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['integer', $this->inSchool('students')]]);

        return $this->attempt(function () use ($request, $course, $v) {
            $n = $this->service->enrol($request->user(), $course, $v['student_ids']);

            return back()->with('success', 'ok.lms.enrolled');
        });
    }

    public function syncAudience(Course $course): RedirectResponse
    {
        $this->author($course);

        return $this->attempt(function () use ($course) {
            $this->service->syncAudience($course);

            return back()->with('success', 'ok.lms.audienceSynced');
        });
    }

    public function withdraw(Course $course, CourseEnrolment $enrolment): RedirectResponse
    {
        $this->author($course);
        abort_unless((int) $enrolment->course_id === (int) $course->id, 404);
        $this->service->withdraw($enrolment);

        return back()->with('success', 'ok.lms.withdrawn');
    }

    // ------------------------------------------------------------------ sections

    public function storeSection(Request $request, Course $course): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate(['title' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:1000']]);
        $this->service->saveSection($course, null, $v);

        return back()->with('success', 'ok.lms.sectionSaved');
    }

    public function updateSection(Request $request, CourseSection $section): RedirectResponse
    {
        $this->author($section->course);
        $v = $request->validate(['title' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:1000'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999']]);
        $this->service->saveSection($section->course, $section, $v);

        return back()->with('success', 'ok.lms.sectionSaved');
    }

    public function destroySection(CourseSection $section): RedirectResponse
    {
        $this->author($section->course);
        $section->delete(); // lessons stay in the course, unsectioned

        return back()->with('success', 'ok.lms.sectionRemoved');
    }

    /** Upcoming / overdue work across the caller's already-scoped courses. */
    protected function dashboardTimeline($courses, bool $author, ?Student $student): array
    {
        $ids = $courses->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }
        $titles = $courses->pluck('title', 'id');

        $assignments = Assignment::query()->whereIn('course_id', $ids)->orderBy('due_at')->orderBy('id')->get();
        $quizzes = Quiz::query()->whereIn('course_id', $ids)->orderBy('closes_at')->orderBy('id')->get();
        if (! $author) {
            $assignments = $assignments->filter(fn (Assignment $a) => $a->isLive())->values();
            $quizzes = $quizzes->filter(fn (Quiz $q) => $q->isPublished())->values();
        }

        $mine = $student
            ? AssignmentSubmission::query()->whereIn('assignment_id', $assignments->pluck('id'))->where('student_id', $student->id)->orderBy('attempt_no')->get()->groupBy('assignment_id')
            : collect();
        $quizAttempts = $student
            ? QuizAttempt::query()->whereIn('quiz_id', $quizzes->pluck('id'))->where('student_id', $student->id)->get()->groupBy('quiz_id')
            : collect();
        $toMark = $author
            ? AssignmentSubmission::query()->whereIn('assignment_id', $assignments->pluck('id'))->where('status', 'submitted')->selectRaw('assignment_id, count(*) n')->groupBy('assignment_id')->pluck('n', 'assignment_id')
            : collect();

        $items = collect();
        foreach ($assignments as $a) {
            $my = $mine->get($a->id, collect());
            $graded = $my->where('status', 'graded')->last();
            $mineStatus = $author ? null : ($my->isEmpty() ? 'missing' : ($graded ? 'graded' : ($my->last()->status === 'returned' ? 'returned' : 'submitted')));
            $mark = (int) ($toMark[$a->id] ?? 0);
            $items->push([
                'kind' => 'assignment',
                'id' => $a->id,
                'title' => $a->title,
                'course' => $titles[$a->course_id] ?? '',
                'course_id' => $a->course_id,
                'when' => $a->due_at?->toDateTimeString(),
                'url' => route('lms.assignments.show', $a),
                'state' => $this->assignmentWorkState($mineStatus, $a->due_at, $author, $mark),
                'to_mark' => $author ? $mark : null,
            ]);
        }
        foreach ($quizzes as $q) {
            $attempts = $quizAttempts->get($q->id) ?? collect();
            $mineStatus = $author ? null : ($attempts->isEmpty() ? null : (($attempts->contains(fn ($x) => $x->score !== null) && $q->resultsReleased()) ? 'graded' : 'submitted'));
            $items->push([
                'kind' => 'quiz',
                'id' => $q->id,
                'title' => $q->title,
                'course' => $titles[$q->course_id] ?? '',
                'course_id' => $q->course_id,
                'when' => $q->closes_at?->toDateTimeString() ?? $q->opens_at?->toDateTimeString(),
                'url' => route('lms.quizzes.show', $q),
                'state' => $this->quizWorkState($mineStatus, $q->closes_at, $author, 0),
                'to_mark' => null,
            ]);
        }

        $rank = ['overdue' => 0, 'to_mark' => 1, 'due' => 2, 'missing' => 3, 'upcoming' => 4, 'returned' => 5, 'submitted' => 6, 'graded' => 7];

        return $items
            ->sortBy(fn ($row) => sprintf('%d-%s-%06d', $rank[$row['state']] ?? 9, $row['when'] ?? '9999', $row['id']))
            ->take(12)
            ->values()
            ->all();
    }

    /** Latest course notices for the dashboard (already tenant-scoped by course ids). */
    protected function dashboardRecent($courseIds, bool $author): array
    {
        if ($courseIds->isEmpty()) {
            return [];
        }

        return CourseAnnouncement::query()
            ->whereIn('course_id', $courseIds)
            ->with('course:id,title')
            ->when(! $author, fn ($q) => $q->where(fn ($w) => $w->whereNull('publish_at')->orWhere('publish_at', '<=', now())))
            ->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (CourseAnnouncement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'course' => $a->course?->title,
                'course_id' => $a->course_id,
                'is_pinned' => $a->is_pinned,
                'when' => ($a->publish_at ?? $a->created_at)?->toDateTimeString(),
                'url' => route('lms.courses.announcements', $a->course_id),
            ])
            ->all();
    }
}
