<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolPaymentMethod;
use App\Services\Finance\FinanceAccess;
use App\Services\Finance\FinanceReports;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Fees landing page: the school's money overview for staff, "my fees" for a parent or learner. */
class FeeController extends Controller
{
    use SharesSchoolShell;

    public function index(Request $request, FinanceReports $reports, OpsSettings $settings): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $user = $request->user();
        $staff = FinanceAccess::isStaff($user);

        $invoices = FinanceAccess::invoicesFor($user)->with(['student:id,first_name,last_name,student_number', 'installments'])->latest('id')->limit(60)->get();
        $payments = FinanceAccess::scope(Payment::query(), $user)->with('student:id,first_name,last_name')->latest('id')->limit(15)->get();

        $props = [
            'mode' => $staff ? 'staff' : 'family',
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id, 'invoice_number' => $i->invoice_number, 'student' => $i->student?->full_name, 'student_id' => $i->student_id,
                'total_amount' => (int) $i->total_amount, 'amount_paid' => (int) $i->amount_paid, 'balance' => $i->balance(),
                'status' => $i->displayStatus(), 'due_on' => $i->due_on?->toDateString(), 'installments' => $i->installments->count(),
            ]),
            'recentPayments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id, 'receipt_number' => $p->receipt_number, 'student' => $p->student?->full_name,
                'amount' => (int) $p->amount, 'method' => $p->method, 'status' => $p->status, 'paid_at' => $p->paid_at?->toDateString(),
            ]),
            'can' => [
                'manage' => $user->can('fees.manage'), 'record' => $user->can('payments.record'), 'verify' => $user->can('payments.verify'),
                'approve' => $user->can('fees.approve'), 'reports' => $user->canAny(['reports.view']) && $staff,
            ],
        ];

        if ($staff) {
            $period = $reports->period(null, null);
            $props['stats'] = $reports->summary($period['from'], $period['to']);
            $props['period'] = $period;
        } else {
            $billed = (int) $invoices->where('status', '!=', 'cancelled')->sum('total_amount');
            $paid = (int) $invoices->where('status', '!=', 'cancelled')->sum('amount_paid');
            $props['stats'] = ['billed' => $billed, 'collected' => $paid, 'outstanding' => max(0, $billed - $paid)];
            $props['methods'] = SchoolPaymentMethod::query()->where('is_active', true)->where('method', '!=', 'cash')->orderBy('sort_order')->orderBy('label')
                ->get(['id', 'method', 'label', 'instructions', 'account_name', 'account_number', 'bank_name']);
            $props['submissionsOn'] = (bool) $settings->get('finance', 'parent_submissions', true);
        }

        return Inertia::render('Fees/Index', array_merge($this->shellProps(), $props));
    }
}
