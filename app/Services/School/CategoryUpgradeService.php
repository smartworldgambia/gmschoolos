<?php

namespace App\Services\School;

use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolCategoryUpgradeRequest;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\SchoolCategoryCatalog;
use App\Support\SchoolTypes;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryUpgradeService
{
    public function __construct(
        protected TenantContext $tenant,
        protected SubscriptionLifecycle $lifecycle,
        protected SchoolLandingProvisioner $landing,
    ) {}

    /**
     * @param  list<string>  $requestedCategories
     */
    public function request(School $school, User $actor, array $requestedCategories, ?string $reason = null): SchoolCategoryUpgradeRequest
    {
        $upgradeable = SchoolCategoryCatalog::upgradeableCategories($school);

        if ($upgradeable === []) {
            throw ValidationException::withMessages([
                'categories' => 'This school cannot request category upgrades.',
            ]);
        }

        $pending = SchoolCategoryUpgradeRequest::query()
            ->where('school_id', $school->id)
            ->where('status', SchoolCategoryUpgradeRequest::PENDING)
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'categories' => 'A category upgrade request is already pending review.',
            ]);
        }

        $requested = array_values(array_unique(array_filter(array_map(
            static fn ($v) => SchoolTypes::normalise((string) $v),
            $requestedCategories
        ), static fn (string $v) => in_array($v, $upgradeable, true))));

        if ($requested === []) {
            throw ValidationException::withMessages([
                'categories' => 'Select at least one category to add.',
            ]);
        }

        $current = SchoolCategoryCatalog::enabledCategories($school);
        $merged = array_values(array_unique([...$current, ...$requested]));
        $target = SchoolCategoryCatalog::billingTypeForEnabled($merged);

        $row = SchoolCategoryUpgradeRequest::query()->create([
            'school_id' => $school->id,
            'current_categories' => $current,
            'requested_categories' => $requested,
            'target_billing_type' => $target,
            'reason' => $reason ? trim($reason) : null,
            'status' => SchoolCategoryUpgradeRequest::PENDING,
            'requested_by' => $actor->id,
        ]);

        $this->tenant->runAsPlatform(function () use ($school, $row, $requested, $target) {
            PlatformAlert::query()->create([
                'severity' => 'warning',
                'source' => 'billing',
                'title' => 'School category upgrade requested',
                'message' => sprintf(
                    '%s requested to add %s (billing → %s).',
                    $school->name,
                    implode(', ', $requested),
                    $target
                ),
                'school_id' => $school->id,
                'context' => [
                    'request_id' => $row->id,
                    'requested_categories' => $requested,
                    'target_billing_type' => $target,
                ],
            ]);
        });

        return $row;
    }

    public function approve(SchoolCategoryUpgradeRequest $request, User $actor, ?string $note = null): SchoolCategoryUpgradeRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => 'Only pending upgrade requests can be approved.',
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $note) {
            /** @var School $school */
            $school = School::query()->lockForUpdate()->findOrFail($request->school_id);
            $current = SchoolCategoryCatalog::enabledCategories($school);
            $requested = array_values(array_filter(
                (array) $request->requested_categories,
                static fn ($v) => in_array(SchoolTypes::normalise((string) $v), SchoolCategoryCatalog::EXCLUSIVE, true)
            ));
            $merged = array_values(array_unique([...$current, ...array_map(
                static fn ($v) => SchoolTypes::normalise((string) $v),
                $requested
            )]));
            $billingType = SchoolCategoryCatalog::billingTypeForEnabled($merged);

            $this->tenant->runForSchool($school, function () use ($school, $requested) {
                $this->landing->seedLevelsForCategories($school, $requested);
            });

            $this->lifecycle->changeSchoolType($school, $billingType, $actor, $merged);

            $request->forceFill([
                'status' => SchoolCategoryUpgradeRequest::APPROVED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $note ? trim($note) : null,
                'target_billing_type' => $billingType,
            ])->save();

            return $request->fresh(['school', 'requester', 'reviewer']);
        });
    }

    public function reject(SchoolCategoryUpgradeRequest $request, User $actor, ?string $note = null): SchoolCategoryUpgradeRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => 'Only pending upgrade requests can be rejected.',
            ]);
        }

        $request->forceFill([
            'status' => SchoolCategoryUpgradeRequest::REJECTED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_note' => $note ? trim($note) : null,
        ])->save();

        return $request->fresh(['school', 'requester', 'reviewer']);
    }

    public function cancel(SchoolCategoryUpgradeRequest $request, User $actor): SchoolCategoryUpgradeRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => 'Only pending upgrade requests can be cancelled.',
            ]);
        }

        if ((int) $request->requested_by !== (int) $actor->id && ! $actor->can('platform.subscriptions.manage')) {
            throw ValidationException::withMessages([
                'request' => 'You cannot cancel this request.',
            ]);
        }

        $request->forceFill([
            'status' => SchoolCategoryUpgradeRequest::CANCELLED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        return $request->fresh();
    }
}
