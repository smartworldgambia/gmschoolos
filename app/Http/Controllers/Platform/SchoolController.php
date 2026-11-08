<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\School;
use App\Models\BillingPayment;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\SchoolTypes;
use App\Models\UsageSnapshot;
use App\Services\Platform\QuotaService;
use App\Services\Platform\UsageRecorder;
use App\Services\School\SchoolDomainService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    use SharesPlatformShell;

    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        $schools = School::query()
            ->with(['subscription', 'primaryDomain', 'quota'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (School $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'status' => $s->suspended_at ? 'suspended' : $s->status,
                'region' => $s->region,
                'school_type' => $s->subscription?->school_type,
                'subscription_status' => $s->subscription ? app(SubscriptionLifecycle::class)->effectiveStatus($s->subscription) : null,
                'domain' => $s->primaryDomain?->host,
                'public_url' => app(SchoolDomainService::class)->urlsFor($s)['primary_url'],
                'billing_email' => $s->billing_email ?? $s->email,
                'created_at' => $s->created_at?->toDateString(),
            ]);

        return Inertia::render('Platform/Schools/Index', array_merge($this->platformShellProps(), [
            'schools' => $schools,
            'filters' => ['q' => $q],
            'types' => SchoolTypes::ALL,
        ]));
    }

    public function show(School $school, QuotaService $quotas, UsageRecorder $usage, SubscriptionLifecycle $lifecycle): Response
    {
        $school->load(['domains', 'subscription', 'quota']);

        $usage->snapshotSchool($school);
        $snapshots = UsageSnapshot::query()
            ->where('school_id', $school->id)
            ->orderByDesc('captured_on')
            ->limit(14)
            ->get()
            ->map(fn (UsageSnapshot $u) => [
                'captured_on' => $u->captured_on?->toDateString(),
                'students_count' => $u->students_count,
                'staff_count' => $u->staff_count,
                'users_count' => $u->users_count,
                'courses_count' => $u->courses_count,
                'request_count' => $u->request_count,
                'export_count' => $u->export_count,
                'import_count' => $u->import_count,
            ]);

        return Inertia::render('Platform/Schools/Show', array_merge($this->platformShellProps(), [
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'status' => $school->status,
                'suspended_at' => $school->suspended_at?->toDateTimeString(),
                'region' => $school->region,
                'email' => $school->email,
                'billing_email' => $school->billing_email,
                'phone' => $school->phone,
                'category' => $school->category,
                'public_url' => app(SchoolDomainService::class)->urlsFor($school)['primary_url'],
            ],
            'domains' => $school->domains->map(fn (Domain $d) => [
                'id' => $d->id,
                'host' => $d->host,
                'is_primary' => $d->is_primary,
                'is_custom' => $d->is_custom,
                'ssl_active' => $d->ssl_active,
            ]),
            'subscription' => $lifecycle->state($school),
            'types' => SchoolTypes::ALL,
            'payments' => BillingPayment::query()->where('school_id', $school->id)->latest('id')->limit(25)->get()
                ->map(fn (BillingPayment $p) => [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'receipt_number' => $p->receipt_number,
                    'method' => $p->method,
                    'status' => $p->status,
                    'total_label' => \App\Support\Money::formatCurrency($p->total_amount, $p->currency),
                    'paid_at' => $p->paid_at?->toDateString(),
                    'period_end' => $p->period_end?->toDateString(),
                ]),
            'quota' => $quotas->usageSummary($school),
            'quotaLimits' => [
                'max_students' => $school->quota?->max_students,
                'max_staff' => $school->quota?->max_staff,
                'max_storage_mb' => $school->quota?->max_storage_mb,
                'max_exports_per_day' => $school->quota?->max_exports_per_day,
                'max_api_requests_per_hour' => $school->quota?->max_api_requests_per_hour,
                'is_premium_isolated' => (bool) $school->quota?->is_premium_isolated,
            ],
            'usageSnapshots' => $snapshots,
            'updateUrls' => [
                'school' => route('platform.schools.update', $school),
                'payment' => route('platform.schools.payments.store', $school),
                'schoolType' => route('platform.schools.type.update', $school),
                'quota' => route('platform.schools.quota', $school),
                'domain' => route('platform.schools.domains.store', $school),
                'suspend' => route('platform.schools.suspend', $school),
                'unsuspend' => route('platform.schools.unsuspend', $school),
            ],
        ]));
    }

    public function store(Request $request, QuotaService $quotas, SubscriptionLifecycle $lifecycle): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:schools,slug'],
            'email' => ['nullable', 'email', 'max:150'],
            'billing_email' => ['nullable', 'email', 'max:150'],
            'region' => ['nullable', 'string', 'max:100'],
            'school_type' => ['required', \Illuminate\Validation\Rule::in(SchoolTypes::ALL)],
            'host' => ['nullable', 'string', 'max:191'],
        ]);

        $domains = app(SchoolDomainService::class);
        $slug = $domains->normalizeSlug($validated['slug'] ?? Str::slug($validated['name']));
        $slugCheck = $domains->slugStatus($slug);
        if (! $slugCheck['available']) {
            return back()
                ->withErrors(['slug' => $domains->slugErrorMessage($slugCheck['status'])])
                ->withInput();
        }

        $school = School::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'email' => $validated['email'] ?? null,
            'billing_email' => $validated['billing_email'] ?? $validated['email'] ?? null,
            'region' => $validated['region'] ?? null,
            'country' => 'GM',
            'category' => $validated['school_type'],
            'status' => 'active',
            'website_published' => false,
        ]);

        // Every school gets its dedicated platform URL; an operator-supplied host is added as a trusted custom domain.
        $domains->assignPlatformDomain($school);
        if (! empty($validated['host'])) {
            try {
                $custom = $domains->connectCustomDomain($school, $validated['host'], true, trusted: true);
                $domains->makePrimary($custom);
            } catch (\InvalidArgumentException) {
                // School is still reachable on its platform URL; operator can retry from the school page.
            }
        }

        // Every provisioned school starts the automatic 30-day trial (no payment required).
        $lifecycle->startTrial($school, null, $validated['school_type'], $request->user());

        $quotas->forSchool($school->fresh('subscription'));

        return redirect()->route('platform.schools.show', $school)
            ->with('success', 'School provisioned.');
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'status' => ['required', 'in:active,pending,inactive'],
            'billing_email' => ['nullable', 'email', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'region' => ['nullable', 'string', 'max:100'],
        ]);

        $school->update($validated);

        return back()->with('success', 'School updated.');
    }

    public function updateQuota(Request $request, School $school, QuotaService $quotas): RedirectResponse
    {
        $validated = $request->validate([
            'max_students' => ['nullable', 'integer', 'min:1'],
            'max_staff' => ['nullable', 'integer', 'min:1'],
            'max_storage_mb' => ['nullable', 'integer', 'min:1'],
            'max_exports_per_day' => ['nullable', 'integer', 'min:1'],
            'max_api_requests_per_hour' => ['nullable', 'integer', 'min:1'],
            'is_premium_isolated' => ['sometimes', 'boolean'],
        ]);

        $quota = $quotas->forSchool($school);
        $quota->fill([
            ...$validated,
            'is_premium_isolated' => (bool) ($validated['is_premium_isolated'] ?? $quota->is_premium_isolated),
        ])->save();

        return back()->with('success', 'Quota updated.');
    }

    public function storeDomain(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:191'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_custom' => ['sometimes', 'boolean'],
        ]);

        $domains = app(SchoolDomainService::class);

        try {
            // Operators are trusted: the claim is created pre-verified (no DNS proof, no plan/limit gate).
            $domain = $domains->connectCustomDomain($school, $validated['host'], false, trusted: true);

            if (! empty($validated['is_primary'])) {
                $domains->makePrimary($domain);
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['host' => $e->getMessage()]);
        }

        return back()->with('success', 'Domain added.');
    }

    public function suspend(School $school): RedirectResponse
    {
        $school->update([
            'suspended_at' => now(),
            'status' => 'inactive',
        ]);

        return back()->with('success', 'School suspended.');
    }

    public function unsuspend(School $school): RedirectResponse
    {
        $school->update([
            'suspended_at' => null,
            'status' => 'active',
        ]);

        return back()->with('success', 'School unsuspended.');
    }
}
