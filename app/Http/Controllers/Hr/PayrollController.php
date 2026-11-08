<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentStaff;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Services\Hr\HrAccess;
use App\Services\Hr\PayrollService;
use App\Services\Hr\PayslipPdf;
use App\Services\Ops\Ops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pay components, payroll runs (draft -> approved -> paid), CSV and payslips, and each staff member's own payslips. */
class PayrollController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Hr/Payroll', array_merge($this->shellProps(), [
            'runs' => PayrollRun::query()->withCount('items')->latest('id')->limit(36)->get()->map(fn (PayrollRun $r) => [
                'id' => $r->id, 'period_label' => $r->period_label, 'period_start' => $r->period_start?->toDateString(), 'period_end' => $r->period_end?->toDateString(),
                'status' => $r->status, 'gross_total' => (int) $r->gross_total, 'net_total' => (int) $r->net_total, 'items_count' => $r->items_count,
            ]),
            'components' => PayrollComponent::query()->withCount('assignments')->orderBy('sort_order')->orderBy('id')->get()->map(fn (PayrollComponent $c) => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'kind' => $c->kind, 'calc' => $c->calc, 'value' => (float) $c->value,
                'applies_to' => $c->applies_to, 'is_active' => (bool) $c->is_active, 'sort_order' => (int) $c->sort_order, 'assigned' => $c->assignments_count,
            ]),
            'staff' => Staff::query()->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
            'assignments' => PayrollComponentStaff::query()->get(['payroll_component_id', 'staff_id', 'value_override'])->groupBy('payroll_component_id')
                ->map(fn ($rows) => $rows->map(fn ($r) => ['staff_id' => $r->staff_id, 'value_override' => $r->value_override])->values()),
            'can' => ['manage' => $user->can('payroll.manage'), 'approve' => $user->can('payroll.approve')],
        ]));
    }

    /** Create a draft run (route name kept from the original foundation). `basic_salary` is only the fallback for staff with no contract. */
    public function store(Request $request, PayrollService $service): RedirectResponse
    {
        $v = $request->validate([
            'period_label' => ['required', 'string', 'max:20'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'basic_salary' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->attempt(function () use ($v, $request, $service) {
            $run = $service->create($request->user(), trim($v['period_label']), $v['period_start'], $v['period_end'], isset($v['basic_salary']) ? (int) $v['basic_salary'] : null, $v['notes'] ?? null);

            return redirect()->route('hr.payroll.show', $run)->with('success', 'ok.hr.payrollCreated');
        });
    }

    public function show(Request $request, PayrollRun $payroll): Response
    {
        $user = $request->user();
        $items = $payroll->items()->with('staff:id,first_name,last_name,staff_number')->get()->sortBy(fn ($i) => $i->staff?->last_name);

        return Inertia::render('Hr/PayrollShow', array_merge($this->shellProps(), [
            'run' => [
                'id' => $payroll->id, 'period_label' => $payroll->period_label, 'period_start' => $payroll->period_start?->toDateString(), 'period_end' => $payroll->period_end?->toDateString(),
                'status' => $payroll->status, 'gross_total' => (int) $payroll->gross_total, 'net_total' => (int) $payroll->net_total, 'notes' => $payroll->notes,
                'posted_at' => $payroll->posted_at?->toDateString(), 'paid_at' => $payroll->paid_at?->toDateString(), 'payment_reference' => $payroll->payment_reference,
                'created_by_me' => (int) $payroll->created_by === (int) $user->id,
            ],
            'items' => $items->values()->map(fn (PayrollItem $i) => [
                'id' => $i->id, 'staff' => $i->staff?->full_name, 'staff_number' => $i->staff?->staff_number, 'basic' => (int) $i->basic_salary, 'allowances' => (int) $i->allowances,
                'deductions' => (int) $i->deductions, 'gross' => (int) $i->gross_pay, 'net' => (int) $i->net_pay, 'lines' => $i->lines ?? [],
            ]),
            'can' => ['manage' => $user->can('payroll.manage'), 'approve' => $user->can('payroll.approve')],
        ]));
    }

    public function post(Request $request, PayrollRun $payroll, PayrollService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->post($payroll, $request->user()), 'ok.hr.payrollPosted');
    }

    public function pay(Request $request, PayrollRun $payroll, PayrollService $service): RedirectResponse
    {
        $v = $request->validate(['payment_reference' => ['required', 'string', 'max:120'], 'paid_on' => ['nullable', 'date', 'before_or_equal:today']]);

        return $this->attempt(fn () => $service->markPaid($payroll, $request->user(), $v['payment_reference'], $v['paid_on'] ?? null), 'ok.hr.payrollPaid');
    }

    public function destroy(PayrollRun $payroll, PayrollService $service): RedirectResponse
    {
        return $this->attempt(function () use ($payroll, $service) {
            $service->discard($payroll);

            return redirect()->route('hr.payroll.index')->with('success', 'ok.hr.payrollDiscarded');
        });
    }

    public function export(Request $request, PayrollRun $payroll)
    {
        Ops::audit('hr.payroll.exported', 'Payroll '.$payroll->period_label.' exported', ['run_id' => $payroll->id]);
        $rows = $payroll->items()->with('staff:id,first_name,last_name,staff_number')->get()->map(fn ($i) => [$i->staff?->staff_number, $i->staff?->full_name, $i->basic_salary, $i->allowances, $i->gross_pay, $i->deductions, $i->net_pay]);

        return Ops::csv('payroll-'.preg_replace('/[^A-Za-z0-9_-]/', '', $payroll->period_label).'.csv', ['Staff no.', 'Name', 'Basic', 'Allowances', 'Gross', 'Deductions', 'Net'], $rows);
    }

    // ------------------------------------------------------------------ components

    public function storeComponent(Request $request): RedirectResponse
    {
        $v = $this->componentRules($request, null);

        return $this->attempt(function () use ($v) {
            $this->assertComponent($v);
            $c = PayrollComponent::query()->create($v + ['is_active' => true]);
            Ops::audit('hr.component.created', 'Pay component created', ['component_id' => $c->id, 'kind' => $c->kind, 'calc' => $c->calc]);
        }, 'ok.hr.componentSaved');
    }

    public function updateComponent(Request $request, PayrollComponent $component): RedirectResponse
    {
        $v = $this->componentRules($request, $component) + $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->attempt(function () use ($v, $component) {
            $this->assertComponent($v);
            $component->update($v);
            Ops::audit('hr.component.updated', 'Pay component updated', ['component_id' => $component->id]);
        }, 'ok.hr.componentSaved');
    }

    /** Replace the list of staff a component applies to ("assigned" components only), with an optional per-person value. */
    public function assignComponent(Request $request, PayrollComponent $component): RedirectResponse
    {
        $v = $request->validate([
            'assignments' => ['present', 'array', 'max:500'],
            'assignments.*.staff_id' => ['required', 'integer', $this->inSchool('staff')],
            'assignments.*.value_override' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        return $this->attempt(function () use ($v, $component) {
            if ($component->applies_to !== 'assigned') {
                throw AcademicRuleException::because('err.hr.componentAll');
            }
            DB::transaction(function () use ($v, $component) {
                PayrollComponentStaff::query()->where('payroll_component_id', $component->id)->delete();
                foreach (collect($v['assignments'])->unique('staff_id') as $a) {
                    if ($component->calc === 'percent_of_basic' && isset($a['value_override']) && (float) $a['value_override'] > 100) {
                        throw AcademicRuleException::because('err.hr.componentPercent');
                    }
                    PayrollComponentStaff::query()->create(['payroll_component_id' => $component->id, 'staff_id' => $a['staff_id'], 'value_override' => $a['value_override'] ?? null]);
                }
            });
            Ops::audit('hr.component.assigned', 'Pay component assignments saved', ['component_id' => $component->id, 'staff' => count($v['assignments'])]);
        }, 'ok.hr.componentAssigned');
    }

    // ------------------------------------------------------------------ payslips

    public function payslip(Request $request, PayrollItem $item, PayslipPdf $pdf)
    {
        $user = $request->user();
        $item->loadMissing(['run', 'staff']);
        $own = $item->staff && (int) $item->staff->user_id === (int) $user->id;
        $manager = HrAccess::seesPay($user);
        abort_unless($manager || ($own && $user->can('staff.self')), 403);
        // staff see only approved runs; payroll managers can preview a draft (it is watermarked)
        abort_unless($manager || in_array($item->run?->status, ['posted', 'paid'], true), 404);

        return response($pdf->render($item), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="payslip-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $item->run?->period_label).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function mine(Request $request): Response
    {
        $staff = HrAccess::ownStaff($request->user());
        $items = $staff
            ? PayrollItem::query()->with('run')->where('staff_id', $staff->id)->whereHas('run', fn ($q) => $q->whereIn('status', ['posted', 'paid']))->latest('id')->limit(36)->get()
            : collect();

        return Inertia::render('Hr/MyPayslips', array_merge($this->shellProps(), [
            'linked' => $staff !== null,
            'payslips' => $items->map(fn (PayrollItem $i) => [
                'id' => $i->id, 'period_label' => $i->run?->period_label, 'status' => $i->run?->status, 'gross' => (int) $i->gross_pay, 'deductions' => (int) $i->deductions,
                'net' => (int) $i->net_pay, 'paid_at' => $i->run?->paid_at?->toDateString(),
            ]),
        ]));
    }

    // ------------------------------------------------------------------ helpers

    private function componentRules(Request $request, ?PayrollComponent $component): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('payroll_components', 'code')->where('school_id', $this->schoolId())->ignore($component?->id)],
            'kind' => ['required', 'in:allowance,deduction'],
            'calc' => ['required', 'in:fixed,percent_of_basic'],
            'value' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'applies_to' => ['required', 'in:all,assigned'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    private function assertComponent(array &$v): void
    {
        if ($v['calc'] === 'percent_of_basic' && (float) $v['value'] > 100) {
            throw AcademicRuleException::because('err.hr.componentPercent');
        }
        $v['sort_order'] = (int) ($v['sort_order'] ?? 0);
    }
}
