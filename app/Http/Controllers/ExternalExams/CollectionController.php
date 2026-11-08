<?php

namespace App\Http\Controllers\ExternalExams;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\CertificateRecord;
use App\Models\ExternalCandidate;
use App\Models\ExternalExamSession;
use App\Models\ResultCollection;
use App\Services\ExternalExams\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Physical results collection register (Part 9.3), certificate tracking (Part 9.4) and the printable register. */
class CollectionController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected CollectionService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('ExternalExams/Collections', $this->shellProps() + $this->data($request, 20) + [
            'can' => ['manage' => $request->user()->can('external_exams.manage')],
            'estimateText' => config('schoolos.external_exams.certificate_estimate_text'),
            'certificateStatuses' => CollectionService::CERT_STATUSES,
        ]);
    }

    public function print(Request $request): Response
    {
        return Inertia::render('ExternalExams/CollectionPrint', $this->data($request, 1000, false) + [
            'printedAt' => now()->format('Y-m-d H:i'),
            'printedBy' => $request->user()->name,
            'school' => ['name' => app(\App\Support\TenantContext::class)->school()?->name],
        ]);
    }

    public function received(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        $v = $request->validate(['received_on' => ['required', 'date']]);

        return $this->attempt(fn () => $this->service->markReceived($request->user(), $candidate, $v['received_on']), 'ok.col.received');
    }

    public function collect(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        $v = $request->validate($this->collectorRules());

        return $this->attempt(fn () => $this->service->collect($request->user(), $candidate, $v), 'ok.col.collected');
    }

    public function certificate(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        $v = $request->validate($this->collectorRules(false) + [
            'status' => ['required', 'in:'.implode(',', CollectionService::CERT_STATUSES)],
            'received_on' => ['nullable', 'date'], 'available_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $status = $v['status'];
        unset($v['status']);

        return $this->attempt(fn () => $this->service->updateCertificate($request->user(), $candidate, $status, $v), 'ok.cert.updated');
    }

    protected function collectorRules(bool $required = true): array
    {
        return [
            'collected_on' => [$required ? 'required' : 'nullable', 'date'],
            'collector_name' => [$required ? 'required' : 'nullable', 'string', 'max:150'],
            'collector_relationship' => ['nullable', 'string', 'max:80'],
            'collector_id_type' => ['nullable', 'string', 'max:40'],
            'collector_id_number' => ['nullable', 'string', 'max:60'],
            'acknowledgement' => [$required ? 'required' : 'nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string,mixed> */
    protected function data(Request $request, int $perPage, bool $paginate = true): array
    {
        $sessions = ExternalExamSession::query()->orderByDesc('exam_year')->orderBy('type')->get(['id', 'type', 'name', 'exam_year']);
        $sessionId = (int) $request->query('session', $sessions->first()?->id);
        $filter = (string) $request->query('status', '');
        $tab = $request->query('tab') === 'certificates' ? 'certificates' : 'results';

        // The register lists every active candidate; the official index number is shown when it has been recorded.
        $query = ExternalCandidate::query()->with(['student:id,first_name,last_name,student_number', 'collection.staff:id,name', 'certificate.staff:id,name'])
            ->where('session_id', $sessionId)->where('status', '!=', 'withdrawn')->orderBy('id')
            ->when($filter !== '' && $tab === 'results', function ($q) use ($filter) {
                $filter === 'outstanding'
                    ? $q->where(fn ($w) => $w->whereDoesntHave('collection')->orWhereHas('collection', fn ($c) => $c->where('status', 'outstanding')))
                    : $q->whereHas('collection', fn ($c) => $c->where('status', $filter));
            })
            ->when($filter !== '' && $tab === 'certificates', function ($q) use ($filter) {
                $filter === 'awaiting'
                    ? $q->where(fn ($w) => $w->whereDoesntHave('certificate')->orWhereHas('certificate', fn ($c) => $c->where('status', 'awaiting')))
                    : $q->whereHas('certificate', fn ($c) => $c->where('status', $filter));
            });

        $map = fn (ExternalCandidate $c) => [
            'id' => $c->id, 'name' => $c->student?->full_name, 'student_number' => $c->student?->student_number,
            'internal_reference' => $c->internal_reference, 'official_index_number' => $c->official_index_number,
            'collection' => $c->collection ? $this->row($c->collection, true) : null,
            'certificate' => $c->certificate ? $this->row($c->certificate, false) : null,
        ];

        $rows = $paginate ? $query->paginate($perPage)->withQueryString()->through($map) : $query->limit($perPage)->get()->map($map)->values();

        return [
            'sessions' => $sessions, 'sessionId' => $sessionId, 'rows' => $rows, 'status' => $filter, 'tab' => $tab,
            'summary' => [
                'results_outstanding' => ResultCollection::query()->where('status', 'outstanding')->whereIn('candidate_id', ExternalCandidate::query()->where('session_id', $sessionId)->select('id'))->count(),
                'results_collected' => ResultCollection::query()->where('status', 'collected')->whereIn('candidate_id', ExternalCandidate::query()->where('session_id', $sessionId)->select('id'))->count(),
                'certificates_waiting' => CertificateRecord::query()->whereIn('status', ['available', 'outstanding'])->whereIn('candidate_id', ExternalCandidate::query()->where('session_id', $sessionId)->select('id'))->count(),
            ],
        ];
    }

    protected function row(ResultCollection|CertificateRecord $r, bool $isResults): array
    {
        $number = $r->collector_id_number;

        return [
            'status' => $r->status,
            'received_on' => $r->received_on?->toDateString(),
            'available_on' => $isResults ? null : $r->available_on?->toDateString(),
            'collected_on' => $r->collected_on?->toDateString(),
            'collector_name' => $r->collector_name, 'collector_relationship' => $r->collector_relationship,
            'collector_id_type' => $r->collector_id_type,
            // Only the last 3 characters of an identity number are ever sent to the browser.
            'collector_id_masked' => $number ? str_repeat('•', max(0, mb_strlen($number) - 3)).mb_substr($number, -3) : null,
            'staff' => $r->staff?->name, 'acknowledgement' => $r->acknowledgement, 'reminders_sent' => $r->reminders_sent,
        ];
    }
}
