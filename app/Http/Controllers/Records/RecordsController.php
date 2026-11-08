<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\RecordsDocument;
use App\Models\RecordsTemplate;
use App\Models\Student;
use App\Services\Records\DocumentPdf;
use App\Services\Records\DocumentService;
use App\Services\Records\TranscriptCompiler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordsController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected DocumentService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $types = $this->visibleTypes();
        $type = (string) $request->query('type', '');
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q'));

        $docs = RecordsDocument::query()->with('student:id,first_name,last_name,student_number')->whereIn('type', $types)
            ->when($type !== '' && in_array($type, $types, true), fn ($w) => $w->where('type', $type))
            ->when($status !== '', fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('reference', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%")))
            ->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (RecordsDocument $d) => $this->row($d));

        return Inertia::render('Records/Index', $this->shellProps() + [
            'documents' => $docs, 'types' => $types, 'type' => $type, 'status' => $status, 'q' => $q,
            'can' => ['draft' => $user->can('records.manage'), 'issue' => $user->can('records.issue')],
        ]);
    }

    public function create(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $students = $q === '' ? collect() : Student::query()->with('schoolClass')
            ->where(fn ($w) => $w->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('student_number', 'like', "%{$q}%"))
            ->orderBy('first_name')->limit(25)->get()
            ->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->full_name, 'student_number' => $s->student_number, 'class' => $s->schoolClass?->name, 'status' => $s->status]);

        return Inertia::render('Records/Create', $this->shellProps() + [
            'students' => $students, 'q' => $q,
            'types' => array_values(array_intersect(['transcript_g9', 'transcript_g12', 'testimonial', 'cover_letter'], $this->visibleTypes())),
            'templates' => RecordsTemplate::query()->where('is_active', true)->whereIn('type', ['testimonial', 'cover_letter'])->orderBy('name')->get(['id', 'type', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', 'in:transcript_g9,transcript_g12,testimonial,cover_letter'],
            'student_id' => ['required', 'integer', $this->inSchool('students')],
            'template_id' => ['nullable', 'integer', $this->inSchool('records_templates')],
            'custom_text' => ['nullable', 'string', 'max:3000'],
        ]);

        return $this->attempt(function () use ($v, $request) {
            $student = Student::query()->findOrFail($v['student_id']);
            $doc = in_array($v['type'], TranscriptCompiler::TYPES, true)
                ? $this->service->createTranscript($request->user(), $student, $v['type'])
                : $this->service->createLetter($request->user(), $student, $v['type'], ! empty($v['template_id']) ? RecordsTemplate::query()->find($v['template_id']) : null, $v['custom_text'] ?? null);

            return redirect()->route('records.show', $doc)->with('success', 'ok.rec.created');
        });
    }

    public function show(RecordsDocument $document): Response
    {
        $this->authorizeView($document);
        $document->load(['student:id,first_name,last_name,student_number', 'events.user:id,name', 'preparer:id,name']);
        $user = auth()->user();
        $isLetter = in_array($document->type, DocumentService::LETTERS, true);
        $payload = (array) $document->payload;

        return Inertia::render('Records/Show', $this->shellProps() + [
            'document' => $this->row($document) + [
                'body' => $document->body, 'payload' => $isLetter ? null : $payload,
                'exception' => $document->exception, 'reprint_count' => $document->reprint_count, 'revoked_reason' => $document->revoked_reason,
                'issue_date' => $document->issue_date?->toDateString(), 'prepared_by' => $document->preparer?->name,
                'is_letter' => $isLetter, 'student_id' => $document->student_id,
                'verification_url' => in_array($document->status, ['issued', 'reprinted', 'revoked'], true) ? $this->service->verificationUrl($document) : null,
                'disclaimer' => DocumentPdf::DISCLAIMER,
            ],
            'events' => $document->events->map(fn ($e) => ['id' => $e->id, 'event' => $e->event, 'note' => $e->note, 'user' => $e->user?->name, 'at' => $e->created_at?->toDateTimeString()]),
            'can' => $this->workflowFlags($document, $user),
        ]);
    }

    public function update(Request $request, RecordsDocument $document): RedirectResponse
    {
        $v = $request->validate(['title' => ['nullable', 'string', 'max:200'], 'body' => ['required', 'string', 'max:6000']]);

        return $this->attempt(fn () => $this->service->updateDraft($request->user(), $document, $v['body'], $v['title'] ?? null));
    }

    public function recompile(Request $request, RecordsDocument $document): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->recompile($request->user(), $document), 'ok.rec.recompiled');
    }

    public function exception(Request $request, RecordsDocument $document): RedirectResponse
    {
        $v = $request->validate([
            'include_grade12_term3' => ['nullable', 'boolean'], 'allow_incomplete' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->attempt(fn () => $this->service->grantException($request->user(), $document, (bool) ($v['include_grade12_term3'] ?? false), (bool) ($v['allow_incomplete'] ?? false), $v['reason']), 'ok.rec.exception');
    }

    public function submit(Request $request, RecordsDocument $document): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->submit($request->user(), $document), 'ok.rec.submitted');
    }

    public function returnToDraft(Request $request, RecordsDocument $document): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->returnToDraft($request->user(), $document, $v['reason']), 'ok.rec.returned');
    }

    public function approve(Request $request, RecordsDocument $document): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->approve($request->user(), $document), 'ok.rec.approved');
    }

    public function issue(Request $request, RecordsDocument $document): RedirectResponse
    {
        $v = $request->validate(['issue_date' => ['nullable', 'date']]);

        return $this->attempt(fn () => $this->service->issue($request->user(), $document, $v['issue_date'] ?? null), 'ok.rec.issued');
    }

    public function reprint(Request $request, RecordsDocument $document): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->reprint($request->user(), $document, $v['reason']), 'ok.rec.reprinted');
    }

    public function revoke(Request $request, RecordsDocument $document): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->revoke($request->user(), $document, $v['reason']), 'ok.rec.revoked');
    }

    public function pdf(DocumentPdf $pdf, RecordsDocument $document)
    {
        $this->authorizeView($document);
        $bytes = $pdf->render($document);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->reference.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /** @return list<string> */
    protected function visibleTypes(): array
    {
        $u = auth()->user();
        $types = [];
        if ($u->can('records.manage')) {
            $types = ['transcript_g9', 'transcript_g12', 'testimonial', 'cover_letter'];
        }
        if ($u->can('senior_admissions.view')) {
            $types[] = 'admission_letter';
        }

        return $types;
    }

    protected function authorizeView(RecordsDocument $document): void
    {
        abort_unless(in_array($document->type, $this->visibleTypes(), true), 403);
    }

    protected function row(RecordsDocument $d): array
    {
        return [
            'id' => $d->id, 'type' => $d->type, 'reference' => $d->reference, 'title' => $d->title, 'status' => $d->status,
            'student' => $d->student?->full_name, 'student_number' => $d->student?->student_number,
            'issue_date' => $d->issue_date?->toDateString(), 'created_at' => $d->created_at?->toDateString(),
        ];
    }

    protected function workflowFlags(RecordsDocument $d, $user): array
    {
        $letterOfAdmission = $d->type === 'admission_letter';
        $draft = $user->can($letterOfAdmission ? 'senior_admissions.manage' : 'records.manage');
        $issue = $user->can($letterOfAdmission ? 'senior_admissions.manage' : 'records.issue');

        return [
            'edit' => $draft && $d->status === 'draft',
            'submit' => $draft && $d->status === 'draft',
            'exception' => $issue && ! $letterOfAdmission && in_array($d->status, ['draft', 'submitted'], true) && in_array($d->type, TranscriptCompiler::TYPES, true),
            'approve' => $issue && $d->status === 'submitted',
            'return' => $issue && in_array($d->status, ['submitted', 'approved'], true),
            'issue' => $issue && $d->status === 'approved',
            'reprint' => $draft && in_array($d->status, ['issued', 'reprinted'], true),
            'revoke' => $issue && in_array($d->status, ['approved', 'issued', 'reprinted'], true),
            'history' => $user->can('records.manage') && $d->student_id && in_array($d->type, TranscriptCompiler::TYPES, true),
        ];
    }
}
