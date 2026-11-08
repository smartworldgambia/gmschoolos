<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * School-finance reports. Every figure is computed from invoices and CONFIRMED payments of the current school only.
 * Amounts are whole Dalasi.
 */
class FinanceReports
{
    /** @return array{from:string,to:string} an inclusive period, defaulting to the current calendar month */
    public function period(?string $from, ?string $to): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return ['from' => $start->toDateString(), 'to' => $end->toDateString()];
    }

    public function summary(string $from, string $to): array
    {
        $range = [$from.' 00:00:00', $to.' 23:59:59'];
        $live = fn () => Invoice::query()->where('status', '!=', 'cancelled');

        $billed = (int) $live()->whereBetween('issued_at', $range)->sum('subtotal');
        $adjusted = (int) $live()->whereBetween('issued_at', $range)->sum('adjustments_total');
        $collected = (int) Payment::query()->confirmed()->whereBetween('paid_at', $range)->sum('amount');
        $outstanding = (int) $live()->open()->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as d')->value('d');
        $overdue = (int) $live()->late()->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as d')->value('d');
        $allBilled = (int) $live()->sum('total_amount');
        $allCollected = (int) Payment::query()->confirmed()->sum('amount');

        return [
            'billed' => $billed, 'adjustments' => $adjusted, 'net_billed' => $billed - $adjusted, 'collected' => $collected,
            'outstanding' => $outstanding, 'overdue' => $overdue,
            'collection_rate' => $allBilled > 0 ? round(min(100, $allCollected / $allBilled * 100), 1) : 0,
            'pending_payments' => (int) Payment::query()->where('status', 'pending')->count(),
        ];
    }

    /** @return array<int,array{method:string,count:int,amount:int}> */
    public function byMethod(string $from, string $to): array
    {
        return Payment::query()->confirmed()->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->select('method', DB::raw('COUNT(*) as n'), DB::raw('SUM(amount) as total'))->groupBy('method')->orderByDesc('total')->get()
            ->map(fn ($r) => ['method' => $r->method, 'count' => (int) $r->n, 'amount' => (int) $r->total])->all();
    }

    /** Billed per fee category (fee lines) and the reductions given, for invoices issued in the period. */
    public function byCategory(string $from, string $to): array
    {
        $ids = Invoice::query()->where('status', '!=', 'cancelled')->whereBetween('issued_at', [$from.' 00:00:00', $to.' 23:59:59'])->pluck('id');

        $fees = InvoiceItem::query()->whereIn('invoice_id', $ids)->where('kind', 'fee')
            ->leftJoin('fee_categories', 'fee_categories.id', '=', 'invoice_items.fee_category_id')
            ->select(DB::raw('COALESCE(fee_categories.name, \'\') as category'), DB::raw('SUM(invoice_items.amount) as total'))
            ->groupBy('fee_categories.name')->orderByDesc('total')->get()
            ->map(fn ($r) => ['category' => (string) $r->category, 'amount' => (int) $r->total])->all();

        $reductions = InvoiceItem::query()->whereIn('invoice_id', $ids)->whereIn('kind', ['discount', 'scholarship', 'waiver'])
            ->select('kind', DB::raw('SUM(amount) as total'))->groupBy('kind')->get()
            ->map(fn ($r) => ['kind' => $r->kind, 'amount' => (int) $r->total])->all();

        return ['fees' => $fees, 'reductions' => $reductions];
    }

    /**
     * Open balances with ageing (days past the due date) and per-class totals.
     *
     * @return array{buckets:array<string,int>,classes:array<int,array<string,mixed>>,rows:array<int,array<string,mixed>>}
     */
    public function outstanding(?int $classId = null, int $limit = 500): array
    {
        $invoices = Invoice::query()->open()->with('student.schoolClass')
            ->when($classId, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('school_class_id', $classId)))
            ->orderBy('due_on')->get();

        $buckets = ['current' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90_plus' => 0];
        $classes = [];
        $rows = [];

        foreach ($invoices as $inv) {
            $balance = $inv->balance();
            if ($balance <= 0) {
                continue;
            }
            $days = $inv->due_on && $inv->due_on->lt(today()) ? (int) $inv->due_on->diffInDays(today()) : 0;
            $bucket = match (true) {
                $days === 0 => 'current', $days <= 30 => 'd1_30', $days <= 60 => 'd31_60', $days <= 90 => 'd61_90', default => 'd90_plus',
            };
            $buckets[$bucket] += $balance;

            $cid = (int) ($inv->student?->school_class_id ?? 0);
            $classes[$cid] ??= ['class_id' => $cid, 'class' => $inv->student?->schoolClass?->name ?? '', 'invoices' => 0, 'balance' => 0];
            $classes[$cid]['invoices']++;
            $classes[$cid]['balance'] += $balance;

            if (count($rows) < $limit) {
                $rows[] = [
                    'invoice_id' => $inv->id, 'invoice_number' => $inv->invoice_number, 'student' => $inv->student?->full_name,
                    'student_number' => $inv->student?->student_number, 'class' => $inv->student?->schoolClass?->name,
                    'total' => (int) $inv->total_amount, 'paid' => (int) $inv->amount_paid, 'balance' => $balance,
                    'due_on' => $inv->due_on?->toDateString(), 'days_overdue' => $days,
                ];
            }
        }

        return ['buckets' => $buckets, 'classes' => array_values($classes), 'rows' => $rows];
    }

    /** Everything one learner was billed and paid. */
    public function statement(Student $student): array
    {
        $invoices = Invoice::query()->where('student_id', $student->id)->where('status', '!=', 'cancelled')->with('items')->orderBy('issued_at')->get();
        $payments = Payment::query()->confirmed()->where('student_id', $student->id)->orderBy('paid_at')->get();
        $billed = (int) $invoices->sum('total_amount');
        $paid = (int) $payments->sum('amount');

        return [
            'student' => ['id' => $student->id, 'name' => $student->full_name, 'student_number' => $student->student_number],
            'invoices' => $invoices->map(fn (Invoice $i) => ['number' => $i->invoice_number, 'issued_at' => $i->issued_at?->toDateString(), 'total' => (int) $i->total_amount, 'paid' => (int) $i->amount_paid, 'status' => $i->displayStatus()])->all(),
            'payments' => $payments->map(fn (Payment $p) => ['receipt' => $p->receipt_number, 'paid_at' => $p->paid_at?->toDateString(), 'amount' => (int) $p->amount, 'method' => $p->method])->all(),
            'billed' => $billed, 'paid' => $paid, 'balance' => max(0, $billed - $paid),
        ];
    }
}
