<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\LmsFile;
use App\Services\Lms\AssignmentService;
use App\Services\Lms\CourseService;
use App\Services\Lms\LmsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    use UsesLms;

    public const LATE_POLICIES = ['allow', 'block'];

    public function __construct(protected AssignmentService $service, protected CourseService $courses) {}

    public function create(Course $course): Response
    {
        $role = $this->author($course);

        return $this->form($course, null, $role);
    }

    public function edit(Assignment $assignment): Response
    {
        $role = $this->author($assignment->course);

        return $this->form($assignment->course, $assignment, $role);
    }

    protected function form(Course $course, ?Assignment $assignment, string $role): Response
    {
        $assignment?->loadMissing('rubric');

        return Inertia::render('Lms/AssignmentForm', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'assignment' => $assignment ? [
                'id' => $assignment->id, 'title' => $assignment->title, 'instructions' => $assignment->instructions,
                'due_at' => $assignment->due_at?->format('Y-m-d\TH:i'), 'closes_at' => $assignment->closes_at?->format('Y-m-d\TH:i'),
                'publish_at' => $assignment->publish_at?->format('Y-m-d\TH:i'), 'max_score' => $assignment->max_score, 'status' => $assignment->status,
                'late_policy' => $assignment->late_policy, 'max_attempts' => $assignment->max_attempts,
                'accepts_text' => (bool) $assignment->accepts_text, 'accepts_files' => (bool) $assignment->accepts_files,
                'rubric_title' => $assignment->rubric?->title, 'criteria' => $assignment->rubric?->criteria ?? [],
                'attachments' => $this->attachments($assignment, true),
            ] : null,
            'latePolicies' => self::LATE_POLICIES,
        ]);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'], 'instructions' => ['nullable', 'string', 'max:20000'],
            'due_at' => ['nullable', 'date'], 'closes_at' => ['nullable', 'date'], 'publish_at' => ['nullable', 'date'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'], 'status' => ['required', 'in:draft,published'],
            'late_policy' => ['required', 'in:'.implode(',', self::LATE_POLICIES)], 'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'accepts_text' => ['boolean'], 'accepts_files' => ['boolean'],
            'rubric_title' => ['nullable', 'string', 'max:150'], 'criteria' => ['nullable', 'array', 'max:20'],
            'criteria.*.label' => ['nullable', 'string', 'max:150'], 'criteria.*.max_points' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $course, $v) {
            $assignment = $this->service->save($request->user(), $course, null, $v);

            return redirect()->route('lms.assignments.edit', $assignment)->with('success', 'ok.lms.assignmentSaved');
        });
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->author($assignment->course);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $assignment, $v) {
            $this->service->save($request->user(), $assignment->course, $assignment, $v);

            return back()->with('success', 'ok.lms.assignmentSaved');
        });
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $course = $assignment->course;
        $this->author($course);
        $this->courses->deleteAssignment($assignment);

        return redirect()->route('lms.courses.show', $course)->with('success', 'ok.lms.assignmentRemoved');
    }

    public function attach(Request $request, Assignment $assignment): RedirectResponse
    {
        $course = $assignment->course;
        $this->author($course);
        $v = $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'file' => ['required', 'file', 'mimes:'.LessonController::MATERIAL_MIMES, 'max:'.config('schoolos.lms.file_max_kb')],
        ]);
        $this->courses->storeUpload($request->file('file'), $course, 'assignment', $assignment->id, $request->user(), ['type' => 'file', 'title' => $v['title'] ?? null]);

        return back()->with('success', 'ok.lms.materialAdded');
    }

    /** @return array<int,array<string,mixed>> */
    protected function attachments(Assignment $assignment, bool $author): array
    {
        return LmsFile::query()->where('owner_type', 'assignment')->where('owner_id', $assignment->id)->orderBy('id')->get()
            ->map(fn (LmsFile $f) => [
                'id' => $f->id, 'title' => $f->title ?: $f->original_name, 'name' => $f->original_name, 'size' => $f->size,
                'file_url' => route('lms.files.show', $f), 'delete_url' => $author ? route('lms.files.destroy', $f) : null,
            ])->values()->all();
    }

    // ------------------------------------------------------------------ show

    public function show(Assignment $assignment): Response
    {
        $course = $assignment->course;
        $role = $this->role($course);
        $author = LmsAccess::authors($role);
        abort_unless($author || $assignment->isLive(), 404);
        $assignment->loadMissing('rubric');

        $base = $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'assignment' => [
                'id' => $assignment->id, 'title' => $assignment->title, 'instructions' => $assignment->instructions,
                'due_at' => $assignment->due_at?->toDateTimeString(), 'closes_at' => $assignment->closes_at?->toDateTimeString(),
                'publish_at' => $assignment->publish_at?->toDateTimeString(), 'max_score' => $assignment->max_score, 'status' => $assignment->status,
                'late_policy' => $assignment->late_policy, 'max_attempts' => $assignment->max_attempts,
                'accepts_text' => (bool) $assignment->accepts_text, 'accepts_files' => (bool) $assignment->accepts_files,
                'criteria' => $assignment->rubric?->criteria ?? [], 'rubric_title' => $assignment->rubric?->title,
                'edit_url' => $author ? route('lms.assignments.edit', $assignment) : null,
            ],
            'attachments' => $this->attachments($assignment, false),
            'fileMaxKb' => (int) config('schoolos.lms.file_max_kb'), 'maxFiles' => (int) config('schoolos.lms.max_submission_files'),
        ];

        if ($author) {
            $enrolled = CourseEnrolment::query()->where('course_id', $course->id)->where('status', 'active')->with('student.schoolClass:id,name')->get();
            $subs = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->orderBy('attempt_no')->get()->groupBy('student_id');

            return Inertia::render('Lms/AssignmentShow', $base + [
                'roster' => $enrolled->map(function ($e) use ($subs) {
                    $list = $subs->get($e->student_id, collect());
                    $last = $list->last();

                    return [
                        'student_id' => $e->student_id, 'name' => $e->student?->full_name, 'class' => $e->student?->schoolClass?->name,
                        'attempts' => $list->count(), 'status' => $last?->status ?? 'missing', 'is_late' => (bool) $last?->is_late,
                        'submitted_at' => $last?->submitted_at?->toDateTimeString(), 'score' => $last?->score !== null ? (float) $last->score : null,
                        'url' => $last ? route('lms.submissions.show', $last) : null,
                    ];
                })->sortBy('name')->values(),
                'mine' => null, 'block' => null,
            ]);
        }

        $student = $this->me();
        $attempts = $this->service->attempts($assignment, $student);

        return Inertia::render('Lms/AssignmentShow', $base + [
            'roster' => [],
            'mine' => $attempts->map(fn (AssignmentSubmission $s) => [
                'id' => $s->id, 'attempt_no' => $s->attempt_no, 'status' => $s->status, 'is_late' => (bool) $s->is_late, 'body' => $s->body,
                'submitted_at' => $s->submitted_at?->toDateTimeString(), 'score' => $s->score !== null ? (float) $s->score : null,
                'feedback' => in_array($s->status, ['graded', 'returned'], true) ? $s->feedback : null,
                'rubric_scores' => $s->status === 'graded' ? $s->rubric_scores : null,
                'files' => LmsFile::query()->where('owner_type', 'submission')->where('owner_id', $s->id)->get()
                    ->map(fn ($f) => ['id' => $f->id, 'name' => $f->original_name, 'size' => $f->size, 'file_url' => route('lms.files.show', $f)])->values(),
            ])->values(),
            'block' => $this->service->submitBlock($assignment, $student),
        ]);
    }

    public function submit(Request $request, Assignment $assignment): RedirectResponse
    {
        $course = $assignment->course;
        $this->role($course, [LmsAccess::LEARNER]);
        $student = $this->me();
        abort_unless($student && $assignment->isLive(), 404);

        $v = $request->validate([
            'body' => ['nullable', 'string', 'max:50000'],
            'files' => ['nullable', 'array', 'max:'.config('schoolos.lms.max_submission_files')],
            'files.*' => ['file', 'mimes:'.LessonController::MATERIAL_MIMES, 'max:'.config('schoolos.lms.file_max_kb')],
        ]);

        return $this->attempt(function () use ($request, $course, $assignment, $student, $v) {
            $this->service->submit($course, $assignment, $student, $request->user(), $v['body'] ?? null, $request->file('files', []));

            return back()->with('success', 'ok.lms.submitted');
        });
    }
}
