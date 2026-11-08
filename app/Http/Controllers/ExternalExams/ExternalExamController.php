<?php

namespace App\Http\Controllers\ExternalExams;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ExternalCandidate;
use App\Models\ExternalCandidateDocument;
use App\Models\ExternalExamSession;
use App\Models\ExternalExamSubject;
use App\Models\ExternalResult;
use App\Models\ImportBatch;
use App\Models\Student;
use App\Services\ExternalExams\ExternalExamService;
use App\Services\ExternalExams\ExternalResultImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExternalExamController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public const DOCUMENT_TYPES = ['registration_form', 'birth_certificate', 'photo', 'other'];

    public function __construct(protected ExternalExamService $service) {}

    public function index(): Response
    {
        $sessions = ExternalExamSession::query()->withCount('candidates')
            ->orderByDesc('exam_year')->orderBy('type')->get()
            ->map(fn (ExternalExamSession $s) => [
                'id' => $s->id, 'type' => $s->type, 'name' => $s->name, 'exam_year' => $s->exam_year, 'status' => $s->status,
                'candidates_count' => $s->candidates_count,
                'registration_opens_on' => $s->registration_opens_on?->toDateString(),
                'registration_closes_on' => $s->registration_closes_on?->toDateString(),
            ]);

        return Inertia::render('ExternalExams/Index', $this->shellProps() + [
            'sessions' => $sessions,
            'can' => $this->can(),
            'estimateText' => config('schoolos.external_exams.certificate_estimate_text'),
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', 'in:gabece,wassce'],
            'name' => ['required', 'string', 'max:150'],
            'exam_year' => ['required', 'integer', 'between:2000,2100'],
            'registration_opens_on' => ['nullable', 'date'],
            'registration_closes_on' => ['nullable', 'date', 'after_or_equal:registration_opens_on'],
        ]);

        return $this->attempt(fn () => $this->service->createSession($v), 'ok.ext.sessionCreated');
    }

    public function updateSession(Request $request, ExternalExamSession $session): RedirectResponse
    {
        $v = $request->validate(['status' => ['required', 'in:open,closed']]);

        return $this->attempt(fn () => $this->service->setSessionStatus($session, $v['status']), 'ok.saved');
    }

    public function show(Request $request, ExternalExamSession $session): Response
    {
        $q = trim((string) $request->query('q'));
        $candidates = ExternalCandidate::query()->with('student:id,first_name,last_name,student_number')
            ->withCount(['subjectLinks as subjects_count', 'results as results_count'])
            ->where('session_id', $session->id)
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('internal_reference', 'like', "%{$q}%")->orWhere('official_index_number', 'like', "%{$q}%")
                        ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('student_number', 'like', "%{$q}%"));
                });
            })
            ->orderBy('id')->paginate(25)->withQueryString();

        $statusCounts = ExternalResult::query()->whereIn('candidate_id', ExternalCandidate::query()->where('session_id', $session->id)->select('id'))
            ->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        $registered = ExternalCandidate::query()->where('session_id', $session->id)->pluck('student_id');
        $needGrade = ExternalExamService::GRADE_NUMBER[$session->type];
        $eligible = Student::query()->with('schoolClass.level')->where('status', 'active')->whereNotIn('id', $registered)
            ->orderBy('first_name')->orderBy('last_name')->limit(600)->get()
            ->filter(fn (Student $s) => ($g = $s->schoolClass?->level?->grade_number) === null || $g === $needGrade)
            ->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->full_name, 'student_number' => $s->student_number, 'class' => $s->schoolClass?->name])
            ->values();

        return Inertia::render('ExternalExams/Session', $this->shellProps() + [
            'session' => [
                'id' => $session->id, 'type' => $session->type, 'name' => $session->name, 'exam_year' => $session->exam_year, 'status' => $session->status,
                'registration_opens_on' => $session->registration_opens_on?->toDateString(),
                'registration_closes_on' => $session->registration_closes_on?->toDateString(),
            ],
            'candidates' => $candidates->through(fn (ExternalCandidate $c) => [
                'id' => $c->id, 'name' => $c->student?->full_name, 'student_number' => $c->student?->student_number,
                'internal_reference' => $c->internal_reference, 'official_index_number' => $c->official_index_number,
                'status' => $c->status, 'subjects_count' => $c->subjects_count, 'results_count' => $c->results_count,
            ]),
            'resultCounts' => $statusCounts,
            'eligibleStudents' => $eligible,
            'subjects' => ExternalExamSubject::query()->where('exam_type', $session->type)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'batches' => ImportBatch::query()->where('type', 'external_results')->latest()->limit(5)->get()->map(fn ($b) => [
                'id' => $b->id, 'original_filename' => $b->original_filename, 'status' => $b->status, 'total_rows' => $b->total_rows,
                'success_rows' => $b->success_rows, 'failed_rows' => $b->failed_rows, 'errors' => $b->errors ?? [], 'finished_at' => $b->finished_at?->toDateTimeString(),
            ]),
            'q' => $q,
            'can' => $this->can(),
            'importHeaders' => ExternalResultImporter::HEADERS,
        ]);
    }

    public function register(Request $request, ExternalExamSession $session): RedirectResponse
    {
        $v = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => ['integer', $this->inSchool('students')],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', $this->inSchool('external_exam_subjects')],
        ]);

        return $this->attempt(function () use ($v, $session, $request) {
            $done = 0;
            $failed = 0;
            foreach (Student::query()->whereIn('id', $v['student_ids'])->get() as $student) {
                try {
                    $this->service->register($request->user(), $session, $student, $v['subject_ids'] ?? []);
                    $done++;
                } catch (AcademicRuleException) {
                    $failed++;
                }
            }
            if ($done === 0) {
                throw AcademicRuleException::because('err.ext.registerNone');
            }

            return back()->with('success', 'ok.ext.registered')->with('count', $done)->with('failedCount', $failed);
        });
    }

    public function import(Request $request, ExternalExamSession $session, ExternalResultImporter $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('schoolos.external_exams.import_max_kb')]]);

        return $this->attempt(function () use ($request, $session, $importer) {
            $batch = $importer->import($request->user(), $session, $request->file('file'));

            return back()->with('success', $batch->failed_rows ? 'ok.ext.importedWithErrors' : 'ok.ext.imported')
                ->with('count', $batch->success_rows)->with('failedCount', $batch->failed_rows);
        });
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ExternalResultImporter::HEADERS);
            // Identify each row by the official index number when you have it, otherwise by the internal reference or student number.
            fputcsv($out, ['', 'IC-WASSCE-2026-00001', '', 'ENG', 'B2']);
            fclose($out);
        }, 'external-results-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function verifySession(Request $request, ExternalExamSession $session): RedirectResponse
    {
        return $this->attempt(function () use ($request, $session) {
            $n = $this->service->verifySession($request->user(), $session);

            return back()->with('success', 'ok.ext.verified')->with('count', $n);
        });
    }

    public function publishSession(Request $request, ExternalExamSession $session): RedirectResponse
    {
        return $this->attempt(function () use ($request, $session) {
            $r = $this->service->publish($request->user(), $session);

            return back()->with('success', 'ok.ext.published')->with('count', $r['published'])->with('failedCount', $r['skipped']);
        });
    }

    // ------------------------------------------------------------------ one candidate

    public function candidate(ExternalCandidate $candidate): Response
    {
        $candidate->load(['session', 'student.schoolClass', 'subjectLinks', 'results.subject', 'documents', 'collection', 'certificate']);
        $type = $candidate->session->type;
        $registered = $candidate->subjectLinks->pluck('subject_id')->all();

        return Inertia::render('ExternalExams/Candidate', $this->shellProps() + [
            'candidate' => [
                'id' => $candidate->id, 'status' => $candidate->status,
                'internal_reference' => $candidate->internal_reference, 'official_index_number' => $candidate->official_index_number,
                'index_recorded_at' => $candidate->index_recorded_at?->toDateTimeString(),
                'registered_on' => $candidate->registered_on?->toDateString(), 'details' => $candidate->details, 'notes' => $candidate->notes,
                'student' => ['id' => $candidate->student->id, 'name' => $candidate->student->full_name, 'student_number' => $candidate->student->student_number],
            ],
            'session' => ['id' => $candidate->session->id, 'type' => $type, 'name' => $candidate->session->name, 'exam_year' => $candidate->session->exam_year],
            'subjects' => ExternalExamSubject::query()->where('exam_type', $type)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'registeredSubjectIds' => $registered,
            'results' => $candidate->results->map(fn ($r) => [
                'id' => $r->id, 'subject_id' => $r->subject_id, 'subject' => $r->subject?->name, 'code' => $r->subject?->code,
                'grade' => $r->grade, 'status' => $r->status, 'source' => $r->source,
            ])->values(),
            'history' => $this->service->history($candidate)->map(fn ($h) => [
                'id' => $h->id, 'event' => $h->event, 'subject' => $h->subject?->name, 'old_grade' => $h->old_grade, 'new_grade' => $h->new_grade,
                'reason' => $h->reason, 'user' => $h->user?->name, 'at' => $h->created_at?->toDateTimeString(),
            ]),
            'documents' => $candidate->documents->map(fn ($d) => ['id' => $d->id, 'type' => $d->type, 'name' => $d->original_name, 'url' => route('external-exams.documents.show', [$candidate, $d])]),
            'documentTypes' => self::DOCUMENT_TYPES,
            'grades' => $this->service->gradeList($type),
            'collection' => $candidate->collection ? ['status' => $candidate->collection->status] : null,
            'certificate' => $candidate->certificate ? ['status' => $candidate->certificate->status] : null,
            'can' => $this->can(),
        ]);
    }

    public function updateSubjects(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        $v = $request->validate(['subject_ids' => ['nullable', 'array'], 'subject_ids.*' => ['integer', $this->inSchool('external_exam_subjects')]]);

        return $this->attempt(fn () => $this->service->setSubjects($candidate, $v['subject_ids'] ?? []));
    }

    public function updateStatus(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        $v = $request->validate(['status' => ['required', 'in:registered,confirmed,withdrawn']]);

        return $this->attempt(fn () => $this->service->setStatus($request->user(), $candidate, $v['status']));
    }

    public function recordIndex(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        // No trimming/normalising of `official_index_number`: the value must be stored exactly as supplied.
        $v = $request->validate(['official_index_number' => ['required', 'string', 'max:60'], 'reason' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->recordIndexNumber($request->user(), $candidate, $v['official_index_number'], $v['reason'] ?? null), 'ok.ext.indexSaved');
    }

    public function enterResult(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        $v = $request->validate(['subject_id' => ['required', 'integer', $this->inSchool('external_exam_subjects')], 'grade' => ['required', 'string', 'max:12']]);

        return $this->attempt(fn () => $this->service->enterResult($request->user(), $candidate, (int) $v['subject_id'], strtoupper(trim($v['grade']))));
    }

    public function verifyCandidate(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->verify($request->user(), $candidate), 'ok.ext.verified');
    }

    public function correct(Request $request, ExternalResult $result): RedirectResponse
    {
        $v = $request->validate(['grade' => ['required', 'string', 'max:12'], 'reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->correct($request->user(), $result, strtoupper(trim($v['grade'])), $v['reason']), 'ok.ext.corrected');
    }

    public function storeDocument(Request $request, ExternalCandidate $candidate): RedirectResponse
    {
        $v = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::DOCUMENT_TYPES)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('schoolos.students.document_max_kb')],
        ]);
        $file = $request->file('file');
        $path = $file->store('external-candidates/'.$candidate->school_id.'/'.$candidate->id, config('schoolos.students.document_disk', 'local'));
        ExternalCandidateDocument::query()->create([
            'candidate_id' => $candidate->id, 'type' => $v['type'], 'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'path' => $path, 'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'ok.ext.documentAdded');
    }

    public function document(ExternalCandidate $candidate, ExternalCandidateDocument $document)
    {
        abort_unless($document->candidate_id === $candidate->id, 404);
        $disk = config('schoolos.students.document_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($document->path), 404);

        return Storage::disk($disk)->download($document->path, $document->original_name);
    }

    public function destroyDocument(ExternalCandidate $candidate, ExternalCandidateDocument $document): RedirectResponse
    {
        abort_unless($document->candidate_id === $candidate->id, 404);
        Storage::disk(config('schoolos.students.document_disk', 'local'))->delete($document->path);
        $document->delete();

        return back()->with('success', 'ok.ext.documentRemoved');
    }

    protected function can(): array
    {
        $u = auth()->user();

        return ['manage' => $u->can('external_exams.manage'), 'verify' => $u->can('external_exams.verify')];
    }
}
