<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffLeaveRequest;
use App\Services\Hr\HrAccess;
use App\Services\Hr\LeaveService;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Leave: HR sees and decides every request; every staff member applies for their own through /my/leave. */
class LeaveController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request, OpsSettings $settings): Response
    {
        $status = (string) $request->query('status', 'pending');
        $user = $request->user();

        $leave = StaffLeaveRequest::query()->with(['staff:id,first_name,last_name,user_id', 'reviewer:id,name'])
            ->when(in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->orderByDesc('starts_on')->paginate(25)->withQueryString();

        return Inertia::render('Hr/Leave', array_merge($this->shellProps(), [
            'leave' => $leave->through(fn (StaffLeaveRequest $l) => $this->row($l, $user->id)),
            'filter' => $status,
            'staff' => Staff::query()->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
            'types' => LeaveService::TYPES,
            'caps' => ['annual' => $settings->get('hr', 'leave_annual_days'), 'sick' => $settings->get('hr', 'leave_sick_days')],
            'can' => ['manage' => $user->can('hr.manage')],
        ]));
    }

    /** HR records leave on behalf of a staff member (route name kept from the original foundation). */
    public function store(Request $request, LeaveService $service): RedirectResponse
    {
        $v = $request->validate([
            'staff_id' => ['required', $this->inSchool('staff')],
            'leave_type' => ['required', Rule::in(LeaveService::TYPES)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->attempt(fn () => $service->apply(Staff::query()->findOrFail($v['staff_id']), $request->user(), $v['leave_type'], $v['starts_on'], $v['ends_on'], $v['days'] ?? null, $v['reason'] ?? null), 'ok.hr.leaveRecorded');
    }

    public function review(Request $request, StaffLeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $v = $request->validate(['status' => ['required', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->review($leave, $request->user(), $v['status'], $v['note'] ?? null), $v['status'] === 'approved' ? 'ok.hr.leaveApproved' : 'ok.hr.leaveRejected');
    }

    public function cancel(Request $request, StaffLeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->cancel($leave, $request->user()), 'ok.hr.leaveCancelled');
    }

    // ------------------------------------------------------------------ self-service

    public function mine(Request $request, LeaveService $service, OpsSettings $settings): Response
    {
        $staff = HrAccess::ownStaff($request->user());

        return Inertia::render('Hr/MyLeave', array_merge($this->shellProps(), [
            'linked' => $staff !== null,
            'leave' => $staff ? StaffLeaveRequest::query()->with(['staff:id,first_name,last_name,user_id', 'reviewer:id,name'])->where('staff_id', $staff->id)->latest('starts_on')->limit(50)->get()->map(fn (StaffLeaveRequest $l) => $this->row($l, $request->user()->id) + ['cancellable' => $l->status === 'pending' || ($l->status === 'approved' && $l->starts_on->gt(today()))]) : [],
            'balances' => $staff ? $service->balances($staff) : [],
            'types' => LeaveService::TYPES,
        ]));
    }

    public function applyMine(Request $request, LeaveService $service): RedirectResponse
    {
        $staff = HrAccess::ownStaff($request->user());
        $v = $request->validate([
            'leave_type' => ['required', Rule::in(LeaveService::TYPES)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->attempt(function () use ($staff, $v, $request, $service) {
            if (! $staff) {
                throw AcademicRuleException::because('err.hr.notLinked');
            }

            return $service->apply($staff, $request->user(), $v['leave_type'], $v['starts_on'], $v['ends_on'], $v['days'] ?? null, $v['reason'] ?? null);
        }, 'ok.hr.leaveApplied');
    }

    public function cancelMine(Request $request, StaffLeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $staff = HrAccess::ownStaff($request->user());
        abort_unless($staff && (int) $leave->staff_id === (int) $staff->id, 403);

        return $this->attempt(fn () => $service->cancel($leave, $request->user()), 'ok.hr.leaveCancelled');
    }

    private function row(StaffLeaveRequest $l, int $viewerId): array
    {
        return [
            'id' => $l->id, 'staff' => $l->staff?->full_name, 'leave_type' => $l->leave_type, 'starts_on' => $l->starts_on?->toDateString(), 'ends_on' => $l->ends_on?->toDateString(),
            'days' => $l->days, 'status' => $l->status, 'reason' => $l->reason, 'decision_note' => $l->decision_note, 'reviewer' => $l->reviewer?->name ?? null,
            'own' => (int) $l->staff?->user_id === $viewerId || (int) $l->applied_by === $viewerId,
        ];
    }
}
