<?php

namespace App\Services\Hr;

use App\Exceptions\AcademicRuleException;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentStaff;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\User;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Configurable payroll. Pay lines come only from (1) the staff member's contract salary for the period and (2) the school's own
 * allowance / deduction components. The platform ships NO tax, pension or social-security rate: whatever a school's accountant
 * enters is what the payslip shows. A run goes draft -> posted (a second person approves) -> paid, and a posted run never changes.
 */
class PayrollService
{
    public function __construct(private readonly OpsSettings $settings) {}

    /**
     * Build a draft run for every active staff member with something to pay.
     *
     * @param  int|null  $defaultBasic  used only for staff who have no contract covering the period
     */
    public function create(User $actor, string $label, string $start, string $end, ?int $defaultBasic, ?string $notes): PayrollRun
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($to->lt($from)) {
            throw AcademicRuleException::because('err.hr.periodDates');
        }
        if (PayrollRun::query()->where('period_label', $label)->exists()) {
            throw AcademicRuleException::because('err.hr.runExists');
        }
        if (PayrollRun::query()->where('period_start', '<=', $to->toDateString())->where('period_end', '>=', $from->toDateString())->exists()) {
            throw AcademicRuleException::because('err.hr.periodOverlap');
        }

        $components = PayrollComponent::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $assigned = PayrollComponentStaff::query()->get()->groupBy('staff_id');
        $staffList = Staff::query()->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('hired_on')->orWhere('hired_on', '<=', $to->toDateString()))
            ->where(fn ($q) => $q->whereNull('exited_on')->orWhere('exited_on', '>=', $from->toDateString()))
            ->orderBy('last_name')->orderBy('first_name')->get();

        return DB::transaction(function () use ($actor, $label, $from, $to, $defaultBasic, $notes, $components, $assigned, $staffList) {
            $run = PayrollRun::query()->create([
                'period_label' => $label, 'period_start' => $from->toDateString(), 'period_end' => $to->toDateString(), 'status' => 'draft',
                'created_by' => $actor->id, 'notes' => $notes ? mb_substr(trim($notes), 0, 500) : null,
            ]);

            $gross = 0;
            $net = 0;
            $count = 0;
            foreach ($staffList as $staff) {
                $contract = StaffContract::query()->where('staff_id', $staff->id)
                    ->where('starts_on', '<=', $to->toDateString())
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $from->toDateString()))
                    ->orderByDesc('starts_on')->first();
                $basic = $contract ? (int) $contract->basic_salary : (int) ($defaultBasic ?? 0);

                [$lines, $allow, $deduct] = $this->lines($basic, $components, $assigned->get($staff->id, collect()));
                if ($basic <= 0 && $allow <= 0) {
                    continue;
                }
                $grossPay = $basic + $allow;
                if ($deduct > $grossPay) {
                    throw AcademicRuleException::because('err.hr.negativeNet');
                }

                PayrollItem::query()->create([
                    'payroll_run_id' => $run->id, 'staff_id' => $staff->id, 'basic_salary' => $basic, 'allowances' => $allow, 'deductions' => $deduct,
                    'gross_pay' => $grossPay, 'net_pay' => $grossPay - $deduct, 'lines' => $lines,
                    'meta' => ['contract_id' => $contract?->id, 'source' => $contract ? 'contract' : 'default'],
                ]);
                $gross += $grossPay;
                $net += $grossPay - $deduct;
                $count++;
            }

            if ($count === 0) {
                throw AcademicRuleException::because('err.hr.nothingToPay');
            }

            $run->update(['gross_total' => $gross, 'net_total' => $net]);
            Ops::audit('hr.payroll.created', 'Payroll draft '.$label.' created', ['run_id' => $run->id, 'staff' => $count, 'gross' => $gross, 'net' => $net]);

            return $run;
        });
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:int,2:int} lines, total allowances, total deductions
     */
    public function lines(int $basic, $components, $assignments): array
    {
        $byComponent = collect($assignments)->keyBy('payroll_component_id');
        $lines = [['kind' => 'basic', 'name' => 'Basic salary', 'code' => 'BASIC', 'calc' => 'fixed', 'value' => $basic, 'amount' => $basic]];
        $allow = 0;
        $deduct = 0;

        foreach ($components as $c) {
            $row = $byComponent->get($c->id);
            if ($c->applies_to === 'assigned' && ! $row) {
                continue;
            }
            $value = (float) ($row?->value_override ?? $c->value);
            $amount = $c->calc === 'percent_of_basic' ? Ops::percentOf($basic, $value) : (int) round($value);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = ['kind' => $c->kind, 'name' => $c->name, 'code' => $c->code, 'calc' => $c->calc, 'value' => $value, 'amount' => $amount];
            $c->kind === 'allowance' ? $allow += $amount : $deduct += $amount;
        }

        return [$lines, $allow, $deduct];
    }

    /** A second person approves (unless the school allows approving your own run); a posted run is final. */
    public function post(PayrollRun $run, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'draft') {
                throw AcademicRuleException::because('err.hr.notDraft');
            }
            if ((int) $run->created_by === (int) $actor->id && ! $this->settings->get('payroll', 'self_approval', false)) {
                throw AcademicRuleException::because('err.hr.selfApprovePayroll');
            }
            $run->update(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()]);
            Ops::audit('hr.payroll.posted', 'Payroll '.$run->period_label.' approved and posted', ['run_id' => $run->id, 'gross' => $run->gross_total, 'net' => $run->net_total]);

            return $run;
        });
    }

    public function markPaid(PayrollRun $run, User $actor, string $reference, ?string $paidOn): PayrollRun
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw AcademicRuleException::because('err.hr.referenceRequired');
        }

        return DB::transaction(function () use ($run, $actor, $reference, $paidOn) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'posted') {
                throw AcademicRuleException::because('err.hr.notPosted');
            }
            $run->update(['status' => 'paid', 'paid_at' => $paidOn ? Carbon::parse($paidOn)->setTimeFrom(now()) : now(), 'paid_by' => $actor->id, 'payment_reference' => mb_substr($reference, 0, 120)]);
            Ops::audit('hr.payroll.paid', 'Payroll '.$run->period_label.' marked paid', ['run_id' => $run->id, 'net' => $run->net_total]);

            return $run;
        });
    }

    public function discard(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'draft') {
                throw AcademicRuleException::because('err.hr.notDraft');
            }
            $label = $run->period_label;
            $run->items()->delete();
            $run->delete();
            Ops::audit('hr.payroll.discarded', 'Payroll draft '.$label.' discarded', ['period' => $label]);
        });
    }
}
