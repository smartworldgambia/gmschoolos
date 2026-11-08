<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Services\Academics\TeacherScope;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected AttendanceService $attendance, protected TeacherScope $scope) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();
        $date = $this->date($request);
        $approver = $user->can('attendance.approve');

        $classes = $this->myClasses();

        return Inertia::render('Attendance/Index', array_merge($this->shellProps(), [
            'date' => $date,
            'overview' => $approver ? $this->attendance->dailyOverview($date) : [],
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'pendingCorrections' => $approver ? AttendanceCorrection::query()->where('status', 'pending')->count() : null,
            'can' => [
                'record' => $user->can('attendance.record'),
                'approve' => $approver,
                'staff' => $user->can('attendance.staff'),
            ],
        ]));
    }

    public function take(Request $request): Response
    {
        abort_unless(auth()->user()?->can('attendance.record'), 403);
        $date = $this->date($request);
        $classes = $this->myClasses();

        $class = $classes->firstWhere('id', $request->integer('class_id')) ?? $classes->first();
        $slot = $class && $request->integer('slot_id')
            ? TimetableSlot::query()->with('subject')->where('school_class_id', $class->id)->find($request->integer('slot_id'))
            : null;
        $session = $slot ? 'p'.$slot->id : ($request->string('session')->toString() ?: 'full');
        if (! $slot && ! in_array($session, ['full', 'am', 'pm'], true)) {
            $session = 'full';
        }

        $slots = $class
            ? TimetableSlot::query()->with('subject')->where('school_class_id', $class->id)
                ->where('day_of_week', Carbon::parse($date)->dayOfWeekIso)->orderBy('starts_at')->get()
                ->map(fn ($s) => ['id' => $s->id, 'label' => $s->subject?->name, 'starts_at' => substr((string) $s->starts_at, 0, 5), 'ends_at' => substr((string) $s->ends_at, 0, 5)])
            : collect();

        $canEditClass = $class ? $this->canTake($class, $slot) : false;

        return Inertia::render('Attendance/Take', array_merge($this->shellProps(), [
            'date' => $date,
            'session' => $session,
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'selected' => $class?->id,
            'slots' => $slots,
            'slotId' => $slot?->id,
            'roster' => $class ? $this->attendance->roster($class, $date, $session) : [],
            'statuses' => AttendanceRecord::STATUSES,
            'locked' => $this->attendance->isLocked(Carbon::parse($date)) && ! auth()->user()->can('attendance.approve'),
            'canTake' => $canEditClass,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('attendance.record'), 403);

        $v = $request->validate([
            'class_id' => ['required', $this->inSchool('school_classes')],
            'date' => ['required', 'date'],
            'session' => ['required', 'string', 'max:20'],
            'slot_id' => ['nullable', $this->inSchool('timetable_slots')],
            'entries' => ['required', 'array', 'min:1', 'max:300'],
            'entries.*.student_id' => ['required', 'integer'],
            'entries.*.status' => ['required', 'string', 'max:20'],
            'entries.*.reason' => ['nullable', 'string', 'max:255'],
            'entries.*.arrived_at' => ['nullable', 'date_format:H:i'],
            'entries.*.left_at' => ['nullable', 'date_format:H:i'],
        ]);

        return $this->attempt(function () use ($v) {
            $class = SchoolClass::query()->findOrFail($v['class_id']);
            $slot = ! empty($v['slot_id']) ? TimetableSlot::query()->findOrFail($v['slot_id']) : null;
            $this->attendance->record(auth()->user(), $class, $v['date'], $v['session'], $v['entries'], $slot);
        }, 'ok.attendanceSaved');
    }

    // ------------------------------------------------------------------ corrections

    public function corrections(Request $request): Response
    {
        $user = auth()->user();
        abort_unless($user?->canAny(['attendance.record', 'attendance.approve']), 403);
        $approver = $user->can('attendance.approve');

        $rows = AttendanceCorrection::query()->with(['record.student', 'requester'])
            ->when(! $approver, fn ($q) => $q->where('requested_by', $user->id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()->limit(100)->get()
            ->map(fn (AttendanceCorrection $c) => [
                'id' => $c->id, 'status' => $c->status,
                'student' => $c->record?->student?->full_name, 'date' => $c->record?->attended_on?->toDateString(),
                'from' => $c->from_status, 'to' => $c->to_status, 'reason' => $c->reason,
                'requested_by' => $c->requester?->name, 'decision_note' => $c->decision_note,
                'created_at' => $c->created_at?->toDateString(),
            ]);

        return Inertia::render('Attendance/Corrections', array_merge($this->shellProps(), [
            'corrections' => $rows,
            'filters' => ['status' => $request->string('status')->toString()],
            'can' => ['approve' => $approver],
        ]));
    }

    public function requestCorrection(Request $request, int $record): RedirectResponse
    {
        abort_unless(auth()->user()?->can('attendance.record'), 403);
        $v = $request->validate([
            'to_status' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $row = AttendanceRecord::query()->findOrFail($record);

        return $this->attempt(fn () => $this->attendance->requestCorrection(auth()->user(), $row, $v['to_status'], $v['reason']), 'ok.correctionRequested');
    }

    public function decide(Request $request, int $correction): RedirectResponse
    {
        abort_unless(auth()->user()?->can('attendance.approve'), 403);
        $v = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:255']]);
        $row = AttendanceCorrection::query()->findOrFail($correction);

        return $this->attempt(fn () => $this->attendance->decideCorrection(auth()->user(), $row, $v['decision'] === 'approve', $v['note'] ?? null), 'ok.correctionDecided');
    }

    // ------------------------------------------------------------------ reports and history

    public function report(Request $request): Response
    {
        $user = auth()->user();
        abort_unless($user?->can('attendance.view') && $user->can('portal.teacher') || $user->canAny(['attendance.approve', 'academics.manage']), 403);

        $kind = in_array($request->string('kind')->toString(), ['day', 'week', 'month', 'term'], true) ? $request->string('kind')->toString() : 'week';
        $anchor = $this->date($request);
        $classes = $this->myClasses();
        $class = $classes->firstWhere('id', $request->integer('class_id'));
        // Restricted staff must pick one of their own classes; approvers may also see the whole school.
        if (! $class && ! $user->can('attendance.approve')) {
            $class = $classes->first();
        }

        $summary = null;
        $error = null;
        try {
            [$from, $to] = $this->attendance->range($kind, $anchor);
            $summary = $this->attendance->summary($class?->id, $from, $to);
        } catch (\App\Exceptions\AcademicRuleException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Attendance/Report', array_merge($this->shellProps(), [
            'kind' => $kind, 'date' => $anchor, 'summary' => $summary, 'error' => $error,
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'selected' => $class?->id,
            'canAll' => $user->can('attendance.approve'),
        ]));
    }

    public function student(Request $request, int $student): Response
    {
        $record = Student::query()->with('schoolClass')->findOrFail($student);
        $this->authorize('view', $record);

        $rows = AttendanceRecord::query()->where('student_id', $record->id)->orderByDesc('attended_on')->orderBy('session')
            ->paginate(30)->withQueryString()
            ->through(fn (AttendanceRecord $r) => [
                'id' => $r->id, 'date' => $r->attended_on?->toDateString(), 'session' => $r->session,
                'status' => $r->status, 'reason' => $r->reason,
            ]);

        $all = AttendanceRecord::query()->where('student_id', $record->id);
        $total = (clone $all)->count();
        $attended = (clone $all)->whereIn('status', AttendanceService::ATTENDED)->count();

        return Inertia::render('Attendance/Student', array_merge($this->shellProps(), [
            'student' => ['id' => $record->id, 'name' => $record->full_name, 'student_number' => $record->student_number, 'class' => $record->schoolClass?->name],
            'records' => $rows,
            'totals' => [
                'total' => $total, 'attended' => $attended,
                'absent' => (clone $all)->where('status', 'absent')->count(),
                'excused' => (clone $all)->where('status', 'excused')->count(),
                'late' => (clone $all)->where('status', 'late')->count(),
                'rate' => $total ? round($attended / $total * 100, 1) : null,
            ],
            'canCorrect' => auth()->user()->can('attendance.record') && ! auth()->user()->isFamilyPortal(),
        ]));
    }

    // ------------------------------------------------------------------ staff / teacher attendance

    public function staff(Request $request): Response
    {
        abort_unless(auth()->user()?->can('attendance.staff'), 403);
        $date = $this->date($request);
        $existing = StaffAttendance::query()->whereDate('attended_on', $date)->get()->keyBy('staff_id');

        return Inertia::render('Attendance/Staff', array_merge($this->shellProps(), [
            'date' => $date,
            'statuses' => AttendanceService::STAFF_STATUSES,
            'staff' => Staff::query()->where('status', 'active')->orderBy('first_name')->get()->map(fn (Staff $s) => [
                'staff_id' => $s->id, 'name' => $s->full_name, 'role_title' => $s->role_title,
                'status' => $existing[$s->id]->status ?? null, 'remarks' => $existing[$s->id]->remarks ?? null,
            ]),
        ]));
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('attendance.staff'), 403);
        $v = $request->validate([
            'date' => ['required', 'date'],
            'entries' => ['required', 'array', 'min:1', 'max:300'],
            'entries.*.staff_id' => ['required', 'integer'],
            'entries.*.status' => ['required', 'string', 'max:20'],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt(fn () => $this->attendance->recordStaff(auth()->user(), $v['date'], $v['entries']), 'ok.attendanceSaved');
    }

    // ------------------------------------------------------------------ helpers

    protected function date(Request $request): string
    {
        try {
            $d = Carbon::parse($request->string('date')->toString() ?: now()->toDateString());
        } catch (\Throwable) {
            $d = now();
        }

        return ($d->isFuture() ? now() : $d)->toDateString();
    }

    /** Classes the user may register or report on. */
    protected function myClasses()
    {
        $user = auth()->user();
        $all = SchoolClass::query()->orderBy('level_id')->orderBy('name')->get();

        if ($user->can('attendance.approve') || $user->can('academics.manage')) {
            return $all;
        }

        $ids = $this->scope->classIds($user);

        return $all->filter(fn ($c) => $ids->contains($c->id))->values();
    }

    protected function canTake(SchoolClass $class, ?TimetableSlot $slot): bool
    {
        try {
            $this->attendance->authorise(auth()->user(), $class, $slot);

            return true;
        } catch (\App\Exceptions\AcademicRuleException) {
            return false;
        }
    }
}
