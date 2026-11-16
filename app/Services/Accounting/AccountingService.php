<?php

namespace App\Services\Accounting;

use App\Exceptions\AcademicRuleException;
use App\Models\FeeAdjustment;
use App\Models\GlAccount;
use App\Models\GlAccountMap;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Models\GlPeriodLock;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tenant AIS: reports and auto-posting from fees / payroll / procurement.
 * Auto-post never throws into the source module — missing maps simply skip.
 */
class AccountingService
{
    public function __construct(
        private readonly ChartOfAccounts $coa,
        private readonly JournalPoster $poster,
    ) {}

    public function ensureReady(): void
    {
        $this->coa->ensureFor();
    }

    public function setupNeeded(): bool
    {
        $this->ensureReady();

        return ! $this->coa->feesReady();
    }

    // ------------------------------------------------------------------ auto-post from school MIS

    public function postFeeInvoice(Invoice $invoice, ?User $actor = null): ?GlJournal
    {
        $this->ensureReady();
        $ar = $this->coa->mapped('fees_receivable');
        $income = $this->coa->mapped('fee_income');
        if (! $ar || ! $income) {
            return null;
        }

        $amount = (int) $invoice->total_amount;
        if ($amount < 1) {
            return null;
        }

        return $this->poster->tryPost(
            'fee_invoice',
            ($invoice->issued_at ?? now())->toDateString(),
            'Invoice '.$invoice->invoice_number,
            [
                ['account_id' => $ar->id, 'debit' => $amount],
                ['account_id' => $income->id, 'credit' => $amount],
            ],
            $actor,
            $invoice,
        );
    }

    public function postFeePayment(Payment $payment, ?User $actor = null): ?GlJournal
    {
        $this->ensureReady();
        if ($payment->status !== 'confirmed') {
            return null;
        }

        $ar = $this->coa->mapped('fees_receivable');
        $cashKey = in_array($payment->method, ['bank_transfer', 'wave', 'card'], true) ? 'bank' : 'cash';
        $cash = $this->coa->mapped($cashKey) ?? $this->coa->mapped('cash') ?? $this->coa->mapped('bank');
        if (! $ar || ! $cash) {
            return null;
        }

        $amount = (int) $payment->amount;
        if ($amount < 1) {
            return null;
        }

        return $this->poster->tryPost(
            'fee_payment',
            ($payment->paid_at ?? now())->toDateString(),
            'Payment '.($payment->receipt_number ?: '#'.$payment->id),
            [
                ['account_id' => $cash->id, 'debit' => $amount],
                ['account_id' => $ar->id, 'credit' => $amount],
            ],
            $actor,
            $payment,
        );
    }

    public function reverseFeePayment(Payment $payment, User $actor): ?GlJournal
    {
        $journal = GlJournal::query()
            ->where('source', 'fee_payment')
            ->where('source_ref_type', $payment->getMorphClass())
            ->where('source_ref_id', $payment->id)
            ->where('status', 'posted')
            ->first();

        if (! $journal) {
            return null;
        }

        try {
            return $this->poster->reverse($journal, $actor, 'Void payment '.$payment->receipt_number);
        } catch (AcademicRuleException) {
            return null;
        }
    }

    public function postFeeAdjustment(FeeAdjustment $adjustment, ?User $actor = null): ?GlJournal
    {
        $this->ensureReady();
        $discounts = $this->coa->mapped('discounts');
        $ar = $this->coa->mapped('fees_receivable');
        if (! $discounts || ! $ar) {
            return null;
        }

        $amount = (int) $adjustment->amount;
        if ($amount < 1) {
            return null;
        }

        // Discount / waiver reduces receivable and records an expense (contra income).
        return $this->poster->tryPost(
            'fee_adjustment',
            now()->toDateString(),
            'Adjustment #'.$adjustment->id,
            [
                ['account_id' => $discounts->id, 'debit' => $amount],
                ['account_id' => $ar->id, 'credit' => $amount],
            ],
            $actor,
            $adjustment,
        );
    }

    public function postPayrollPaid(PayrollRun $run, ?User $actor = null): ?GlJournal
    {
        $this->ensureReady();
        $expense = $this->coa->mapped('payroll_expense');
        $cash = $this->coa->mapped('cash') ?? $this->coa->mapped('bank');
        if (! $expense || ! $cash) {
            return null;
        }

        $amount = (int) $run->net_total;
        if ($amount < 1) {
            return null;
        }

        return $this->poster->tryPost(
            'payroll',
            ($run->paid_at ?? now())->toDateString(),
            'Payroll '.$run->period_label,
            [
                ['account_id' => $expense->id, 'debit' => $amount],
                ['account_id' => $cash->id, 'credit' => $amount],
            ],
            $actor,
            $run,
        );
    }

