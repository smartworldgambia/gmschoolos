<?php

namespace App\Http\Controllers\SeniorSecondary;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\RecordsDocument;
use App\Models\RecordsTemplate;
use App\Models\SsApplication;
use App\Models\SsApplicationDocument;
use App\Services\Records\DocumentService;
use App\Services\SeniorSecondary\SeniorSecondaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected SeniorSecondaryService $service, protected DocumentService $documents) {}

    public function show(SsApplication $application): Response
    {
        $application->load(['round', 'results', 'documents']);
        $rules = $application->round->rules;

        return Inertia::render('SeniorSecondary/Application', $this->shellProps() + [
            'application' => [
                'id' => $application->id, 'reference' => $application->reference, 'status' => $application->status, 'source' => $application->source,
                'first_name' => $application->first_name, 'last_name' => $application->last_name, 'gender' => $application->gender,
                'date_of_birth' => $application->date_of_birth?->toDateString(), 'nationality' => $application->nationality,
                'previous_school' => $application->previous_school, 'gabece_index_number' => $application->gabece_index_number,
                'gabece_year' => $application->gabece_year, 'guardian_name' => $application->guardian_name,
                'guardian_phone' => $application->guardian_phone, 'guardian_email' => $application->guardian_email,
                'address' => $application->address, 'programme_choice' => $application->programme_choice, 'notes' => $application->notes,
                'aggregate' => $application->aggregate, 'eligible' => $application->eligible, 'assessment' => $application->assessment,
                'rank' => $application->rank_position, 'recommendation' => $application->recommendation,
                'verified' => $application->results_verified_at !== null, 'is_override' => $application->is_override,
                'decision_reason' => $application->decision_reason, 'created_at' => $application->created_at?->toDateTimeString(),
            ],
            'round' => ['id' => $application->round->id, 'name' => $application->round->name, 'status' => $application->round->status, 'capacity' => $application->round->capacity],
            'subjects' => $rules['subjects'],
            'gradeOptions' => array_keys($rules['grade_points']),
            'results' => $application->results->map(fn ($r) => ['code' => $r->subject_code, 'name' => $r->subject_name, 'grade' => $r->grade])->values(),
            'documents' => $application->documents->map(fn ($d) => ['id' => $d->id, 'type' => $d->type, 'name' => $d->original_name, 'url' => route('senior-secondary.documents.show', [$application, $d])]),
            'history' => $this->service->history($application)->map(fn ($e) => [
                'id' => $e->id, 'event' => $e->event, 'from' => $e->from_status, 'to' => $e->to_status, 'note' => $e->note,
                'data' => $e->data, 'user' => $e->user?->name, 'at' => $e->created_at?->toDateTimeString(),
            ]),
            'letters' => RecordsDocument::query()->where('ss_application_id', $application->id)->orderByDesc('id')->get(['id', 'reference', 'status'])
                ->map(fn ($d) => ['id' => $d->id, 'reference' => $d->reference, 'status' => $d->status, 'url' => route('records.show', $d)]),
            'templates' => RecordsTemplate::query()->where('type', 'admission_letter')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can' => ['manage' => auth()->user()->can('senior_admissions.manage')],
        ]);
    }

    public function verify(Request $request, SsApplication $application): RedirectResponse
    {
        $v = $request->validate([
            'grades' => ['nullable', 'array'], 'grades.*' => ['nullable', 'string', 'max:12'], 'note' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->attempt(fn () => $this->service->verifyResults($request->user(), $application, $v['grades'] ?? null, $v['note'] ?? null), 'ok.ss.verified');
    }

    public function decide(Request $request, SsApplication $application): RedirectResponse
    {
        $v = $request->validate(['decision' => ['required', 'in:shortlisted,waitlisted,approved,rejected'], 'reason' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->decide($request->user(), $application, $v['decision'], $v['reason'] ?? null), 'ok.ss.decided');
    }

    public function withdraw(Request $request, SsApplication $application): RedirectResponse
    {
        $v = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->withdraw($request->user(), $application, $v['reason'] ?? null), 'ok.ss.withdrawn');
    }

    public function document(SsApplication $application, SsApplicationDocument $document)
    {
        abort_unless($document->application_id === $application->id, 404);
        $disk = config('schoolos.students.document_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($document->path), 404);

        return Storage::disk($disk)->download($document->path, $document->original_name);
    }

    public function letter(Request $request, SsApplication $application): RedirectResponse
    {
        $v = $request->validate(['template_id' => ['nullable', 'integer', $this->inSchool('records_templates')], 'custom_text' => ['nullable', 'string', 'max:3000']]);

        return $this->attempt(function () use ($request, $application, $v) {
            $template = ! empty($v['template_id']) ? RecordsTemplate::query()->find($v['template_id']) : null;
            $doc = $this->documents->createAdmissionLetter($request->user(), $application, $template, $v['custom_text'] ?? null);

            return redirect()->route('records.show', $doc)->with('success', 'ok.rec.created');
        });
    }
}
