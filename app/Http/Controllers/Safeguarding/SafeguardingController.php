<?php

namespace App\Http\Controllers\Safeguarding;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\SafeguardingCase;
use App\Models\SafeguardingNote;
use App\Models\Student;
use App\Models\User;
use App\Services\Safeguarding\SafeguardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Child-protection cases. Lists never include the summary. Opening a case is logged.
 * Anyone with safeguarding.report may file a concern; only safeguarding.manage may read and work a case.
 */
class SafeguardingController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('safeguarding.manage'), 403);

        $status = (string) $request->query('status', 'open');
        $cases = SafeguardingCase::query()
            ->with(['student:id,first_name,last_name', 'assignee:id,name'])
            ->when(in_array($status, ['open', 'investigating', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->latest()->limit(80)->get()
            ->map(fn (SafeguardingCase $c) => [
                'id' => $c->id, 'reference' => $c->reference, 'category' => $c->category, 'severity' => $c->severity, 'status' => $c->status,
                'student' => $c->student?->full_name, 'assignee' => $c->assignee?->name, 'created_at' => $c->created_at?->toDateString(),
            ]);

        return Inertia::render('Safeguarding/Index', array_merge($this->shellProps(), [
            'cases' => $cases,
            'filter' => $status,
            'stats' => [
                'open' => SafeguardingCase::query()->where('status', '!=', 'closed')->count(),
                'critical' => SafeguardingCase::query()->where('severity', 'critical')->where('status', '!=', 'closed')->count(),
            ],
        ]));
    }

    public function reportForm(Request $request): Response
    {
        abort_unless($request->user()->canAny(['safeguarding.report', 'safeguarding.manage']), 403);

        return Inertia::render('Safeguarding/Report', array_merge($this->shellProps(), [
            'students' => $request->user()->can('students.view')
                ? Student::query()->where('status', 'active')->orderBy('last_name')->limit(400)->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name])
                : [],
            'categories' => SafeguardingService::CATEGORIES,
            'severities' => SafeguardingService::SEVERITIES,
            'canManage' => $request->user()->can('safeguarding.manage'),
        ]));
    }

    /** Kept from the original foundation; also reachable as the report form's submit. */
    public function store(Request $request, SafeguardingService $service): RedirectResponse
    {
        abort_unless($request->user()->canAny(['safeguarding.report', 'safeguarding.manage']), 403);
        $v = $this->caseRules($request);

        return $this->attempt(function () use ($v, $request, $service) {
            $case = $service->open($request->user(), $v);

            return $request->user()->can('safeguarding.manage')
                ? redirect()->route('safeguarding.show', $case)->with('success', 'ok.sg.opened')
                : redirect()->route('safeguarding.report')->with('success', 'ok.sg.reported');
        });
    }

    public function show(Request $request, SafeguardingCase $case, SafeguardingService $service): Response
    {
        abort_unless($request->user()->can('safeguarding.manage'), 403);
        $service->logAccess($case, $request->user());
        $case->load(['student:id,first_name,last_name', 'reporter:id,name', 'assignee:id,name', 'notes.author:id,name']);

        return Inertia::render('Safeguarding/Show', array_merge($this->shellProps(), [
            'case' => [
                'id' => $case->id, 'reference' => $case->reference, 'category' => $case->category, 'severity' => $case->severity, 'status' => $case->status,
                'student_id' => $case->student_id, 'student' => $case->student?->full_name, 'subject_name' => $case->subject_name, 'summary' => $case->summary,
                'occurred_on' => $case->occurred_on?->toDateString(), 'reporter' => $case->reporter?->name, 'assignee' => $case->assignee?->name, 'assigned_to' => $case->assigned_to,
                'created_at' => $case->created_at?->toDateString(), 'closed_at' => $case->closed_at?->toDateString(), 'closure_note' => $case->closure_note,
                'notes' => $case->notes->map(fn (SafeguardingNote $n) => [
                    'id' => $n->id, 'body' => $n->body, 'author' => $n->author?->name, 'at' => $n->created_at?->toDateTimeString(),
                ]),
            ],
            'officers' => User::query()->where('school_id', $this->schoolId())->orderBy('name')->get()->filter(fn (User $u) => $u->can('safeguarding.manage'))->values()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
        ]));
    }

    public function assign(Request $request, SafeguardingCase $case, SafeguardingService $service): RedirectResponse
    {
        abort_unless($request->user()->can('safeguarding.manage'), 403);
        $v = $request->validate(['assigned_to' => ['required', 'integer']]);

        return $this->attempt(fn () => $service->assign($case, (int) $v['assigned_to']), 'ok.sg.assigned');
    }

    public function addNote(Request $request, SafeguardingCase $case, SafeguardingService $service): RedirectResponse
    {
        abort_unless($request->user()->can('safeguarding.manage'), 403);
        $v = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        return $this->attempt(fn () => $service->addNote($case, $request->user(), $v['body']), 'ok.sg.noteAdded');
    }

    public function close(Request $request, SafeguardingCase $case, SafeguardingService $service): RedirectResponse
    {
        abort_unless($request->user()->can('safeguarding.manage'), 403);
        $v = $request->validate(['closure_note' => ['required', 'string', 'max:5000']]);

        return $this->attempt(fn () => $service->close($case, $request->user(), $v['closure_note']), 'ok.sg.closed');
    }

    private function caseRules(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(SafeguardingService::CATEGORIES)],
            'severity' => ['required', Rule::in(SafeguardingService::SEVERITIES)],
            'student_id' => ['nullable', $this->inSchool('students')],
            'subject_name' => ['nullable', 'string', 'max:150'],
            'summary' => ['required', 'string', 'max:5000'],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
    }
}
