<?php

namespace App\Http\Controllers\Fees;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentReconciliation;
use App\Services\Ops\Ops;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checking the school's own records against what actually arrived (the cash drawer count, the Wave statement, the bank statement).
 * A run covers CONFIRMED, not-yet-reconciled payments the officer ticks; the officer enters the amount the statement shows and the
 * difference is recorded, never hidden. A reconciled payment can no longer be voided.
 */
class ReconciliationController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(): Response
    {
        $open = Payment::query()->confirmed()->where('is_reconciled', false);

        return Inertia::render('Fees/Reconciliation', array_merge($this->shellProps(), [
            'unreconciledPayments' => (clone $open)->with('student:id,first_name,last_name')->latest('paid_at')->limit(200)->get()->map(fn (Payment $p) => [
                'id' => $p->id, 'receipt_number' => $p->receipt_number, 'student_name' => $p->student?->full_name, 'amount' => (int) $p->amount,
                'method' => $p->method, 'external_reference' => $p->external_reference, 'paid_at' => $p->paid_at?->toDateString(),
            ]),
            'byMethod' => (clone $open)->select('method', DB::raw('COUNT(*) as n'), DB::raw('SUM(amount) as total'))->groupBy('method')->get()
                ->map(fn ($r) => ['method' => $r->method, 'count' => (int) $r->n, 'amount' => (int) $r->total]),
            'summary' => [
                'unreconciled' => (int) (clone $open)->sum('amount'),
                'unreconciled_label' => Money::format((int) (clone $open)->sum('amount')),
                'pending' => Payment::query()->where('status', 'pending')->count(),
            ],
            'reconciliations' => PaymentReconciliation::query()->latest('id')->limit(30)->get()->map(fn (PaymentReconciliation $r) => [
                'id' => $r->id, 'reference' => $r->reference, 'period_starts_on' => $r->period_starts_on?->toDateString(), 'period_ends_on' => $r->period_ends_on?->toDateString(),
                'statement_amount' => (int) $r->expected_amount, 'collected_amount' => (int) $r->collected_amount, 'variance_amount' => (int) $r->variance_amount,
                'status' => $r->status, 'notes' => $r->notes,
            ]),
            'storeUrl' => route('fees.reconciliation.store'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('payments.verify'), 403);

        $v = $request->validate([
            'period_starts_on' => ['required', 'date'],
            'period_ends_on' => ['required', 'date', 'after_or_equal:period_starts_on'],
            'payment_ids' => ['required', 'array', 'min:1', 'max:500'],
            'payment_ids.*' => ['integer', $this->inSchool('payments')],
            'statement_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->attempt(function () use ($v, $request) {
            DB::transaction(function () use ($v, $request) {
                $payments = Payment::query()->whereIn('id', $v['payment_ids'])->confirmed()->where('is_reconciled', false)->lockForUpdate()->get();

                if ($payments->isEmpty()) {
                    throw AcademicRuleException::because('err.fin.nothingToReconcile');
                }
                $from = $v['period_starts_on'].' 00:00:00';
                $to = $v['period_ends_on'].' 23:59:59';
                if ($payments->contains(fn (Payment $p) => $p->paid_at->format('Y-m-d H:i:s') < $from || $p->paid_at->format('Y-m-d H:i:s') > $to)) {
                    throw AcademicRuleException::because('err.fin.outsidePeriod');
                }

                $collected = (int) $payments->sum('amount');
                $statement = isset($v['statement_amount']) ? (int) $v['statement_amount'] : $collected;
                $variance = abs($statement - $collected);

                $run = PaymentReconciliation::query()->create([
                    'reference' => 'REC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                    'period_starts_on' => $v['period_starts_on'], 'period_ends_on' => $v['period_ends_on'],
                    'expected_amount' => $statement, 'collected_amount' => $collected, 'variance_amount' => $variance,
                    'status' => $variance === 0 ? 'matched' : 'variance', 'notes' => $v['notes'] ?? null,
                    'reconciled_by' => $request->user()->id, 'reconciled_at' => now(),
                ]);

                Payment::query()->whereIn('id', $payments->pluck('id'))->update(['is_reconciled' => true, 'payment_reconciliation_id' => $run->id]);

                Ops::audit('fin.reconciliation.saved', 'Reconciliation '.$run->reference, ['reconciliation_id' => $run->id, 'payments' => $payments->count(), 'collected' => $collected, 'statement' => $statement, 'variance' => $variance]);
            });
        }, 'ok.fin.reconciled');
    }
}
