<?php

namespace App\Services\Attendance;

use App\Exceptions\AcademicRuleException;
use App\Models\ActivityLog;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Notifications\AttendanceAlertNotification;
use App\Services\Academics\TeacherScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Attendance capture, corrections, guardian alerts and reports.
 *
 * Rules:
 *  - one record per student, date and session (DB unique key); session is `full`, `am`, `pm` or `p<slotId>` (a lesson),
 *  - daily registers are taken by the class teacher (or someone holding attendance.approve),
 *    lesson registers by the teacher of that timetable slot / subject allocation,
 *  - nobody records the future,
 *  - after `schoolos.attendance.edit_window_days` a register is locked; changes then need a correction
 *    request approved by someone holding attendance.approve,
 *  - guardians with a portal account are notified once when a record becomes absent/late/left_early.
 *    (E-mail is queued; SMS is not implemented in this phase and no delivery claim is made.)
 */
class AttendanceService
{
    public const ATTENDED = ['present', 'late', 'left_early'];

    public const STAFF_STATUSES = ['present', 'absent', 'late', 'on_leave', 'excused'];

    public function __construct(protected TeacherScope $scope) {}

    // ------------------------------------------------------------------ registers

    /** @return array{sessions:array<int,string>} */
    public function sessionFor(?TimetableSlot $slot, string $requested): string
    {
        if ($slot) {
            return 'p'.$slot->id;
        }
        if (! in_array($requested, ['full', 'am', 'pm'], true)) {
            throw AcademicRuleException::because('err.attendance.session');
        }

        return $requested;
    }

    public function authorise(User $user, SchoolClass $class, ?TimetableSlot $slot): void
    {
        if ($user->can('attendance.approve')) {
            return;
        }
        if (! $user->can('attendance.record')) {
            throw AcademicRuleException::because('err.attendance.forbidden');
        }

        $allowed = $slot
            ? ((int) $slot->teacher_id === (int) $user->id
                || $this->scope->canTeach($user, $class->id, (int) $slot->subject_id, 'attendance.approve'))
            : $this->scope->isClassTeacher($user, $class->id);

        if (! $allowed) {
            throw AcademicRuleException::because('err.attendance.notYourClass');
        }
    }

    /**
     * @param  array<int,array{student_id:int,status:string,reason?:?string,arrived_at?:?string,left_at?:?string}>  $entries
     * @return array{saved:int,notified:int}
     */
    public function record(User $user, SchoolClass $class, string $date, string $session, array $entries, ?TimetableSlot $slot = null): array
    {
        $this->authorise($user, $class, $slot);

        if ($slot && (int) $slot->school_class_id !== (int) $class->id) {
            throw AcademicRuleException::because('err.attendance.slotMismatch');
        }

        $day = Carbon::parse($date)->startOfDay();
        if ($day->isFuture()) {
            throw AcademicRuleException::because('err.attendance.future');
        }

        $session = $this->sessionFor($slot, $session);

        $rosterIds = Student::query()->where('school_class_id', $class->id)->where('status', 'active')->pluck('id')->all();
        $seen = [];
        foreach ($entries as $entry) {
            $sid = (int) ($entry['student_id'] ?? 0);
            if (! in_array($sid, $rosterIds, true)) {
                throw AcademicRuleException::because('err.attendance.notInClass');
            }
            if (isset($seen[$sid])) {
                throw AcademicRuleException::because('err.attendance.duplicateEntry');
            }
            $seen[$sid] = true;
            if (! in_array($entry['status'] ?? null, AttendanceRecord::STATUSES, true)) {
                throw AcademicRuleException::because('err.attendance.status');
            }
        }

        $locked = $this->isLocked($day) && ! $user->can('attendance.approve');
        $saved = 0;
        $notified = 0;

        DB::transaction(function () use ($entries, $class, $day, $session, $slot, $user, $locked, &$saved, &$notified) {
            foreach ($entries as $entry) {
                $existing = AttendanceRecord::query()
                    ->where('student_id', $entry['student_id'])
                    ->whereDate('attended_on', $day->toDateString())
                    ->where('session', $session)->first();

                if ($existing && $locked && $existing->status !== $entry['status']) {
                    throw AcademicRuleException::because('err.attendance.locked');
                }
                if (! $existing && $locked) {
                    throw AcademicRuleException::because('err.attendance.locked');
                }

                $statusChanged = ! $existing || $existing->status !== $entry['status'];
                $attrs = [
                    'school_id' => $class->school_id,
                    'school_class_id' => $class->id,
                    'status' => $entry['status'],
                    'reason' => $entry['reason'] ?? null,
                    'arrived_at' => ($entry['arrived_at'] ?? null) ?: null,
                    'left_at' => ($entry['left_at'] ?? null) ?: null,
                    'recorded_by' => $user->id,
                    'timetable_slot_id' => $slot?->id,
                ];

                $record = $existing
                    ? tap($existing)->update($attrs)
                    : AttendanceRecord::query()->create($attrs + [
                        'student_id' => $entry['student_id'], 'attended_on' => $day->toDateString(), 'session' => $session,
                    ]);
                $saved++;

                if ($statusChanged && $this->alertWorthy($record)) {
                    $notified += $this->alertGuardians($record);
                }
            }

            ActivityLog::query()->create([
                'school_id' => $class->school_id,
                'user_id' => $user->id,
                'event' => 'attendance_recorded',
                'description' => "Class {$class->id} {$day->toDateString()} {$session}: {$saved} record(s)",
            ]);
        });

        return ['saved' => $saved, 'notified' => $notified];
    }

