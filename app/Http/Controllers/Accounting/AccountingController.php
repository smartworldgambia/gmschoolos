<?php

namespace App\Http\Controllers\Accounting;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\GlPeriodLock;
use App\Services\Accounting\AccountingAccess;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ChartOfAccounts;
use App\Services\Ops\Ops;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    use SharesSchoolShell;

    public function __construct(
        private readonly AccountingService $ais,
        private readonly ChartOfAccounts $coa,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.view'), 403);
        $this->ais->ensureReady();

        $period = $this->ais->period($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);

        return Inertia::render('Accounting/Index', array_merge($this->shell(), [
            'current' => 'overview',
            'period' => $period,
            'stats' => $this->ais->overview($period['from'], $period['to']),
            'series' => $this->ais->overviewSeries($period['from'], $period['to']),
            'recent' => $this->ais->recentJournals(8),
        ]));
    }

    public function journals(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.view'), 403);
        $this->ais->ensureReady();

        $source = $request->string('source')->toString();
        $journals = GlJournal::query()
            ->with(['lines.account:id,code,name', 'poster:id,name'])
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->latest('date')->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (GlJournal $j) => [
                'id' => $j->id,
                'number' => $j->number,
                'date' => $j->date?->toDateString(),
                'memo' => $j->memo,
                'status' => $j->status,
                'source' => $j->source,
                'debit' => $j->lines->sum('debit'),
                'credit' => $j->lines->sum('credit'),
                'poster' => $j->poster?->name,
                'lines' => $j->lines->map(fn ($l) => [
                    'account' => $l->account?->code.' '.$l->account?->name,
                    'debit' => (int) $l->debit,
                    'credit' => (int) $l->credit,
                    'memo' => $l->memo,
                ]),
            ]);

        return Inertia::render('Accounting/Journals', array_merge($this->shell(), [
            'current' => 'journal',
            'journals' => $journals,
            'filters' => ['source' => $source],
            'accounts' => $this->accountOptions(),
            'canPost' => (bool) $request->user()?->can('accounting.post'),
        ]));
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.post'), 403);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:190'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:gl_accounts,id'],
            'lines.*.debit' => ['nullable', 'integer', 'min:0'],
            'lines.*.credit' => ['nullable', 'integer', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $this->ais->postManual($request->user(), $data['date'], $data['memo'] ?? 'Manual journal', $data['lines']);
        } catch (AcademicRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ok.ais.journalPosted');
    }

    public function reverseJournal(Request $request, GlJournal $journal): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.post'), 403);

        try {
            $this->ais->reverse($journal, $request->user());
        } catch (AcademicRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ok.ais.journalReversed');
    }

    public function ledger(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.view'), 403);
        $this->ais->ensureReady();

        $period = $this->ais->period($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);
        $accountId = (int) $request->input('account_id');
        $account = $accountId ? GlAccount::query()->find($accountId) : null;

        return Inertia::render('Accounting/Ledger', array_merge($this->shell(), [
            'current' => 'ledger',
            'period' => $period,
            'accounts' => $this->accountOptions(),
            'accountId' => $account?->id,
            'rows' => $account ? $this->ais->ledger((int) $account->id, $period['from'], $period['to']) : [],
        ]));
    }

    public function trialBalance(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('accounting.view'), 403);
        $this->ais->ensureReady();

        $asOf = $request->string('as_of')->toString() ?: now()->toDateString();
        $rows = $this->ais->trialBalance($asOf);

        if ($request->boolean('export')) {
            abort_unless($request->user()?->can('reports.export'), 403);
            $slug = $request->user()->school?->slug ?? 'school';

            return Ops::csv($slug.'-trial-balance-'.$asOf.'.csv', ['Code', 'Name', 'Type', 'Debit', 'Credit'], array_map(
                fn ($r) => [$r['code'], $r['name'], $r['type'], $r['debit'], $r['credit']],
                $rows
            ));
        }

        return Inertia::render('Accounting/TrialBalance', array_merge($this->shell(), [
            'current' => 'trialBalance',
            'asOf' => $asOf,
            'rows' => $rows,
            'totals' => [
                'debit' => array_sum(array_column($rows, 'debit')),
                'credit' => array_sum(array_column($rows, 'credit')),
            ],
            'canExport' => (bool) $request->user()?->can('reports.export'),
        ]));
    }

    public function income(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('accounting.view'), 403);
        $this->ais->ensureReady();

        $period = $this->ais->period($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);
        $rows = $this->ais->incomeStatement($period['from'], $period['to']);
        $income = array_sum(array_map(fn ($r) => $r['type'] === 'income' ? $r['amount'] : 0, $rows));
        $expense = array_sum(array_map(fn ($r) => $r['type'] === 'expense' ? $r['amount'] : 0, $rows));

        if ($request->boolean('export')) {
            abort_unless($request->user()?->can('reports.export'), 403);
            $slug = $request->user()->school?->slug ?? 'school';

            return Ops::csv($slug.'-income-'.$period['from'].'-'.$period['to'].'.csv', ['Code', 'Name', 'Type', 'Amount'], array_map(
                fn ($r) => [$r['code'], $r['name'], $r['type'], $r['amount']],
                $rows
            ));
        }

        return Inertia::render('Accounting/Income', array_merge($this->shell(), [
            'current' => 'income',
            'period' => $period,
            'rows' => $rows,
            'totals' => ['income' => $income, 'expenses' => $expense, 'surplus' => $income - $expense],
            'canExport' => (bool) $request->user()?->can('reports.export'),
        ]));
    }

    public function balance(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.view'), 403);
        $this->ais->ensureReady();

        $asOf = $request->string('as_of')->toString() ?: now()->toDateString();

        return Inertia::render('Accounting/Balance', array_merge($this->shell(), [
            'current' => 'balance',
            'asOf' => $asOf,
            'sheet' => $this->ais->balanceSheet($asOf),
        ]));
    }

    public function cashbook(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.view'), 403);
        $this->ais->ensureReady();

        $period = $this->ais->period($request->string('from')->toString() ?: null, $request->string('to')->toString() ?: null);

        return Inertia::render('Accounting/Cashbook', array_merge($this->shell(), [
            'current' => 'cashbook',
            'period' => $period,
            'rows' => $this->ais->cashBook($period['from'], $period['to']),
        ]));
    }

    public function setup(Request $request): Response
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);
        $this->ais->ensureReady();

        return Inertia::render('Accounting/Setup', array_merge($this->shell(), [
            'current' => 'setup',
            'accounts' => GlAccount::query()->orderBy('sort_order')->orderBy('code')->get()->map(fn (GlAccount $a) => [
                'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type,
                'subtype' => $a->subtype, 'is_system' => $a->is_system, 'is_active' => $a->is_active,
                'has_lines' => $a->lines()->exists(),
            ]),
            'maps' => $this->coa->maps(),
            'mapKeys' => \App\Models\GlAccountMap::KEYS,
            'locks' => GlPeriodLock::query()->orderByDesc('year_month')->get()->map(fn (GlPeriodLock $l) => [
                'year_month' => $l->year_month, 'locked_at' => $l->locked_at?->toDateTimeString(),
            ]),
            'hasOpening' => GlJournal::query()->where('source', 'opening')->where('status', 'posted')->exists(),
        ]));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);
        $this->ais->ensureReady();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('gl_accounts', 'code')->where(fn ($q) => $q->where('school_id', $request->user()->school_id))],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'subtype' => ['nullable', 'string', 'max:32'],
        ]);

        GlAccount::query()->create($data + ['is_system' => false, 'is_active' => true, 'sort_order' => 500]);

        return back()->with('success', 'ok.ais.accountSaved');
    }

    public function updateAccount(Request $request, GlAccount $account): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $account->update($data);

        return back()->with('success', 'ok.ais.accountSaved');
    }

    public function destroyAccount(Request $request, GlAccount $account): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);
        if ($account->is_system || $account->lines()->exists()) {
            return back()->with('error', 'err.ais.accountInUse');
        }
        $account->delete();

        return back()->with('success', 'ok.ais.accountDeleted');
    }

    public function saveMaps(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);

        $data = $request->validate([
            'maps' => ['required', 'array'],
            'maps.*' => ['nullable', 'integer'],
        ]);

        try {
            $this->ais->saveMaps($data['maps']);
        } catch (AcademicRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ok.ais.mapsSaved');
    }

    public function storeOpening(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.debit' => ['nullable', 'integer', 'min:0'],
            'lines.*.credit' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->ais->postOpening($request->user(), $data['date'], $data['lines']);
        } catch (AcademicRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ok.ais.openingPosted');
    }

    public function lockPeriod(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);
        $data = $request->validate(['year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $this->ais->lockPeriod($data['year_month'], $request->user());
        } catch (AcademicRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ok.ais.periodLocked');
    }

    public function unlockPeriod(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('accounting.manage'), 403);
        $data = $request->validate(['year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/']]);
        $this->ais->unlockPeriod($data['year_month']);

        return back()->with('success', 'ok.ais.periodUnlocked');
    }

    private function shell(): array
    {
        $user = auth()->user();

        return array_merge($this->shellProps(), [
            'aisTabs' => AccountingAccess::tabs($user),
            'can' => [
                'view' => (bool) $user?->can('accounting.view'),
                'post' => (bool) $user?->can('accounting.post'),
                'manage' => (bool) $user?->can('accounting.manage'),
                'export' => (bool) $user?->can('reports.export'),
            ],
        ]);
    }

    /** @return list<array{id:int,code:string,name:string,type:string}> */
    private function accountOptions(): array
    {
        return GlAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (GlAccount $a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type])
            ->all();
    }
}
