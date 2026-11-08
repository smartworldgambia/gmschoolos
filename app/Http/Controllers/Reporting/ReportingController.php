<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicReport;
use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\RecordsDocument;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\Academics\TeacherScope;
use App\Services\Reporting\AcademicReportPdf;
use App\Services\Reporting\AcademicReportService;
use App\Services\Reporting\AcademicReportSettings;
use App\Services\Reporting\ReportCompiler;
use App\Services\Reporting\ReportingAccess;
use App\Services\Records\DocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportingController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(
        protected AcademicReportService $service,
        protected ReportCompiler $compiler,
        protected AcademicReportSettings $settings,
        protected TeacherScope $scope,
    ) {}

    public function index(Request $request): Response
    {
        $this->assertView();
        $this->settings->ensureTemplates();
        $user = $request->user();
        if ($user->isFamilyPortal()) {
            return $this->familyHome($request);
        }
        $year = $this->year((int) $request->query('academic_year_id'));
        $termId = $request->integer('term_id') ?: null;
        $classId = $request->integer('class_id') ?: null;

        $base = AcademicReport::query()->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId));
        $this->restrict($base, $user);

        $kpis = [
            'students' => $this->studentQuery($user)->where('status', 'active')->count(),
            'pending' => (clone $base)->whereIn('status', ['draft'])->count(),
            'awaiting_approval' => (clone $base)->whereIn('status', ['submitted', 'in_review'])->count(),
            'published' => (clone $base)->where('status', 'published')->count(),
            'generated' => (clone $base)->count(),
        ];

        $classOverview = SchoolClass::query()->with('level')->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->when(! $user->can('exams.manage') && ! $user->isFamilyPortal(), fn ($q) => $q->whereIn('id', $this->scope->classIds($user)))
            ->orderBy('name')->limit(12)->get()
            ->map(function (SchoolClass $c) use ($year) {
                $avg = AcademicReport::query()->where('school_class_id', $c->id)->where('status', 'published')->where('type', 'termly')
                    ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                    ->avg('overall_average');

                return [
                    'id' => $c->id, 'name' => $c->name, 'level' => $c->level?->name,
                    'students' => $c->students_count,
                    'average' => $avg !== null ? round((float) $avg, 1) : null,
                ];
            });

        $recent = $this->listQuery($request, null)->orderByDesc('id')->limit(12)->get()->map(fn ($r) => $this->row($r));

        return Inertia::render('Reporting/Dashboard', $this->page('dashboard') + [
            'kpis' => $kpis,
            'classOverview' => $classOverview,
            'recent' => $recent,
            'filters' => $this->filterPayload($request, $year, $termId, $classId),
        ]);
    }

    public function termly(Request $request): Response
    {
        $this->assertStaffSurface();

        return $this->listing($request, 'termly');
    }

    public function annual(Request $request): Response
    {
        $this->assertStaffSurface();

        return $this->listing($request, 'annual');
    }

    public function finalReports(Request $request): Response
    {
        $this->assertStaffSurface();

        return $this->listing($request, 'final');
    }

    public function approval(Request $request): Response
    {
        abort_unless($request->user()->can('exams.manage') || $request->user()->can('results.publish'), 403);
        $request->merge(['status' => $request->query('status', 'submitted')]);

        return $this->listing($request, null, 'approval');
    }

    public function register(Request $request): Response
    {
        abort_unless($request->user()->can('exams.manage') || $request->user()->can('results.publish'), 403);

        return $this->listing($request, null, 'register');
    }

    public function generate(Request $request): Response
    {
        $this->assertStaffSurface();
        abort_unless(ReportingAccess::can($request->user())['prepare'], 403);
        $year = $this->year((int) $request->query('academic_year_id'));
        $type = (string) $request->query('type', 'termly');
        if (! in_array($type, AcademicReport::TYPES, true)) {
            $type = 'termly';
        }
        $term = $request->integer('term_id') ? Term::query()->find($request->integer('term_id')) : $this->currentTerm($year);
        $class = $request->integer('class_id') ? SchoolClass::query()->with('level')->find($request->integer('class_id')) : null;
        $preview = [];
        if ($class && $year && ($type !== 'termly' || $term)) {
            abort_unless($this->scope->canAccessClass($request->user(), (int) $class->id, 'exams.manage') || $request->user()->can('exams.manage'), 403);
            $preview = $this->compiler->previewClass($class, $type, $year, $term)->all();
        }

        return Inertia::render('Reporting/Generate', $this->page('termly') + [
            'type' => $type,
            'preview' => $preview,
            'selectedClass' => $class?->id,
            'selectedTerm' => $term?->id,
            'filters' => $this->filterPayload($request, $year, $term?->id, $class?->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', 'in:termly,annual,final'],
            'academic_year_id' => ['required', $this->inSchool('academic_years')],
            'term_id' => ['nullable', $this->inSchool('terms')],
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => ['integer', $this->inSchool('students')],
            'class_teacher_comment' => ['nullable', 'string', 'max:2000'],
            'headteacher_comment' => ['nullable', 'string', 'max:2000'],
            'behaviour' => ['nullable', 'string', 'max:40'],
            'participation' => ['nullable', 'string', 'max:40'],
            'promotion_status' => ['nullable', 'string', 'max:40'],
            'allow_incomplete' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(function () use ($v, $request) {
            $year = AcademicYear::query()->findOrFail($v['academic_year_id']);
            $term = ! empty($v['term_id']) ? Term::query()->findOrFail($v['term_id']) : null;
            $extras = collect($v)->only(['class_teacher_comment', 'headteacher_comment', 'behaviour', 'participation', 'promotion_status', 'allow_incomplete'])->all();
            $first = null;
            foreach (Student::query()->whereIn('id', $v['student_ids'])->get() as $student) {
                $created = $this->service->generate($request->user(), $student, $v['type'], $year, $term, $extras);
                $first ??= $created;
            }

            return $first
                ? redirect()->route('reporting.show', $first)->with('success', 'ok.rpt.generated')
                : back()->with('success', 'ok.rpt.generated');
        });
    }

    public function show(AcademicReport $report): Response
    {
        abort_unless($this->service->canView(auth()->user(), $report), 403);
        $report->load(['student:id,first_name,last_name,student_number,school_class_id,photo_path', 'academicYear:id,name', 'term:id,name,sequence', 'events.actor:id,name', 'preparer:id,name']);

        return Inertia::render('Reporting/Show', array_merge($this->page($report->type === 'final' ? 'final' : $report->type), [
            'report' => $this->detail($report),
            'events' => $report->events->map(fn ($e) => [
                'id' => $e->id, 'event' => $e->event, 'comment' => $e->comment,
                'user' => $e->actor?->name, 'at' => $e->created_at?->toDateTimeString(),
            ]),
            'can' => $this->service->flags(auth()->user(), $report),
            'disclaimer' => AcademicReportPdf::DISCLAIMER,
        ]));
    }

    public function preview(AcademicReport $report): Response
    {
        abort_unless($this->service->canView(auth()->user(), $report), 403);

        return Inertia::render('Reporting/Preview', $this->page($report->type) + [
            'report' => $this->detail($report),
            'disclaimer' => AcademicReportPdf::DISCLAIMER,
        ]);
    }

    public function pdf(AcademicReport $report, AcademicReportPdf $pdf): HttpResponse
    {
        abort_unless($this->service->canView(auth()->user(), $report), 403);
        $bytes = $pdf->render($report);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$report->reference.'.pdf"',
        ]);
    }

    public function update(Request $request, AcademicReport $report): RedirectResponse
    {
        $v = $request->validate([
            'class_teacher_comment' => ['nullable', 'string', 'max:2000'],
            'headteacher_comment' => ['nullable', 'string', 'max:2000'],
            'behaviour' => ['nullable', 'string', 'max:40'],
            'participation' => ['nullable', 'string', 'max:40'],
            'promotion_status' => ['nullable', 'string', 'max:40'],
            'promotion_decision' => ['nullable', 'string', 'max:40'],
            'allow_incomplete' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(fn () => $this->service->updateDraft($request->user(), $report, $v), 'ok.saved');
    }

    public function submit(Request $request, AcademicReport $report): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->submit($request->user(), $report), 'ok.rpt.submitted');
    }

    public function review(Request $request, AcademicReport $report): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->startReview($request->user(), $report), 'ok.rpt.review');
    }

    public function approve(Request $request, AcademicReport $report): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->approve($request->user(), $report), 'ok.rpt.approved');
    }

    public function returnToDraft(Request $request, AcademicReport $report): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->returnToDraft($request->user(), $report, $v['reason']), 'ok.rpt.returned');
    }

    public function publish(Request $request, AcademicReport $report): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->publish($request->user(), $report), 'ok.rpt.published');
    }

    public function revise(Request $request, AcademicReport $report): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(function () use ($request, $report, $v) {
            $next = $this->service->revise($request->user(), $report, $v['reason']);

            return redirect()->route('reporting.show', $next)->with('success', 'ok.rpt.revised');
        });
    }

    public function transcripts(Request $request): Response
    {
        $this->assertStaffSurface();
        $this->assertView();
        $q = trim((string) $request->query('q'));
        $types = ['transcript_g9', 'transcript_g12'];
        $docs = RecordsDocument::query()->with('student:id,first_name,last_name,student_number')
            ->whereIn('type', $types)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('reference', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%")))
            ->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (RecordsDocument $d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'type' => $d->type,
                'status' => $d->status,
                'title' => $d->title,
                'student' => $d->student?->full_name,
                'student_number' => $d->student?->student_number,
                'issue_date' => $d->issue_date?->toDateString(),
                'show_url' => route('records.show', $d),
            ]);

        return Inertia::render('Reporting/Transcripts', $this->page('transcripts') + [
            'documents' => $docs,
            'q' => $q,
            'disclaimer' => DocumentPdf::DISCLAIMER,
        ]);
    }

    protected function listing(Request $request, ?string $type, string $current = ''): Response
    {
        $this->assertView();
        $current = $current ?: ($type ?? 'dashboard');
        $year = $this->year((int) $request->query('academic_year_id'));
        $reports = $this->listQuery($request, $type)
            ->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (AcademicReport $r) => $this->row($r));

        return Inertia::render('Reporting/Index', $this->page($current) + [
            'reports' => $reports,
            'type' => $type,
            'filters' => $this->filterPayload($request, $year, $request->integer('term_id') ?: null, $request->integer('class_id') ?: null),
        ]);
    }

    protected function listQuery(Request $request, ?string $type)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');
        $yearId = $request->integer('academic_year_id') ?: null;
        $termId = $request->integer('term_id') ?: null;
        $classId = $request->integer('class_id') ?: null;
        $levelId = $request->integer('level_id') ?: null;

        $query = AcademicReport::query()->with(['student:id,first_name,last_name,student_number', 'schoolClass:id,name', 'academicYear:id,name', 'term:id,name'])
            ->when($type, fn ($w) => $w->where('type', $type))
            ->when($status !== '' && in_array($status, AcademicReport::STATUSES, true), fn ($w) => $w->where('status', $status))
            ->when($yearId, fn ($w) => $w->where('academic_year_id', $yearId))
            ->when($termId, fn ($w) => $w->where('term_id', $termId))
            ->when($classId, fn ($w) => $w->where('school_class_id', $classId))
            ->when($levelId, fn ($w) => $w->whereHas('schoolClass', fn ($c) => $c->where('level_id', $levelId)))
            ->when($q !== '', function ($w) use ($q) {
                $w->where(fn ($x) => $x->where('reference', 'like', "%{$q}%")
                    ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('student_number', 'like', "%{$q}%")));
            });

        $this->restrict($query, $user);

        return $query;
    }

    protected function restrict($query, $user): void
    {
        if ($user->isFamilyPortal()) {
            $ids = $this->familyStudentIds($user);
            $query->where('status', 'published')->whereIn('student_id', $ids ?: [0]);

            return;
        }
        if (! $user->can('exams.manage') && ! $user->can('results.publish')) {
            $query->whereIn('school_class_id', $this->scope->classIds($user)->all() ?: [0]);
        }
    }

    protected function familyHome(Request $request): Response
    {
        $user = $request->user();
        $year = $this->year((int) $request->query('academic_year_id'));
        $term = $request->integer('term_id')
            ? Term::query()->find($request->integer('term_id'))
            : $this->currentTerm($year);
        if ($term && $year && (int) $term->academic_year_id !== (int) $year->id) {
            $term = $this->currentTerm($year);
        }

        $students = Student::query()->with('schoolClass:id,name')
            ->whereIn('id', $this->familyStudentIds($user) ?: [0])
            ->orderBy('first_name')->orderBy('last_name')->get();

        $learners = $students->map(function (Student $student) use ($year, $term) {
            $reports = AcademicReport::query()
                ->with(['academicYear:id,name', 'term:id,name', 'schoolClass:id,name'])
                ->where('student_id', $student->id)
                ->where('status', 'published')
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->orderByDesc('id')
                ->get()
                ->map(fn (AcademicReport $r) => $this->row($r))
                ->values();

            return [
                'id' => $student->id,
                'name' => $student->full_name,
                'number' => $student->student_number,
                'class' => $student->schoolClass?->name,
                'photo_url' => $student->photo_path ? route('students.photo', $student) : null,
                'results' => $term ? $this->compiler->publishedTermResults($student, $term) : null,
                'reports' => $reports,
            ];
        })->values();

        return Inertia::render('Reporting/Family', $this->page('dashboard') + [
            'learners' => $learners,
            'isLearner' => $user->isLearnerPortal(),
            'termName' => $term?->name,
            'disclaimer' => AcademicReportPdf::DISCLAIMER,
            'filters' => [
                'academic_year_id' => $year?->id,
                'term_id' => $term?->id,
                'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
                'terms' => $year
                    ? Term::query()->where('academic_year_id', $year->id)->orderBy('sequence')->get(['id', 'name', 'sequence'])
                    : collect(),
            ],
        ]);
    }

    protected function assertStaffSurface(): void
    {
        abort_if(auth()->user()?->isFamilyPortal(), 403);
    }

    protected function familyStudentIds($user): array
    {
        if ($user->isLearnerPortal()) {
            return array_filter([(int) $user->studentProfile?->id]);
        }
        $guardian = $user->guardianProfile;

        return $guardian ? $guardian->students()->pluck('students.id')->map(fn ($id) => (int) $id)->all() : [];
    }

    protected function studentQuery($user)
    {
        $q = Student::query();
        if ($user->isFamilyPortal()) {
            return $q->whereIn('id', $this->familyStudentIds($user) ?: [0]);
        }
        if (! $user->can('exams.manage')) {
            $q->whereIn('school_class_id', $this->scope->classIds($user)->all() ?: [0]);
        }

        return $q;
    }

    protected function row(AcademicReport $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'type' => $r->type,
            'status' => $r->status,
            'version' => $r->version,
            'student' => $r->student?->full_name,
            'student_number' => $r->student?->student_number,
            'photo_url' => $r->student?->photo_path ? route('students.photo', $r->student) : null,
            'class' => $r->schoolClass?->name,
            'academic_year' => $r->academicYear?->name,
            'term' => $r->term?->name,
            'average' => $r->overall_average !== null ? (float) $r->overall_average : null,
            'grade' => $r->overall_grade,
            'incomplete' => $r->incomplete,
            'generated_at' => $r->created_at?->toDateString(),
            'published_at' => $r->published_at?->toDateString(),
            'issue_date' => $r->issue_date?->toDateString(),
        ];
    }

    protected function detail(AcademicReport $r): array
    {
        return $this->row($r) + [
            'payload' => $r->payload,
            'class_teacher_comment' => $r->class_teacher_comment,
            'headteacher_comment' => $r->headteacher_comment,
            'behaviour' => $r->behaviour,
            'participation' => $r->participation,
            'promotion_status' => $r->promotion_status,
            'promotion_decision' => $r->promotion_decision,
            'allow_incomplete' => $r->allow_incomplete,
            'revision_reason' => $r->revision_reason,
            'prepared_by' => $r->preparer?->name,
            'verification_url' => in_array($r->status, ['published', 'superseded'], true) ? $this->service->verificationUrl($r) : null,
            'pdf_url' => route('reporting.pdf', $r),
            'preview_url' => route('reporting.preview', $r),
        ];
    }

    protected function page(string $current): array
    {
        $user = auth()->user();

        return $this->shellProps() + [
            'current' => $current,
            'tabs' => ReportingAccess::tabs($user),
            'can' => ReportingAccess::can($user),
        ];
    }

    protected function filterPayload(Request $request, ?AcademicYear $year, ?int $termId, ?int $classId): array
    {
        $user = $request->user();
        $years = AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']);
        $terms = $year ? Term::query()->where('academic_year_id', $year->id)->orderBy('sequence')->get(['id', 'name', 'sequence']) : collect();
        $classes = SchoolClass::query()->with('level:id,name')
            ->when(! $user->can('exams.manage') && ! $user->isFamilyPortal(), fn ($q) => $q->whereIn('id', $this->scope->classIds($user)))
            ->orderBy('name')->get()
            ->map(fn (SchoolClass $c) => ['id' => $c->id, 'name' => $c->name, 'level_id' => $c->level_id, 'level' => $c->level?->name]);
        $levels = Level::query()->orderBy('sort_order')->get(['id', 'name', 'grade_number']);

        return [
            'academic_year_id' => $year?->id,
            'term_id' => $termId,
            'class_id' => $classId,
            'level_id' => $request->integer('level_id') ?: null,
            'status' => (string) $request->query('status', ''),
            'q' => (string) $request->query('q', ''),
            'years' => $years,
            'terms' => $terms,
            'classes' => $classes,
            'levels' => $levels,
        ];
    }

    protected function year(?int $id): ?AcademicYear
    {
        if ($id) {
            return AcademicYear::query()->findOrFail($id);
        }

        return AcademicYear::query()->where('is_current', true)->first()
            ?? AcademicYear::query()->orderByDesc('starts_on')->first();
    }

    protected function currentTerm(?AcademicYear $year): ?Term
    {
        if (! $year) {
            return null;
        }

        return Term::query()->where('academic_year_id', $year->id)->where('is_current', true)->first()
            ?? Term::query()->where('academic_year_id', $year->id)->orderBy('sequence')->first();
    }

    protected function assertView(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->can('exams.view') || $user->can('exams.mark') || $user->can('exams.manage') || $user->can('students.view')), 403);
    }
}