    public function isLocked(Carbon $day): bool
    {
        $window = max(0, (int) config('schoolos.attendance.edit_window_days', 1));

        return $day->copy()->addDays($window)->endOfDay()->lt(now());
    }

    protected function alertWorthy(AttendanceRecord $record): bool
    {
        return in_array($record->status, (array) config('schoolos.attendance.guardian_alert_statuses', []), true);
    }

    /** Notify guardians that have a portal account. Returns the number of users notified. */
    protected function alertGuardians(AttendanceRecord $record): int
    {
        $student = Student::query()->with('guardians')->find($record->student_id);
        $users = User::query()
            ->whereIn('id', $student?->guardians->pluck('user_id')->filter()->all() ?? [])
            ->where('school_id', $record->school_id)
            ->get();

        if ($users->isEmpty()) {
            return 0;
        }

        Notification::send($users, new AttendanceAlertNotification($record, $student));
        $record->forceFill(['guardian_notified_at' => now()])->save();

        return $users->count();
    }

    /** Roster for a class/date/session with whatever has already been recorded. */
    public function roster(SchoolClass $class, string $date, string $session): array
    {
        $records = AttendanceRecord::query()
            ->where('school_class_id', $class->id)->whereDate('attended_on', $date)->where('session', $session)
            ->get()->keyBy('student_id');

        return Student::query()->where('school_class_id', $class->id)->where('status', 'active')
            ->orderBy('first_name')->orderBy('last_name')->get()
            ->map(fn (Student $s) => [
                'student_id' => $s->id,
                'student_number' => $s->student_number,
                'name' => $s->full_name,
                'status' => $records[$s->id]->status ?? null,
                'reason' => $records[$s->id]->reason ?? null,
                'arrived_at' => isset($records[$s->id]) ? substr((string) $records[$s->id]->arrived_at, 0, 5) : null,
                'left_at' => isset($records[$s->id]) ? substr((string) $records[$s->id]->left_at, 0, 5) : null,
                'record_id' => $records[$s->id]->id ?? null,
            ])->all();
    }

    // ------------------------------------------------------------------ corrections

