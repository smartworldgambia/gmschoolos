<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\StaffDocument;
use App\Models\StaffLeaveRequest;
use App\Services\Hr\HrAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** HR overview: headcount, who is away, what needs a decision and what is about to expire. */
class HrController extends Controller
{
    use SharesSchoolShell;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('hr.view'), 403);

        $today = today()->toDateString();
        $soon = today()->addDays(45)->toDateString();

        $props = [
            'stats' => [
                'staff' => Staff::query()->where('status', 'active')->count(),
                'away' => StaffLeaveRequest::query()->where('status', 'approved')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->count(),
                'pending_leave' => StaffLeaveRequest::query()->where('status', 'pending')->count(),
            ],
            'pendingLeave' => StaffLeaveRequest::query()->with('staff:id,first_name,last_name')->where('status', 'pending')->orderBy('starts_on')->limit(8)->get()
                ->map(fn (StaffLeaveRequest $l) => ['id' => $l->id, 'staff' => $l->staff?->full_name, 'leave_type' => $l->leave_type, 'starts_on' => $l->starts_on?->toDateString(), 'ends_on' => $l->ends_on?->toDateString(), 'days' => $l->days]),
            'awayToday' => StaffLeaveRequest::query()->with('staff:id,first_name,last_name')->where('status', 'approved')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->limit(20)->get()
                ->map(fn (StaffLeaveRequest $l) => ['id' => $l->id, 'staff' => $l->staff?->full_name, 'leave_type' => $l->leave_type, 'ends_on' => $l->ends_on?->toDateString()]),
            'endingContracts' => StaffContract::query()->with('staff:id,first_name,last_name')->where('status', 'active')->whereNotNull('ends_on')->whereBetween('ends_on', [$today, $soon])->orderBy('ends_on')->limit(10)->get()
                ->map(fn (StaffContract $c) => ['id' => $c->id, 'staff_id' => $c->staff_id, 'staff' => $c->staff?->full_name, 'ends_on' => $c->ends_on?->toDateString()]),
            'expiringDocuments' => $user->can('hr.manage')
                ? StaffDocument::query()->with('staff:id,first_name,last_name')->whereNotNull('expires_on')->where('expires_on', '<=', $soon)->orderBy('expires_on')->limit(10)->get()
                    ->map(fn (StaffDocument $d) => ['id' => $d->id, 'staff_id' => $d->staff_id, 'staff' => $d->staff?->full_name, 'title' => $d->title, 'expires_on' => $d->expires_on?->toDateString()])
                : [],
            'latestRun' => HrAccess::seesPay($user) ? PayrollRun::query()->latest('id')->first(['id', 'period_label', 'status', 'net_total']) : null,
            'can' => ['manage' => $user->can('hr.manage'), 'pay' => HrAccess::seesPay($user)],
        ];

        return Inertia::render('Hr/Index', array_merge($this->shellProps(), $props));
    }
}
