<?php

namespace App\Http\Controllers\Students;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\Academics\TeacherScope;
use App\Services\Attendance\AttendanceService;
use App\Services\Results\ResultsService;
use App\Services\Students\StudentMovementService;
use App\Services\Students\StudentRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(Request $request, TeacherScope $scope): Response
    {
        $this->authorize('viewAny', Student::class);
        $user = auth()->user();

        $query = Student::query()->with('schoolClass');
        if ($user->isGuardianPortal()) {
            $ids = $user->guardianProfile?->students()->pluck('students.id') ?? collect();
            $query->whereIn('id', $ids);
        } elseif ($user->isLearnerPortal()) {
            $query->where('id', $user->studentProfile?->id);
        } else {
            $scope->limitStudents($query, $user);
        }

        $students = $query
            ->when($request->string('q')->toString(), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            })
            ->when($request->integer('class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(20)->withQueryString()
            ->through(fn (Student $student) => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'gender' => $student->gender,
                'class' => $student->schoolClass?->name,
                'status' => $student->status,
            ]);

        return Inertia::render('Students/Index', array_merge($this->shellProps(), [
            'students' => $students,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'class_id' => $request->integer('class_id') ?: null,
                'status' => $request->string('status')->toString(),
            ],
            'classes' => $user->can('portal.admin') ? SchoolClass::query()->orderBy('name')->get(['id', 'name']) : [],
            'can' => [
                'create' => $user->can('students.create'),
                'import' => $user->can('students.import'),
            ],
        ]));
    }

    public function create(): Response
    {
        $this->authorize('create', Student::class);

        return Inertia::render('Students/Form', array_merge($this->shellProps(), $this->formProps(null)));
    }

    public function store(Request $request, StudentRegistrar $registrar): RedirectResponse
    {
        $this->authorize('create', Student::class);
        $data = $this->validated($request, null);
        if (! auth()->user()->can('students.view_sensitive')) {
            unset($data['medical_notes'], $data['welfare_notes']); // restricted fields: ignored without the permission
        }
        $guardian = $request->validate([
            'guardian.name' => ['nullable', 'string', 'max:150'],
            'guardian.relationship' => ['nullable', 'string', 'max:50'],
            'guardian.phone' => ['nullable', 'string', 'max:50'],
            'guardian.email' => ['nullable', 'email', 'max:150'],
        ])['guardian'] ?? null;

        $student = null;
        $response = $this->attempt(function () use ($registrar, $request, $data, $guardian, &$student) {
            $school = auth()->user()->school;
            $student = $registrar->register($school, $data, filled($guardian['name'] ?? null) ? $guardian : null, $request->boolean('confirm_duplicate'));
            $this->storePhoto($request, $student);
        });

        return $student ? redirect()->route('students.show', $student)->with('success', 'ok.studentRegistered') : $response;
    }

    public function edit(int $student): Response
    {
        $record = Student::query()->findOrFail($student);
        $this->authorize('update', $record);

        return Inertia::render('Students/Form', array_merge($this->shellProps(), $this->formProps($record)));
    }

    public function update(Request $request, int $student): RedirectResponse
    {
        $record = Student::query()->findOrFail($student);
        $this->authorize('update', $record);
        $data = $this->validated($request, $record);

        // student_number and status are never edited here (permanent id; moves go through the movement service).
        unset($data['school_class_id']);
        if (! auth()->user()->can('students.view_sensitive')) {
            unset($data['medical_notes'], $data['welfare_notes']);
        }

        return $this->attempt(function () use ($record, $data, $request) {
            $record->update($data);
            $this->storePhoto($request, $record);
            ActivityLog::query()->create([
                'school_id' => $record->school_id, 'user_id' => auth()->id(),
                'event' => 'student_updated', 'description' => "Updated student {$record->student_number}",
            ]);
        }, 'ok.studentUpdated');
    }

    public function show(int $student, ResultsService $results, AttendanceService $attendance, TeacherScope $scope): Response
    {
        $record = Student::query()->with(['schoolClass.level', 'guardians'])->findOrFail($student);
        $this->authorize('view', $record);
        $user = auth()->user();

        $portal = $user->isFamilyPortal();
        $sensitive = $user->can('students.view_sensitive');
        $report = $results->reportFor($record, $portal);

        $records = AttendanceRecord::query()->where('student_id', $record->id);
        $total = (clone $records)->count();
        $attended = (clone $records)->whereIn('status', AttendanceService::ATTENDED)->count();

        return Inertia::render('Students/Show', array_merge($this->shellProps(), [
            'student' => [
                'id' => $record->id,
                'student_number' => $record->student_number,
                'full_name' => $record->full_name,
                'first_name' => $record->first_name,
                'last_name' => $record->last_name,
                'gender' => $record->gender,
                'date_of_birth' => $record->date_of_birth?->toDateString(),
                'place_of_birth' => $record->place_of_birth,
                'nationality' => $record->nationality,
                'religion' => $record->religion,
                'status' => $record->status,
                'class' => $record->schoolClass?->name,
                'level' => $record->schoolClass?->level?->name,
                'class_id' => $record->school_class_id,
                'admitted_on' => $record->admitted_on?->toDateString(),
                'previous_school' => $record->previous_school,
                'address' => $record->address,
                'emergency_contact' => [
                    'name' => $record->emergency_contact_name,
                    'phone' => $record->emergency_contact_phone,
                    'relationship' => $record->emergency_contact_relationship,
                ],
                'withdrawn_on' => $record->withdrawn_on?->toDateString(),
                'withdrawal_reason' => $record->withdrawal_reason,
                'has_photo' => (bool) $record->photo_path,
                'photo_url' => $record->photo_path ? route('students.photo', $record) : null,
            ],
            // Restricted: medical and welfare notes are sent ONLY to holders of students.view_sensitive.
            'sensitive' => $sensitive ? [
                'medical_notes' => $record->medical_notes,
                'welfare_notes' => $record->welfare_notes,
            ] : null,
            'guardians' => $record->guardians->map(fn ($g) => [
                'full_name' => $g->full_name,
                'relationship' => $g->relationship,
                'phone' => $g->phone,
                'email' => $g->email,
                'occupation' => $g->occupation,
                'has_account' => (bool) $g->user_id,
            ]),
            'documents' => $user->canAny(['students.update', 'students.view_sensitive'])
                ? $record->documents()->latest()->get()->map(fn (StudentDocument $d) => [
                    'id' => $d->id, 'type' => $d->type, 'name' => $d->original_name, 'size' => $d->size,
                    'uploaded_at' => $d->created_at?->toDateString(),
                    'url' => route('students.documents.show', [$record, $d]),
                ]) : [],
            'movements' => $record->movements()->with(['fromClass', 'toClass'])->limit(25)->get()->map(fn ($m) => [
                'id' => $m->id, 'event' => $m->event, 'from' => $m->fromClass?->name, 'to' => $m->toClass?->name,
                'reason' => $m->reason, 'date' => $m->effective_on?->toDateString(),
            ]),
            'grades' => $report['rows']->map(fn ($r) => collect($r)->only(['subject', 'examination', 'term', 'ca_score', 'exam_score', 'total', 'letter_grade', 'remark', 'revised'])->all()),
            'history' => $results->history($record, $portal),
            'attendance' => [
                'total' => $total,
                'present' => (clone $records)->where('status', 'present')->count(),
                'attended' => $attended,
                'absent' => (clone $records)->whereIn('status', ['absent', 'excused'])->count(),
                'rate' => $total > 0 ? round($attended / $total * 100, 1) : 0,
            ],
            'can' => [
                'update' => $user->can('students.update') && ! $portal,
                'move' => $user->can('academics.manage') && ! $portal,
                'documents' => $user->can('students.update') && ! $portal,
            ],
            'classes' => $user->can('academics.manage') ? SchoolClass::query()->orderBy('name')->get(['id', 'name']) : [],
            'reportCardUrl' => route('students.report-card', $record),
            'attendanceUrl' => $user->can('attendance.view') ? route('attendance.student', $record) : null,
        ]));
    }

    public function reportCard(int $student, ResultsService $results): Response
    {
        $record = Student::query()->with('schoolClass')->findOrFail($student);
        $this->authorize('view', $record);
        $school = $record->school;
        $portal = auth()->user()->isFamilyPortal();
        $report = $results->reportFor($record, $portal, request()->integer('examination_id') ?: null);

        return Inertia::render('Students/ReportCard', array_merge($this->shellProps(), [
            'school' => ['name' => $school?->name, 'address' => $school?->address],
            'student' => [
                'full_name' => $record->full_name,
                'student_number' => $record->student_number,
                'class' => $record->schoolClass?->name,
            ],
            'grades' => $report['rows'],
            'summary' => ['average' => $report['average'], 'overall_grade' => $report['letter']],
            'scale' => $results->scale()->bands->map(fn ($b) => ['letter' => $b->letter, 'min' => $b->min_score, 'remark' => $b->remark]),
            'history' => $results->history($record, $portal),
        ]));
    }

    // ------------------------------------------------------------------ movements

    public function move(Request $request, int $student, StudentMovementService $movements): RedirectResponse
    {
        abort_unless(auth()->user()?->can('academics.manage'), 403);
        $record = Student::query()->findOrFail($student);

        $validated = $request->validate([
            'action' => ['required', 'in:change_class,withdraw,transfer_out,graduate,reinstate'],
            'school_class_id' => ['nullable', $this->inSchool('school_classes')],
            'reason' => ['nullable', 'string', 'max:255'],
            'effective_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        return $this->attempt(function () use ($validated, $record, $movements) {
            $class = ! empty($validated['school_class_id']) ? SchoolClass::query()->findOrFail($validated['school_class_id']) : null;
            $reason = $validated['reason'] ?? null;
            $on = $validated['effective_on'] ?? null;

            match ($validated['action']) {
                'change_class' => $class
                    ? $this->requireActive($record, fn () => $movements->changeClass($record, $class, $reason))
                    : throw AcademicRuleException::because('err.class.notFound'),
                'withdraw' => blank($reason)
                    ? throw AcademicRuleException::because('err.movement.reasonRequired')
                    : $movements->withdraw($record, $reason, $on),
                'transfer_out' => blank($reason)
                    ? throw AcademicRuleException::because('err.movement.reasonRequired')
                    : $movements->transferOut($record, $reason, $on),
                'graduate' => $movements->graduate($record, $on),
                'reinstate' => $movements->reinstate($record, $class, $reason),
            };
        }, 'ok.movementSaved');
    }

    protected function requireActive(Student $student, callable $fn): void
    {
        if ($student->status !== 'active') {
            throw AcademicRuleException::because('err.movement.notActive');
        }
        $fn();
    }

    // ------------------------------------------------------------------ photo and documents (private disk)

    public function photo(int $student): StreamedResponse
    {
        $record = Student::query()->findOrFail($student);
        $this->authorize('view', $record);
        abort_unless($record->photo_path && Storage::disk($this->disk())->exists($record->photo_path), 404);

        return Storage::disk($this->disk())->response($record->photo_path, null, [
            'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeDocument(Request $request, int $student): RedirectResponse
    {
        $record = Student::query()->findOrFail($student);
        $this->authorize('update', $record);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', StudentDocument::TYPES)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('schoolos.students.document_max_kb')],
        ]);

        return $this->attempt(function () use ($validated, $request, $record) {
            $file = $validated['file'];
            $path = $file->storeAs(
                "students/{$record->school_id}/{$record->id}",
                Str::random(40).'.'.$file->extension(),
                $this->disk()
            );
            StudentDocument::query()->create([
                'school_id' => $record->school_id, 'student_id' => $record->id, 'type' => $validated['type'],
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->id(),
            ]);
        }, 'ok.documentUploaded');
    }

    public function showDocument(int $student, int $document): StreamedResponse
    {
        $record = Student::query()->findOrFail($student);
        $this->authorize('update', $record);
        abort_unless(auth()->user()->canAny(['students.update', 'students.view_sensitive']), 403);

        $doc = StudentDocument::query()->where('student_id', $record->id)->findOrFail($document);
        abort_unless(Storage::disk($this->disk())->exists($doc->path), 404);

        ActivityLog::query()->create([
            'school_id' => $record->school_id, 'user_id' => auth()->id(),
            'event' => 'student_document_viewed', 'description' => "Document {$doc->id} of {$record->student_number}",
        ]);

        return Storage::disk($this->disk())->download($doc->path, $doc->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroyDocument(int $student, int $document): RedirectResponse
    {
        $record = Student::query()->findOrFail($student);
        $this->authorize('update', $record);
        $doc = StudentDocument::query()->where('student_id', $record->id)->findOrFail($document);

        Storage::disk($this->disk())->delete($doc->path);
        $doc->delete();

        return back()->with('success', 'ok.documentRemoved');
    }

    // ------------------------------------------------------------------ helpers

    protected function disk(): string
    {
        return (string) config('schoolos.students.document_disk', 'local');
    }

    protected function storePhoto(Request $request, Student $student): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }
        $request->validate(['photo' => ['image', 'mimes:jpg,jpeg,png', 'max:2048']]);

        if ($student->photo_path) {
            Storage::disk($this->disk())->delete($student->photo_path);
        }
        $file = $request->file('photo');
        $student->update(['photo_path' => $file->storeAs(
            "students/{$student->school_id}/{$student->id}", 'photo-'.Str::random(24).'.'.$file->extension(), $this->disk()
        )]);
    }

    protected function validated(Request $request, ?Student $student): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'school_class_id' => ['nullable', $this->inSchool('school_classes')],
            'admitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'previous_school' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:80'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'welfare_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    protected function formProps(?Student $student): array
    {
        $user = auth()->user();

        return [
            'classes' => SchoolClass::query()->with('level')->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'level' => $c->level?->name]),
            'student' => $student ? [
                ...$student->only([
                    'id', 'first_name', 'last_name', 'gender', 'place_of_birth', 'nationality', 'religion',
                    'previous_school', 'address', 'emergency_contact_name', 'emergency_contact_phone',
                    'emergency_contact_relationship', 'student_number',
                ]),
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'admitted_on' => $student->admitted_on?->toDateString(),
                'school_class_id' => $student->school_class_id,
                'medical_notes' => $user->can('students.view_sensitive') ? $student->medical_notes : null,
                'welfare_notes' => $user->can('students.view_sensitive') ? $student->welfare_notes : null,
            ] : null,
            'canSensitive' => $user->can('students.view_sensitive'),
        ];
    }
}
