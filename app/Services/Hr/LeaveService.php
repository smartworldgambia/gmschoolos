<?php

namespace App\Services\Hr;

use App\Exceptions\AcademicRuleException;
use App\Models\Staff;
use App\Models\StaffLeaveRequest;
use App\Models\User;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff leave: apply, approve or reject (by someone other than the applicant), cancel, and entitlement balances.
 * The platform ships NO statutory entitlement: a school may set an annual and a sick-leave cap, and without one nothing is capped.
 */
class LeaveService
{
    public const TYPES = ['annual', 'sick', 'unpaid', 'other'];

    public function __construct(private readonly OpsSettings $settings) {}

    public function apply(Staff $staff, User $actor, string $type, string $start, string $end, ?int $days, ?string $reason): StaffLeaveRequest
    {
        if ($staff->status !== 'active') {
            throw AcademicRuleException::because('err.hr.staffInactive');
        }
        if (! in_array($type, self::TYPES, true)) {
            throw AcademicRuleException::because('err.hr.leaveType');
        }

        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($to->lt($from)) {
            throw AcademicRuleException::because('err.hr.leaveDates');
        }
        $span = (int) $from->diffInDays($to) + 1;
        $count = $days ?? $span;
        if ($count < 1 || $count > $span) {
            throw AcademicRuleException::because('err.hr.leaveDays');
        }

        return DB::transaction(function () use ($staff, $actor, $type, $from, $to, $count, $reason) {
            // lock this person's rows so two simultaneous applications cannot both pass the overlap and entitlement checks
            $existing = StaffLeaveRequest::query()->where('staff_id', $staff->id)->whereIn('status', ['pending', 'approved'])->lockForUpdate()->get();

            if ($existing->contains(fn ($l) => $l->starts_on->lte($to) && $l->ends_on->gte($from))) {
                throw AcademicRuleException::because('err.hr.overlap');
            }

            $cap = $this->capFor($type);
            if ($cap !== null) {
                $used = (int) $existing->where('leave_type', $type)->filter(fn ($l) => $l->starts_on->year === $from->year)->sum('days');
                if ($used + $count > $cap) {
                    throw AcademicRuleException::because('err.hr.entitlement');
                }
            }

            $leave = StaffLeaveRequest::query()->create([
                'staff_id' => $staff->id, 'leave_type' => $type, 'starts_on' => $from->toDateString(), 'ends_on' => $to->toDateString(),
                'days' => $count, 'reason' => $reason ? mb_substr(trim($reason), 0, 1000) : null, 'status' => 'pending', 'applied_by' => $actor->id,
            ]);

            Ops::audit('hr.leave.applied', 'Leave requested for '.$staff->full_name, ['leave_id' => $leave->id, 'staff_id' => $staff->id, 'type' => $type, 'days' => $count]);
            Ops::notify(Ops::usersWith('hr.manage', $actor->id), 'Leave request', $staff->full_name.' asked for '.$count.' day(s) of '.$type.' leave from '.$from->format('d M Y').'.', route('hr.leave.index'));

            return $leave;
        });
    }

    public function review(StaffLeaveRequest $leave, User $actor, string $decision, ?string $note): StaffLeaveRequest
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw AcademicRuleException::because('err.hr.decision');
        }
        $note = $note !== null ? mb_substr(trim($note), 0, 500) : null;
        if ($decision === 'rejected' && ($note === null || $note === '')) {
            throw AcademicRuleException::because('err.hr.noteRequired');
        }

        return DB::transaction(function () use ($leave, $actor, $decision, $note) {
            $leave = StaffLeaveRequest::query()->with('staff')->lockForUpdate()->findOrFail($leave->id);
            if ($leave->status !== 'pending') {
                throw AcademicRuleException::because('err.hr.notPending');
            }
            $own = (int) $leave->staff?->user_id === (int) $actor->id || (int) $leave->applied_by === (int) $actor->id;
            if ($own && ! $this->settings->get('hr', 'self_approval', false)) {
                throw AcademicRuleException::because('err.hr.selfReview');
            }

            $leave->update(['status' => $decision, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'decision_note' => $note ?: null]);
            Ops::audit('hr.leave.'.($decision === 'approved' ? 'approved' : 'rejected'), 'Leave '.$decision.' for '.$leave->staff?->full_name, ['leave_id' => $leave->id, 'staff_id' => $leave->staff_id]);

            $applicant = $leave->staff?->user_id ? User::query()->where('id', $leave->staff->user_id)->where('school_id', $leave->school_id)->first() : null;
            Ops::notify([$applicant], $decision === 'approved' ? 'Leave approved' : 'Leave not approved',
                'Your '.$leave->leave_type.' leave from '.$leave->starts_on->format('d M Y').' was '.$decision.'.'.($note ? ' '.$note : ''),
                route('my.leave.index'), $decision === 'approved' ? 'success' : 'warning');

            return $leave;
        });
    }

    /** The applicant (or HR) withdraws a pending request, or an approved one that has not started. */
    public function cancel(StaffLeaveRequest $leave, User $actor): StaffLeaveRequest
    {
        return DB::transaction(function () use ($leave, $actor) {
            $leave = StaffLeaveRequest::query()->lockForUpdate()->findOrFail($leave->id);
            $open = $leave->status === 'pending' || ($leave->status === 'approved' && $leave->starts_on->gt(today()));
            if (! $open) {
                throw AcademicRuleException::because('err.hr.cannotCancel');
            }
            $leave->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            Ops::audit('hr.leave.cancelled', 'Leave cancelled', ['leave_id' => $leave->id, 'staff_id' => $leave->staff_id]);

            return $leave;
        });
    }

    /** Days taken and waiting per type for a calendar year, with the cap where the school set one. */
    public function balances(Staff $staff, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $rows = StaffLeaveRequest::query()->where('staff_id', $staff->id)->whereIn('status', ['pending', 'approved'])
            ->whereBetween('starts_on', [$year.'-01-01', $year.'-12-31'])->get();

        $out = [];
        foreach (self::TYPES as $type) {
            $out[] = [
                'type' => $type,
                'approved' => (int) $rows->where('leave_type', $type)->where('status', 'approved')->sum('days'),
                'pending' => (int) $rows->where('leave_type', $type)->where('status', 'pending')->sum('days'),
                'cap' => $this->capFor($type),
            ];
        }

        return $out;
    }

    private function capFor(string $type): ?int
    {
        return match ($type) {
            'annual' => $this->nullableInt($this->settings->get('hr', 'leave_annual_days')),
            'sick' => $this->nullableInt($this->settings->get('hr', 'leave_sick_days')),
            default => null,
        };
    }

    private function nullableInt(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }
}
