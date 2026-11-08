<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\Money;
use App\Support\SchoolTypes;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform finance dashboard for annual subscriptions + authorised manual payment verification.
 * (Gateway workflows - bank evidence upload, Wave, Stripe - arrive in Phase 3.)
 */
class BillingController extends Controller
{
    use SharesPlatformShell;

    public function index(Request $request, SubscriptionLifecycle $lifecycle): Response
    {
        $filters = $request->only(['q', 'status', 'type']);
        $now = now();

        $rows = $this->filtered($filters)
            ->orderBy('ends_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SchoolSubscription $s) => $this->row($s, $lifecycle));

        return Inertia::render('Platform/Billing/Index', array_merge($this->platformShellProps(), [
            'stats' => $this->stats($now),
            'schools' => $rows,
            'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '', 'type' => $filters['type'] ?? ''],
            'types' => SchoolTypes::ALL,
            'upcomingTrials' => $this->upcoming(SchoolSubscription::TRIAL, 14, $lifecycle),
            'upcomingRenewals' => $this->upcoming(SchoolSubscription::ACTIVE, 60, $lifecycle),
            'recentPayments' => BillingPayment::query()->with('school:id,name')->latest('id')->limit(15)->get()
                ->map(fn (BillingPayment $p) => $this->paymentRow($p)),
            'exportUrl' => route('platform.billing.export', array_filter($filters)),
        ]));
    }

    public function export(Request $request, SubscriptionLifecycle $lifecycle): StreamedResponse
    {
        $filters = $request->only(['q', 'status', 'type']);

        // The stream callback runs after the request pipeline has finished, so platform mode must be re-entered explicitly.
        return response()->streamDownload(function () use ($filters, $lifecycle) {
            app(TenantContext::class)->runAsPlatform(function () use ($filters, $lifecycle) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['school', 'school_type', 'status', 'payment_status', 'currency', 'annual_amount', 'ends_at', 'days_remaining']);

                $this->filtered($filters)->orderBy('ends_at')->each(function (SchoolSubscription $s) use ($out, $lifecycle) {
                    $r = $this->row($s, $lifecycle);
                    fputcsv($out, [
                        // neutralise spreadsheet formula injection in school-entered text
                        $this->csvSafe($r['school']), $r['school_type'], $r['status'], $r['payment_status'],
                        $r['currency'], $r['annual_total'], $r['ends_at'], $r['days_remaining'],
                    ]);
                });

                fclose($out);
            });
        }, 'subscriptions-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function recordPayment(Request $request, School $school, SubscriptionLifecycle $lifecycle): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'wave'])],
            'amount' => ['nullable', 'integer', 'min:1'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // If the school already started a payment with this method (cash reference / bank transfer), settle THAT one
        // instead of creating a duplicate; the full-amount rule is unchanged.
        $open = BillingPayment::query()->where('school_id', $school->id)
            ->where('method', $validated['method'])->whereIn('status', BillingPayment::OPEN)->latest('id')->first();
        if ($open) {
            $validated['payment_id'] = $open->id;
        }

        try {
            $lifecycle->recordVerifiedPayment($school, $validated, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Verified payment recorded. The subscription is active.');
    }

    public function updateType(Request $request, School $school, SubscriptionLifecycle $lifecycle): RedirectResponse
    {
        $validated = $request->validate(['school_type' => ['required', Rule::in(SchoolTypes::ALL)]]);

        try {
            $lifecycle->changeSchoolType($school, $validated['school_type'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['school_type' => $e->getMessage()]);
        }

        return back()->with('success', 'School type updated (audited).');
    }

    // ------------------------------------------------------------------ helpers

    protected function filtered(array $filters): Builder
    {
        $now = now();

        return SchoolSubscription::query()
            ->with('school:id,name,slug,suspended_at')
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->whereHas('school', fn (Builder $s) => $s
                ->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('school_type', $type))
            ->when($filters['status'] ?? null, function (Builder $q, string $status) use ($now) {
                match ($status) {
                    'trial' => $q->where('status', 'trial')->where('ends_at', '>', $now),
                    'active' => $q->where('status', 'active')->where('ends_at', '>', $now),
                    'expired' => $q->where(fn (Builder $w) => $w->where('status', 'expired')
                        ->orWhere(fn (Builder $x) => $x->whereIn('status', ['trial', 'active'])->where('ends_at', '<=', $now))),
                    'suspended' => $q->where(fn (Builder $w) => $w->where('status', 'suspended')
                        ->orWhereHas('school', fn (Builder $s) => $s->whereNotNull('suspended_at'))),
                    default => null,
                };
            });
    }

    /** @return array<string, mixed> */
    protected function row(SchoolSubscription $s, SubscriptionLifecycle $lifecycle): array
    {
        $quote = $lifecycle->quote($s);
        $status = $s->school?->suspended_at ? 'suspended' : $lifecycle->effectiveStatus($s);

        return [
            'subscription_id' => $s->id,
            'school_id' => $s->school_id,
            'school' => $s->school?->name,
            'school_url' => $s->school ? route('platform.schools.show', $s->school) : null,
            'school_type' => $s->school_type,
            'status' => $status,
            'payment_status' => $status === 'trial' ? 'not_required_during_trial' : ($s->payment_status === 'paid' && $status === 'active' ? 'paid' : $s->payment_status),
            'currency' => $quote['currency'],
            'annual_total' => $quote['total'],
            'annual_label' => $quote['configured'] ? Money::formatCurrency($quote['total'], $quote['currency']) : null,
            'ends_at' => $s->ends_at?->toDateString(),
            'days_remaining' => $lifecycle->daysRemaining($s),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function upcoming(string $status, int $withinDays, SubscriptionLifecycle $lifecycle): array
    {
        return SchoolSubscription::query()->with('school:id,name,slug')
            ->where('status', $status)
            ->whereBetween('ends_at', [now(), now()->addDays($withinDays)->endOfDay()])
            ->orderBy('ends_at')->limit(10)->get()
            ->map(fn (SchoolSubscription $s) => $this->row($s, $lifecycle))->all();
    }

    /** @return array<string, mixed> */
    protected function paymentRow(BillingPayment $p): array
    {
        return [
            'id' => $p->id,
            'school' => $p->school?->name,
            'reference' => $p->reference,
            'receipt_number' => $p->receipt_number,
            'method' => $p->method,
            'status' => $p->status,
            'currency' => $p->currency,
            'total' => $p->total_amount,
            'total_label' => Money::formatCurrency($p->total_amount, $p->currency),
            'paid_at' => $p->paid_at?->toDateString(),
            'period_end' => $p->period_end?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    protected function stats(\Illuminate\Support\Carbon $now): array
    {
        $count = fn (callable $fn) => (int) $fn(SchoolSubscription::query())->count();
        $year = [$now->copy()->startOfYear(), $now->copy()->endOfYear()];

        $verified = BillingPayment::query()->where('status', 'verified')->whereBetween('paid_at', $year);

        return [
            'schools' => School::query()->count(),
            'trial' => $count(fn ($q) => $q->where('status', 'trial')->where('ends_at', '>', $now)),
            'active' => $count(fn ($q) => $q->where('status', 'active')->where('ends_at', '>', $now)),
            'expired' => $count(fn ($q) => $q->where(fn ($w) => $w->where('status', 'expired')
                ->orWhere(fn ($x) => $x->whereIn('status', ['trial', 'active'])->where('ends_at', '<=', $now)))),
            'suspended' => School::query()->whereNotNull('suspended_at')->count()
                + $count(fn ($q) => $q->where('status', 'suspended')),
            'pending_payments' => BillingPayment::query()->whereIn('status', ['pending', 'pending_verification'])->count(),
            'failed_payments' => BillingPayment::query()->where('status', 'failed')->count(),
            'revenue_year' => (clone $verified)->select('currency', DB::raw('SUM(total_amount) as total'))->groupBy('currency')->get()
                ->map(fn ($r) => ['currency' => $r->currency, 'total' => (int) $r->total, 'label' => Money::formatCurrency($r->total, $r->currency)])->all(),
            'revenue_by_type' => (clone $verified)->select('school_type', 'currency', DB::raw('SUM(total_amount) as total'))->groupBy('school_type', 'currency')->get()
                ->map(fn ($r) => ['key' => $r->school_type ?? 'other', 'label' => Money::formatCurrency($r->total, $r->currency)])->all(),
            'revenue_by_method' => (clone $verified)->select('method', 'currency', DB::raw('SUM(total_amount) as total'))->groupBy('method', 'currency')->get()
                ->map(fn ($r) => ['key' => $r->method, 'label' => Money::formatCurrency($r->total, $r->currency)])->all(),
            'year' => $now->year,
        ];
    }

    protected function csvSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}