    public function postProcurementInvoice(SupplierInvoice $inv, ?User $actor = null): ?GlJournal
    {
        $this->ensureReady();
        $ap = $this->coa->mapped('ap');
        $dr = $this->coa->mapped('inventory')
            ?? GlAccount::query()->where('code', '5300')->first()
            ?? GlAccount::query()->where('type', 'expense')->orderBy('code')->first();
        if (! $dr || ! $ap) {
            return null;
        }

        $amount = (int) $inv->amount;
        if ($amount < 1) {
            return null;
        }

        return $this->poster->tryPost(
            'procurement',
            now()->toDateString(),
            'Supplier invoice '.$inv->invoice_number,
            [
                ['account_id' => $dr->id, 'debit' => $amount],
                ['account_id' => $ap->id, 'credit' => $amount],
            ],
            $actor,
            $inv,
        );
    }

    public function postProcurementPayment(SupplierInvoice $inv, ?User $actor = null): ?GlJournal
    {
        $this->ensureReady();
        $ap = $this->coa->mapped('ap');
        $cash = $this->coa->mapped('bank') ?? $this->coa->mapped('cash');
        if (! $ap || ! $cash) {
            return null;
        }

        $amount = (int) $inv->amount;
        if ($amount < 1) {
            return null;
        }

        return $this->poster->tryPost(
            'procurement_pay',
            $inv->paid_on?->toDateString() ?? now()->toDateString(),
            'Pay supplier '.$inv->invoice_number,
            [
                ['account_id' => $ap->id, 'debit' => $amount],
                ['account_id' => $cash->id, 'credit' => $amount],
            ],
            $actor,
            $inv,
        );
    }

    // ------------------------------------------------------------------ manual + setup

    /**
     * @param  list<array{account_id:int,debit?:int,credit?:int,memo?:?string}>  $lines
     */
    public function postManual(User $actor, string $date, string $memo, array $lines): GlJournal
    {
        $this->ensureReady();

        return $this->poster->post('manual', $date, $memo, $lines, $actor);
    }

    public function reverse(GlJournal $journal, User $actor): GlJournal
    {
        return $this->poster->reverse($journal, $actor);
    }

    /** @param  array<string, int|null>  $maps */
    public function saveMaps(array $maps): void
    {
        $this->ensureReady();
        foreach (GlAccountMap::KEYS as $key) {
            $accountId = isset($maps[$key]) && $maps[$key] ? (int) $maps[$key] : null;
            if (! $accountId) {
                GlAccountMap::query()->where('key', $key)->delete();

                continue;
            }
            if (! GlAccount::query()->whereKey($accountId)->exists()) {
                throw AcademicRuleException::because('err.ais.badAccount');
            }
            GlAccountMap::query()->updateOrCreate(
                ['school_id' => app(TenantContext::class)->id(), 'key' => $key],
                ['account_id' => $accountId]
            );
        }
    }

    /**
     * Opening balances: one balanced opening journal. Debit assets / credit liabilities+equity typically.
     *
     * @param  list<array{account_id:int,debit?:int,credit?:int}>  $lines
     */
    public function postOpening(User $actor, string $date, array $lines): GlJournal
    {
        $this->ensureReady();
        if (GlJournal::query()->where('source', 'opening')->where('status', 'posted')->exists()) {
            throw AcademicRuleException::because('err.ais.openingExists');
        }

        return $this->poster->post('opening', $date, 'Opening balances', $lines, $actor);
    }

