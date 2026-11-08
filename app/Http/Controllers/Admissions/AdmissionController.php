<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\SchoolClass;
use App\Models\StudentDocument;
use App\Services\Admissions\AdmissionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->can('admissions.view'), 403);

        $applications = AdmissionApplication::query()
            ->with('preferredClass')
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('source')->toString(), fn ($q, $source) => $q->where('source', $source))
            ->when($request->string('q')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('application_number', 'like', "%{$s}%")->orWhere('guardian_name', 'like', "%{$s}%")))
            ->orderByRaw("case status when 'submitted' then 0 when 'under_review' then 1 when 'waitlisted' then 2 else 3 end")
            ->latest()
            ->paginate(20)->withQueryString()
            ->through(fn (AdmissionApplication $app) => [
                'id' => $app->id,
                'application_number' => $app->application_number,
                'full_name' => $app->full_name,
                'gender' => $app->gender,
                'preferred_class' => $app->preferredClass?->name,
                'guardian_name' => $app->guardian_name,
                'status' => $app->status,
                'source' => $app->source,
                'waitlist_position' => $app->waitlist_position,
                'created_at' => $app->created_at?->toDateString(),
                'url' => route('admissions.show', $app->id),
            ]);

        $counts = AdmissionApplication::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('Admissions/Index', array_merge($this->shellProps(), [
            'applications' => $applications,
            'counts' => $counts,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'source' => $request->string('source')->toString(),
                'q' => $request->string('q')->toString(),
            ],
            'can' => ['manage' => auth()->user()->can('admissions.manage')],
            'publicApplyUrl' => route('school.apply', ['school_slug' => auth()->user()->school?->slug]),
        ]));
    }

    public function create(): Response
    {
        $this->authorizeManage();

        return Inertia::render('Admissions/Create', array_merge($this->shellProps(), [
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name']),
            'storeUrl' => route('admissions.store'),
            'documentTypes' => StudentDocument::TYPES,
        ]));
    }

    public function store(Request $request, AdmissionWorkflow $workflow): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            ...ApplicationRules::fields($this->inSchool('school_classes')),
            ...ApplicationRules::documents(),
        ]);

        $application = $workflow->submit(
            auth()->user()->school,
            collect($validated)->except('documents')->all(),
            'walk_in',
            ApplicationRules::uploads($request),
        );

        return redirect()->route('admissions.show', $application->id)->with('success', 'ok.applicationSubmitted');
    }

    public function show(int $admission): Response
    {
        abort_unless(auth()->user()?->can('admissions.view'), 403);
        $app = AdmissionApplication::query()->with(['preferredClass', 'documents', 'student'])->findOrFail($admission);

        return Inertia::render('Admissions/Show', array_merge($this->shellProps(), [
            'application' => [
                'id' => $app->id,
                'application_number' => $app->application_number,
                'status' => $app->status,
                'source' => $app->source,
                'full_name' => $app->full_name,
                'gender' => $app->gender,
                'date_of_birth' => $app->date_of_birth?->toDateString(),
                'place_of_birth' => $app->place_of_birth,
                'nationality' => $app->nationality,
                'religion' => $app->religion,
                'previous_school' => $app->previous_school,
                'address' => $app->address,
                'preferred_class' => $app->preferredClass?->name,
                'preferred_class_id' => $app->preferred_class_id,
                'guardian' => [
                    'name' => $app->guardian_name, 'relationship' => $app->guardian_relationship,
                    'phone' => $app->guardian_phone, 'email' => $app->guardian_email,
                ],
                'emergency_contact' => ['name' => $app->emergency_contact_name, 'phone' => $app->emergency_contact_phone],
                'notes' => $app->notes,
                'decision_reason' => $app->decision_reason,
                'waitlist_position' => $app->waitlist_position,
                'created_at' => $app->created_at?->toDateTimeString(),
                'student' => $app->student ? ['id' => $app->student->id, 'student_number' => $app->student->student_number, 'url' => route('students.show', $app->student)] : null,
            ],
            'documents' => $app->documents->map(fn (StudentDocument $d) => [
                'id' => $d->id, 'type' => $d->type, 'name' => $d->original_name, 'size' => $d->size,
                'url' => route('admissions.documents.show', [$app->id, $d->id]),
            ]),
            'transitions' => AdmissionWorkflow::TRANSITIONS[$app->status] ?? [],
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name', 'capacity']),
            'can' => ['manage' => auth()->user()->can('admissions.manage')],
        ]));
    }

    public function updateStatus(Request $request, int $admission, AdmissionWorkflow $workflow): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'status' => ['required', 'in:under_review,accepted,waitlisted,rejected,enrolled,withdrawn'],
            'reason' => ['nullable', 'string', 'max:255'],
            'school_class_id' => ['nullable', $this->inSchool('school_classes')],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ]);

        $application = AdmissionApplication::query()->findOrFail($admission);

        return $this->attempt(fn () => $workflow->decide(
            $application, $validated['status'], $validated['reason'] ?? null,
            $validated['school_class_id'] ?? null, (bool) ($validated['confirm_duplicate'] ?? false),
        ), 'ok.admissionUpdated');
    }

    public function document(int $admission, int $document): StreamedResponse
    {
        abort_unless(auth()->user()?->can('admissions.view'), 403);
        $app = AdmissionApplication::query()->findOrFail($admission);
        $doc = StudentDocument::query()->where('admission_application_id', $app->id)->findOrFail($document);
        $disk = (string) config('schoolos.students.document_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($doc->path), 404);

        return Storage::disk($disk)->download($doc->path, $doc->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('admissions.manage'), 403);
    }
}
