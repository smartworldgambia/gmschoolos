<?php

namespace App\Http\Controllers\Examinations;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\GradeChange;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\Academics\TeacherScope;
use App\Services\Results\ResultsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExaminationController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected ResultsService $results, protected TeacherScope $scope) {}

    public function index(): Response
    {
        $this->authorizeAccess();

        $exams = Examination::query()->with('term')->withCount('grades')->latest()->get()
            ->map(fn (Examination $exam) => [
                'id' => $exam->id, 'name' => $exam->name, 'status' => $exam->status, 'type' => $exam->type,
                'term' => $exam->term?->name, 'grades_count' => $exam->grades_count,
                'published_at' => $exam->published_at?->toDateTimeString(),
                'url' => route('examinations.show', $exam),
            ]);

        return Inertia::render('Examinations/Index', array_merge($this->shellProps(), [
            'examinations' => $exams,
            'terms' => Term::query()->orderByDesc('is_current')->orderByDesc('starts_on')->get(['id', 'name']),
            'types' => Examination::TYPES,
            'storeUrl' => route('examinations.store'),
            'can' => ['manage' => auth()->user()->can('exams.manage')],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);

        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'term_id' => ['nullable', $this->inSchool('terms')],
            'type' => ['required', 'in:'.implode(',', Examination::TYPES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $term = ! empty($v['term_id']) ? Term::query()->find($v['term_id']) : null;
        Examination::query()->create([
            ...$v,
            'school_id' => $this->schoolId(),
            'academic_year_id' => $term?->academic_year_id ?? AcademicYear::query()->where('is_current', true)->value('id'),
            'status' => 'draft',
        ]);

        return back()->with('success', 'ok.examCreated');
    }

    public function show(Request $request, int $examination): Response
    {
        $this->authorizeAccess();
        $user = auth()->user();
        $exam = Examination::query()->with('term')->findOrFail($examination);
        $manager = $user->can('exams.manage');
        $categories = $this->results->categories();

        // class + subject pairs this user may mark
        $pairs = ClassSubject::query()->with(['schoolClass', 'subject'])
            ->when(! $manager, fn ($q) => $q->where(fn ($w) => $w->where('teacher_id', $user->id)
                ->orWhereIn('subject_id', $this->scope->headedSubjectIds($user))))
            ->get()
            ->sortBy(fn ($r) => ($r->schoolClass?->name ?? '').($r->subject?->name ?? ''))->values();

        $selected = $pairs->first(fn ($r) => $r->school_class_id === $request->integer('class_id') && $r->subject_id === $request->integer('subject_id'))
            ?? $pairs->first();

        $sheet = [];
        if ($selected) {
            $students = Student::query()->where('school_class_id', $selected->school_class_id)->where('status', 'active')
                ->orderBy('first_name')->orderBy('last_name')->get();
            $grades = Grade::query()->with('scores')->where('examination_id', $exam->id)->where('subject_id', $selected->subject_id)
                ->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');
            $sheet = $students->map(fn (Student $s) => [
                'student_id' => $s->id, 'student_number' => $s->student_number, 'name' => $s->full_name,
                'scores' => $categories->mapWithKeys(fn ($c) => [$c->id => ($grades[$s->id] ?? null)?->scores->firstWhere('assessment_category_id', $c->id)?->score])->all(),
                'total' => $grades[$s->id]->total ?? null, 'letter' => $grades[$s->id]->letter_grade ?? null,
                'remark' => $grades[$s->id]->remark ?? null, 'revised' => ($grades[$s->id]->revised_at ?? null) !== null,
            ])->values();
        }

        $review = $manager || $exam->status !== 'draft'
            ? Grade::query()->with(['student', 'subject'])->where('examination_id', $exam->id)->orderBy('subject_id')->orderBy('student_id')->limit(500)->get()
                ->map(fn (Grade $g) => [
                    'id' => $g->id, 'student' => $g->student?->full_name, 'subject' => $g->subject?->name,
                    'ca_score' => $g->ca_score, 'exam_score' => $g->exam_score, 'total' => $g->total,
                    'letter_grade' => $g->letter_grade, 'revised' => $g->revised_at !== null,
                ])
            : [];

        return Inertia::render('Examinations/Show', array_merge($this->shellProps(), [
            'examination' => [
                'id' => $exam->id, 'name' => $exam->name, 'status' => $exam->status, 'type' => $exam->type,
                'term' => $exam->term?->name, 'published_at' => $exam->published_at?->toDateTimeString(),
                'returned_reason' => $exam->returned_reason,
            ],
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'type' => $c->type, 'weight' => $c->weight, 'max_score' => $c->max_score]),
            'pairs' => $pairs->map(fn ($r) => ['class_id' => $r->school_class_id, 'class' => $r->schoolClass?->name, 'subject_id' => $r->subject_id, 'subject' => $r->subject?->name]),
            'selected' => $selected ? ['class_id' => $selected->school_class_id, 'subject_id' => $selected->subject_id] : null,
            'sheet' => $sheet,
            'review' => $review,
            'can' => [
                'mark' => $user->canAny(['exams.mark', 'exams.manage']),
                'manage' => $manager,
                'publish' => $user->can('results.publish'),
            ],
            'urls' => [
                'marks' => route('examinations.marks.store', $exam),
                'submit' => route('examinations.submit', $exam),
                'approve' => route('examinations.approve', $exam),
                'return' => route('examinations.return', $exam),
                'publish' => route('examinations.publish', $exam),
                'history' => $manager ? route('examinations.history', $exam) : null,
            ],
        ]));
    }

    /** Whole mark sheet for one class + subject. All-or-nothing: one invalid mark saves nothing. */
    public function storeMarks(Request $request, int $examination): RedirectResponse
    {
        abort_unless(auth()->user()?->canAny(['exams.mark', 'exams.manage']), 403);
        $exam = Examination::query()->findOrFail($examination);

        $v = $request->validate([
            'subject_id' => ['required', $this->inSchool('subjects')],
            'class_id' => ['required', $this->inSchool('school_classes')],
            'reason' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1', 'max:300'],
            'rows.*.student_id' => ['required', 'integer'],
            'rows.*.scores' => ['nullable', 'array'],
            'rows.*.remark' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(function () use ($exam, $v) {
            $students = Student::query()->where('school_class_id', $v['class_id'])->get()->keyBy('id');
            DB::transaction(function () use ($exam, $v, $students) {
                $saved = 0;
                foreach ($v['rows'] as $row) {
                    $student = $students[(int) $row['student_id']] ?? null;
                    if (! $student) {
                        throw AcademicRuleException::because('err.exam.studentNotInClass');
                    }
                    $scores = array_filter((array) ($row['scores'] ?? []), fn ($x) => $x !== null && $x !== '');
                    if ($scores === []) {
                        continue;
                    }
                    $this->results->saveMarks(auth()->user(), $exam, $student, (int) $v['subject_id'], $scores, $row['remark'] ?? null, $v['reason'] ?? null);
                    $saved++;
                }
                if ($saved === 0) {
                    throw AcademicRuleException::because('err.exam.scoreRequired');
                }
            });
        }, 'ok.marksSaved');
    }

    /** Single mark (kept for existing callers): ca_score / exam_score map onto the school's CA / exam categories. */
    public function storeGrade(Request $request, int $examination): RedirectResponse
    {
        abort_unless(auth()->user()?->can('exams.mark'), 403);
        $exam = Examination::query()->findOrFail($examination);

        $v = $request->validate([
            'student_id' => ['required', $this->inSchool('students')],
            'subject_id' => ['required', $this->inSchool('subjects')],
            'ca_score' => ['nullable', 'numeric', 'min:0'],
            'exam_score' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(function () use ($exam, $v) {
            $categories = $this->results->categories();
            $scores = [];
            if (($v['ca_score'] ?? null) !== null && $ca = $categories->firstWhere('type', 'ca')) {
                $scores[$ca->id] = $v['ca_score'];
            }
            if (($v['exam_score'] ?? null) !== null && $ex = $categories->firstWhere('type', 'exam')) {
                $scores[$ex->id] = $v['exam_score'];
            }
            $this->results->saveMarks(auth()->user(), $exam, Student::query()->findOrFail($v['student_id']), (int) $v['subject_id'], $scores, $v['remark'] ?? null, $v['reason'] ?? null);
        }, 'ok.marksSaved');
    }

    public function submit(int $examination): RedirectResponse
    {
        $exam = Examination::query()->findOrFail($examination);

        return $this->attempt(fn () => $this->results->submit(auth()->user(), $exam), 'ok.examSubmitted');
    }

    public function approve(int $examination): RedirectResponse
    {
        $exam = Examination::query()->findOrFail($examination);

        return $this->attempt(fn () => $this->results->approve(auth()->user(), $exam), 'ok.examApproved');
    }

    public function returnToDraft(Request $request, int $examination): RedirectResponse
    {
        $exam = Examination::query()->findOrFail($examination);
        $v = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);

        return $this->attempt(fn () => $this->results->returnToDraft(auth()->user(), $exam, $v['reason']), 'ok.examReturned');
    }

    public function publish(int $examination): RedirectResponse
    {
        abort_unless(auth()->user()?->can('results.publish'), 403);
        $exam = Examination::query()->findOrFail($examination);

        return $this->attempt(fn () => $this->results->publish(auth()->user(), $exam), 'ok.examPublished');
    }

    public function history(int $examination): Response
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);
        $exam = Examination::query()->findOrFail($examination);

        return Inertia::render('Examinations/History', array_merge($this->shellProps(), [
            'examination' => ['id' => $exam->id, 'name' => $exam->name, 'url' => route('examinations.show', $exam)],
            'changes' => GradeChange::query()->with(['author', 'student', 'subject'])->where('examination_id', $exam->id)
                ->latest()->limit(300)->get()->map(fn (GradeChange $c) => [
                    'id' => $c->id, 'event' => $c->event, 'student' => $c->student?->full_name, 'subject' => $c->subject?->name,
                    'by' => $c->author?->name, 'reason' => $c->reason, 'at' => $c->created_at?->toDateTimeString(),
                    'old' => $c->old_values['total'] ?? null, 'new' => $c->new_values['total'] ?? null,
                    'old_letter' => $c->old_values['letter_grade'] ?? null, 'new_letter' => $c->new_values['letter_grade'] ?? null,
                ]),
        ]));
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->canAny(['exams.manage', 'exams.mark']), 403);
    }
}