    public function lockPeriod(string $yearMonth, User $actor): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            throw AcademicRuleException::because('err.ais.badPeriod');
        }
        GlPeriodLock::query()->firstOrCreate(
            ['school_id' => app(TenantContext::class)->id(), 'year_month' => $yearMonth],
            ['locked_by' => $actor->id, 'locked_at' => now()]
        );
    }

    public function unlockPeriod(string $yearMonth): void
    {
        GlPeriodLock::query()->where('year_month', $yearMonth)->delete();
    }

    // ------------------------------------------------------------------ reports

    /** @return array{from:string,to:string} */
    public function period(?string $from, ?string $to): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return ['from' => $start->toDateString(), 'to' => $end->toDateString()];
    }

    public function overview(string $from, string $to): array
    {
        $income = $this->typeTotal('income', $from, $to, 'credit') - $this->typeTotal('income', $from, $to, 'debit');
        $expense = $this->typeTotal('expense', $from, $to, 'debit') - $this->typeTotal('expense', $from, $to, 'credit');
        $cash = $this->balanceAsOf($this->coa->mapped('cash')?->id, $to);
        $bank = $this->balanceAsOf($this->coa->mapped('bank')?->id, $to);
        $ar = $this->balanceAsOf($this->coa->mapped('fees_receivable')?->id, $to);
        $ap = $this->balanceAsOf($this->coa->mapped('ap')?->id, $to);

        return [
            'income' => $income,
            'expenses' => $expense,
            'surplus' => $income - $expense,
            'cash' => $cash,
            'bank' => $bank,
            'cash_bank' => $cash + $bank,
            'ar' => $ar,
            'ap' => $ap,
            'setup_needed' => $this->setupNeeded(),
        ];
    }

    /** @return list<array{account_id:int,code:string,name:string,type:string,debit:int,credit:int}> */
    public function trialBalance(string $asOf): array
    {
        $rows = GlLine::query()
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_lines.journal_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'gl_lines.account_id')
            ->where('gl_journals.status', 'posted')
            ->whereDate('gl_journals.date', '<=', $asOf)
            ->groupBy('gl_accounts.id', 'gl_accounts.code', 'gl_accounts.name', 'gl_accounts.type', 'gl_accounts.sort_order')
            ->orderBy('gl_accounts.sort_order')->orderBy('gl_accounts.code')
            ->select(
                'gl_accounts.id as account_id',
                'gl_accounts.code',
                'gl_accounts.name',
                'gl_accounts.type',
                DB::raw('SUM(gl_lines.debit) as debit'),
                DB::raw('SUM(gl_lines.credit) as credit'),
            )
            ->get();

        return $rows->map(function ($r) {
            $debit = (int) $r->debit;
            $credit = (int) $r->credit;
            $netDebit = max(0, $debit - $credit);
            $netCredit = max(0, $credit - $debit);

            return [
                'account_id' => (int) $r->account_id,
                'code' => $r->code,
                'name' => $r->name,
                'type' => $r->type,
                'debit' => $netDebit,
                'credit' => $netCredit,
            ];
        })->filter(fn ($r) => $r['debit'] > 0 || $r['credit'] > 0)->values()->all();
    }

    /** @return list<array{code:string,name:string,amount:int}> */
    public function incomeStatement(string $from, string $to): array
    {
        $rows = [];
        foreach (['income', 'expense'] as $type) {
            foreach (GlAccount::query()->where('type', $type)->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get() as $account) {
                $amount = $type === 'income'
                    ? $this->movement($account->id, $from, $to, 'credit') - $this->movement($account->id, $from, $to, 'debit')
                    : $this->movement($account->id, $from, $to, 'debit') - $this->movement($account->id, $from, $to, 'credit');
                if ($amount !== 0) {
                    $rows[] = ['code' => $account->code, 'name' => $account->name, 'type' => $type, 'amount' => $amount];
                }
            }
        }

        return $rows;
    }

    /** @return array{assets:list,liabilities:list,equity:list,surplus:int} */
    public function balanceSheet(string $asOf): array
    {
        $assets = $liabilities = $equity = [];
        foreach (GlAccount::query()->whereIn('type', ['asset', 'liability', 'equity'])->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get() as $account) {
            $bal = $this->balanceAsOf($account->id, $asOf);
            if ($bal === 0) {
                continue;
            }
            $row = ['code' => $account->code, 'name' => $account->name, 'amount' => $bal];
            match ($account->type) {
                'asset' => $assets[] = $row,
                'liability' => $liabilities[] = $row,
                default => $equity[] = $row,
            };
        }

        $income = $this->typeTotal('income', '1970-01-01', $asOf, 'credit') - $this->typeTotal('income', '1970-01-01', $asOf, 'debit');
        $expense = $this->typeTotal('expense', '1970-01-01', $asOf, 'debit') - $this->typeTotal('expense', '1970-01-01', $asOf, 'credit');
        $surplus = $income - $expense;
        if ($surplus !== 0) {
            $equity[] = ['code' => '3999', 'name' => 'Current period surplus', 'amount' => $surplus];
        }

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'surplus' => $surplus];
    }

    /** @return list<array{date:string,number:string,memo:?string,account:string,debit:int,credit:int,balance:int}> */
    public function cashBook(string $from, string $to): array
    {
        $accountIds = array_values(array_filter([
            $this->coa->mapped('cash')?->id,
            $this->coa->mapped('bank')?->id,
        ]));
        if ($accountIds === []) {
            return [];
        }

        $opening = 0;
        foreach ($accountIds as $id) {
            $opening += $this->balanceAsOf($id, Carbon::parse($from)->subDay()->toDateString());
        }

        $lines = GlLine::query()
            ->with(['journal', 'account:id,code,name'])
            ->whereIn('account_id', $accountIds)
            ->whereHas('journal', fn ($q) => $q->where('status', 'posted')->whereBetween('date', [$from, $to]))
            ->get()
            ->sortBy(fn (GlLine $l) => [$l->journal->date?->toDateString(), $l->journal_id, $l->id])
            ->values();

        $balance = $opening;
        $rows = [];
        foreach ($lines as $line) {
            $balance += (int) $line->debit - (int) $line->credit;
            $rows[] = [
                'date' => $line->journal->date?->toDateString(),
                'number' => $line->journal->number,
                'memo' => $line->memo ?: $line->journal->memo,
                'account' => $line->account?->code.' '.$line->account?->name,
                'debit' => (int) $line->debit,
                'credit' => (int) $line->credit,
                'balance' => $balance,
            ];
        }

        return $rows;
    }

    /** @return list<array{date:string,number:string,memo:?string,debit:int,credit:int,balance:int}> */
    public function ledger(int $accountId, string $from, string $to): array
    {
        $opening = $this->balanceAsOf($accountId, Carbon::parse($from)->subDay()->toDateString());
        $lines = GlLine::query()
            ->with('journal')
            ->where('account_id', $accountId)
            ->whereHas('journal', fn ($q) => $q->where('status', 'posted')->whereBetween('date', [$from, $to]))
            ->get()
            ->sortBy(fn (GlLine $l) => [$l->journal->date?->toDateString(), $l->journal_id, $l->id])
            ->values();

        $balance = $opening;
        $rows = [];
        foreach ($lines as $line) {
            $balance += (int) $line->debit - (int) $line->credit;
            // For credit-normal accounts (liability/equity/income) the signed balance is still debit-credit;
            // UI shows the running figure as stored.
            $rows[] = [
                'date' => $line->journal->date?->toDateString(),
                'number' => $line->journal->number,
                'memo' => $line->memo ?: $line->journal->memo,
                'debit' => (int) $line->debit,
                'credit' => (int) $line->credit,
                'balance' => $balance,
            ];
        }

        return $rows;
    }

    private function typeTotal(string $type, string $from, string $to, string $side): int
    {
        return (int) GlLine::query()
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_lines.journal_id')
            ->join('gl_accounts', 'gl_accounts.id', '=', 'gl_lines.account_id')
            ->where('gl_journals.status', 'posted')
            ->where('gl_accounts.type', $type)
            ->whereBetween('gl_journals.date', [$from, $to])
            ->sum('gl_lines.'.$side);
    }

    private function movement(int $accountId, string $from, string $to, string $side): int
    {
        return (int) GlLine::query()
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_lines.journal_id')
            ->where('gl_journals.status', 'posted')
            ->where('gl_lines.account_id', $accountId)
            ->whereBetween('gl_journals.date', [$from, $to])
            ->sum('gl_lines.'.$side);
    }

    /** Debit-normal balance (assets/expenses positive when debit-heavy). */
    private function balanceAsOf(?int $accountId, string $asOf): int
    {
        if (! $accountId) {
            return 0;
        }

        $account = GlAccount::query()->find($accountId);
        if (! $account) {
            return 0;
        }

        $debit = (int) GlLine::query()
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_lines.journal_id')
            ->where('gl_journals.status', 'posted')
            ->where('gl_lines.account_id', $accountId)
            ->whereDate('gl_journals.date', '<=', $asOf)
            ->sum('gl_lines.debit');
        $credit = (int) GlLine::query()
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_lines.journal_id')
            ->where('gl_journals.status', 'posted')
            ->where('gl_lines.account_id', $accountId)
            ->whereDate('gl_journals.date', '<=', $asOf)
            ->sum('gl_lines.credit');

        return in_array($account->type, ['asset', 'expense'], true)
            ? $debit - $credit
            : $credit - $debit;
    }
}
