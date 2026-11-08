<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolRegistrationReview;
use App\Models\SchoolSubscription;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform review of registrations that resembled a known institution (trial-abuse control).
 * Confirming a duplicate suspends the school and ends its trial; nothing is ever deleted.
 */
class RegistrationReviewController extends Controller
{
    use SharesPlatformShell;

    public function index(Request $request, TenantContext $tenant): Response
    {
        $status = $request->query('status', SchoolRegistrationReview::PENDING);
        $status = in_array($status, [SchoolRegistrationReview::PENDING, SchoolRegistrationReview::CLEARED, SchoolRegistrationReview::CONFIRMED_DUPLICATE, 'all'], true)
            ? $status
            : SchoolRegistrationReview::PENDING;

        $reviews = $tenant->runAsPlatform(function () use ($status) {
            $paginator = SchoolRegistrationReview::query()
                ->with(['school.subscription', 'reviewer:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')
                ->paginate(20)
                ->withQueryString();

            $matchedIds = $paginator->getCollection()->flatMap(fn ($r) => (array) $r->matched_school_ids)->unique()->all();
            $matched = School::query()->whereIn('id', $matchedIds)->get(['id', 'name', 'slug', 'region', 'status', 'suspended_at'])->keyBy('id');

            return $paginator->through(fn (SchoolRegistrationReview $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'reasons' => $r->reasons ?? [],
                'notes' => $r->notes,
                'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                'reviewer' => $r->reviewer?->name,
                'created_at' => $r->created_at?->toDateTimeString(),
                'school' => $r->school ? [
                    'id' => $r->school->id,
                    'name' => $r->school->name,
                    'slug' => $r->school->slug,
                    'category' => $r->school->category,
                    'region' => $r->school->region,
                    'district' => $r->school->district,
                    'registration_number' => $r->school->registration_number,
                    'email' => $r->school->email,
                    'admin_email' => $r->school->billing_email,
                    'phone' => $r->school->phone,
                    'suspended' => $r->school->suspended_at !== null,
                    'subscription_status' => $r->school->subscription?->status,
                    'trial_ends_at' => $r->school->subscription?->trial_ends_at?->toDateString(),
                ] : null,
                'matched' => collect((array) $r->matched_school_ids)
                    ->map(fn ($id) => $matched->get($id))
                    ->filter()
                    ->map(fn (School $m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'slug' => $m->slug,
                        'region' => $m->region,
                        'suspended' => $m->suspended_at !== null,
                    ])->values(),
            ]);
        });

        $pending = $tenant->runAsPlatform(fn () => SchoolRegistrationReview::query()->where('status', SchoolRegistrationReview::PENDING)->count());

        return Inertia::render('Platform/Registrations/Index', array_merge($this->platformShellProps(), [
            'reviews' => $reviews,
            'status' => $status,
            'pending' => $pending,
        ]));
    }

    public function clear(Request $request, int $review, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $tenant->runAsPlatform(function () use ($request, $review, $data) {
            $row = SchoolRegistrationReview::query()->findOrFail($review);
            $this->assertPending($row);

            $row->update([
                'status' => SchoolRegistrationReview::CLEARED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->resolveAlert($row);
        });

        return back()->with('success', 'Registration cleared.');
    }

    public function confirm(Request $request, int $review, TenantContext $tenant, SubscriptionLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:1000']]);

        $tenant->runAsPlatform(function () use ($request, $review, $data, $lifecycle) {
            $row = SchoolRegistrationReview::query()->with('school.subscription')->findOrFail($review);
            $this->assertPending($row);

            $school = $row->school;
            $subscription = $school?->subscription;

            // Never act on a school that has paid: that needs a deliberate decision on the school page.
            if ($subscription && $subscription->status !== SchoolSubscription::TRIAL) {
                throw ValidationException::withMessages(['review' => 'This school already has a paid or processed subscription. Handle it from the school page.']);
            }

            $row->update([
                'status' => SchoolRegistrationReview::CONFIRMED_DUPLICATE,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'notes' => $data['notes'],
            ]);

            if ($school) {
                $school->update(['suspended_at' => now(), 'status' => 'inactive']);
            }

            if ($subscription) {
                $subscription->forceFill(['status' => SchoolSubscription::SUSPENDED])->save();
                $lifecycle->event($subscription, 'trial_revoked_duplicate', $request->user(), ['review_id' => $row->id]);
            }

            $this->resolveAlert($row);
        });

        return back()->with('success', 'Duplicate confirmed. The school was suspended and its trial ended; no records were deleted.');
    }

    private function assertPending(SchoolRegistrationReview $row): void
    {
        if ($row->status !== SchoolRegistrationReview::PENDING) {
            throw ValidationException::withMessages(['review' => 'This review has already been decided.']);
        }
    }

    private function resolveAlert(SchoolRegistrationReview $row): void
    {
        PlatformAlert::query()
            ->where('source', 'registration')
            ->where('school_id', $row->school_id)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }
}