    public function requestCorrection(User $user, AttendanceRecord $record, string $toStatus, string $reason): AttendanceCorrection
    {
        if (! in_array($toStatus, AttendanceRecord::STATUSES, true)) {
            throw AcademicRuleException::because('err.attendance.status');
        }
        if ($toStatus === $record->status) {
            throw AcademicRuleException::because('err.attendance.noChange');
        }
        if (blank($reason) || mb_strlen(trim($reason)) < 5) {
            throw AcademicRuleException::because('err.attendance.reasonRequired');
        }

        $class = SchoolClass::query()->findOrFail($record->school_class_id);
        $slot = $record->timetable_slot_id ? TimetableSlot::query()->find($record->timetable_slot_id) : null;
        $this->authorise($user, $class, $slot);

        if (AttendanceCorrection::query()->where('attendance_record_id', $record->id)->where('status', 'pending')->exists()) {
            throw AcademicRuleException::because('err.attendance.correctionPending');
        }

        return AttendanceCorrection::query()->create([
            'school_id' => $record->school_id,
            'attendance_record_id' => $record->id,
            'requested_by' => $user->id,
            'from_status' => $record->status,
            'to_status' => $toStatus,
            'reason' => trim($reason),
            'status' => 'pending',
        ]);
    }

    public function decideCorrection(User $approver, AttendanceCorrection $correction, bool $approve, ?string $note = null): AttendanceCorrection
    {
        if (! $approver->can('attendance.approve')) {
            throw AcademicRuleException::because('err.attendance.forbidden');
        }
        if ($correction->status !== 'pending') {
            throw AcademicRuleException::because('err.attendance.alreadyDecided');
        }
        if (! $approve && blank($note)) {
            throw AcademicRuleException::because('err.attendance.noteRequired');
        }

        DB::transaction(function () use ($approver, $correction, $approve, $note) {
            $correction->update([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            if ($approve) {
                $record = $correction->record;
                $before = $record->status;
                $record->update(['status' => $correction->to_status, 'reason' => $correction->reason]);
                if ($before !== $record->status && $this->alertWorthy($record)) {
                    $this->alertGuardians($record);
                }
            }

            ActivityLog::query()->create([
                'school_id' => $correction->school_id,
                'user_id' => $approver->id,
                'event' => 'attendance_correction_'.($approve ? 'approved' : 'rejected'),
                'description' => "Correction {$correction->id}: {$correction->from_status} -> {$correction->to_status}",
            ]);
        });

        return $correction->fresh();
    }

    // ------------------------------------------------------------------ staff / teacher attendance

    /**
     * @param  array<int,array{staff_id:int,status:string,remarks?:?string}>  $entries
     */
    public function recordStaff(User $user, string $date, array $entries): int
    {
        if (! $user->can('attendance.staff')) {
            throw AcademicRuleException::because('err.attendance.forbidden');
        }
        $day = Carbon::parse($date)->startOfDay();
        if ($day->isFuture()) {
            throw AcademicRuleException::because('err.attendance.future');
        }

        $valid = Staff::query()->pluck('id')->all();
        $count = 0;
        DB::transaction(function () use ($entries, $valid, $day, $user, &$count) {
            foreach ($entries as $entry) {
                if (! in_array((int) $entry['staff_id'], $valid, true) || ! in_array($entry['status'] ?? null, self::STAFF_STATUSES, true)) {
                    throw AcademicRuleException::because('err.attendance.staffEntry');
                }
                $attrs = [
                    'school_id' => $user->school_id ?? app(\App\Support\TenantContext::class)->id(),
                    'status' => $entry['status'], 'remarks' => $entry['remarks'] ?? null, 'recorded_by' => $user->id,
                ];
                // whereDate: the column is stored as a datetime on some drivers, so an equality lookup would miss and re-insert
                $existing = StaffAttendance::query()->where('staff_id', $entry['staff_id'])->whereDate('attended_on', $day->toDateString())->first();
                $existing
                    ? $existing->update($attrs)
                    : StaffAttendance::query()->create($attrs + ['staff_id' => $entry['staff_id'], 'attended_on' => $day->toDateString()]);
                $count++;
            }
        });

        return $count;
    }

    // ------------------------------------------------------------------ reports

    /** @return array{0:Carbon,1:Carbon} */
    public function range(string $kind, string $anchor): array
    {
        $day = Carbon::parse($anchor);

        return match ($kind) {
            'day' => [$day->copy()->startOfDay(), $day->copy()->endOfDay()],
            'week' => [$day->copy()->startOfWeek(), $day->copy()->endOfWeek()],
            'month' => [$day->copy()->startOfMonth(), $day->copy()->endOfMonth()],
            'term' => $this->termRange($day),
            default => throw AcademicRuleException::because('err.attendance.range'),
        };
    }

    protected function termRange(Carbon $day): array
    {
        $term = Term::query()->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->first()
            ?? Term::query()->where('is_current', true)->first();

        if (! $term) {
            throw AcademicRuleException::because('err.attendance.noTerm');
        }

        return [Carbon::parse($term->starts_on)->startOfDay(), Carbon::parse($term->ends_on)->endOfDay()];
    }

    /** Per-student totals for a class (or the whole school) in a date range. */
    public function summary(?int $classId, Carbon $from, Carbon $to): array
    {
        $rows = AttendanceRecord::query()
            ->select('student_id', 'status', DB::raw('count(*) as n'))
            ->whereDate('attended_on', '>=', $from->toDateString())
            ->whereDate('attended_on', '<=', $to->toDateString())
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->groupBy('student_id', 'status')->get()->groupBy('student_id');

        $students = Student::query()
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->whereIn('id', $rows->keys())
            ->orderBy('first_name')->get()->keyBy('id');

        $out = [];
        $totals = array_fill_keys(AttendanceRecord::STATUSES, 0);
        foreach ($rows as $studentId => $set) {
            $counts = array_fill_keys(AttendanceRecord::STATUSES, 0);
            foreach ($set as $r) {
                $counts[$r->status] = (int) $r->n;
            }
            $total = array_sum($counts);
            $attended = array_sum(array_intersect_key($counts, array_flip(self::ATTENDED)));
            foreach ($counts as $k => $v) {
                $totals[$k] += $v;
            }
            $s = $students[$studentId] ?? null;
            if ($s) {
                $out[] = ['student_id' => $studentId, 'student_number' => $s->student_number, 'name' => $s->full_name]
                    + $counts + ['total' => $total, 'rate' => $total ? round($attended / $total * 100, 1) : null];
            }
        }

        $grand = array_sum($totals);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $out,
            'totals' => $totals + ['total' => $grand, 'rate' => $grand ? round(array_sum(array_intersect_key($totals, array_flip(self::ATTENDED))) / $grand * 100, 1) : null],
        ];
    }

    /** Administrator dashboard: which classes have a daily register today and the headline numbers. */
    public function dailyOverview(string $date): array
    {
        $classes = SchoolClass::query()->withCount(['students' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get();
        $records = AttendanceRecord::query()->whereDate('attended_on', $date)->where('session', 'full')
            ->select('school_class_id', 'status', DB::raw('count(*) as n'))->groupBy('school_class_id', 'status')->get()
            ->groupBy('school_class_id');

        return $classes->map(function (SchoolClass $c) use ($records) {
            $set = ($records[$c->id] ?? collect())->pluck('n', 'status');

            return [
                'class_id' => $c->id, 'class' => $c->name, 'students' => $c->students_count,
                'recorded' => (int) $set->sum(),
                'present' => (int) ($set['present'] ?? 0), 'absent' => (int) ($set['absent'] ?? 0),
                'late' => (int) ($set['late'] ?? 0), 'excused' => (int) ($set['excused'] ?? 0),
                'left_early' => (int) ($set['left_early'] ?? 0),
                'marked' => $set->sum() > 0,
            ];
        })->all();
    }
}
