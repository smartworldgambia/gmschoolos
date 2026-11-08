<?php

namespace App\Services\Billing;

use App\Models\BillingEvent;
use App\Models\SchoolTypePrice;
use App\Models\User;
use App\Support\SchoolTypes;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Platform-controlled annual pricing: exactly one current price per school type, with append-only history.
 * A price change never touches already-paid periods (payments snapshot their own amount).
 */
class PricingService
{
    /** The price in force for a school type on a given day (null when none is configured). */
    public function current(string $type, ?CarbonInterface $at = null): ?SchoolTypePrice
    {
        $day = ($at ?? now())->toDateString();

        return SchoolTypePrice::query()
            ->where('school_type', SchoolTypes::normalise($type))
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $day)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /** @return array<string, SchoolTypePrice|null> current price for every school type */
    public function currentForAll(?CarbonInterface $at = null): array
    {
        $prices = [];
        foreach (SchoolTypes::ALL as $type) {
            $prices[$type] = $this->current($type, $at);
        }

        return $prices;
    }

    /** Full history for a school type, newest first, each row labelled current / scheduled / superseded. */
    public function history(?string $type = null): array
    {
        $rows = SchoolTypePrice::query()
            ->when($type, fn ($q) => $q->where('school_type', $type))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $currentIds = collect(SchoolTypes::ALL)
            ->map(fn (string $t) => $this->current($t)?->id)
            ->filter()
            ->all();

        return $rows->map(function (SchoolTypePrice $row) use ($currentIds) {
            $state = in_array($row->id, $currentIds, true)
                ? 'current'
                : ($row->effective_from->isFuture() ? 'scheduled' : 'superseded');

            return [
                'id' => $row->id,
                'school_type' => $row->school_type,
                'amount' => $row->amount,
                'currency' => $row->currency,
                'tax_rate' => $row->tax_rate,
                'effective_from' => $row->effective_from->toDateString(),
                'status' => $row->status === 'active' ? $state : 'inactive',
                'description' => $row->description,
                'created_by' => $row->creator?->name,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        })->all();
    }

    public function set(
        string $type,
        int $amount,
        string $currency,
        CarbonInterface|string|null $effectiveFrom = null,
        ?float $taxRate = null,
        ?string $description = null,
        ?User $actor = null,
    ): SchoolTypePrice {
        if (! SchoolTypes::isValid($type)) {
            throw new InvalidArgumentException("Unknown school type [{$type}].");
        }
        if ($amount < 1) {
            throw new InvalidArgumentException('The annual amount must be greater than zero.');
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code (e.g. GMD).');
        }
        if ($taxRate !== null && ($taxRate < 0 || $taxRate > 100)) {
            throw new InvalidArgumentException('Tax rate must be between 0 and 100.');
        }

        $effective = $effectiveFrom ? Carbon::parse($effectiveFrom)->startOfDay() : now()->startOfDay();

        return DB::transaction(function () use ($type, $amount, $currency, $effective, $taxRate, $description, $actor) {
            $before = $this->current($type);

            $price = SchoolTypePrice::query()->create([
                'school_type' => $type,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'tax_rate' => $taxRate,
                'effective_from' => $effective->toDateString(),
                'status' => 'active',
                'description' => $description,
                'created_by' => $actor?->id,
            ]);

            // Platform-level audit entry (no school).
            logger()->info('billing.price_changed', [
                'school_type' => $type,
                'from' => $before?->amount,
                'to' => $amount,
                'currency' => $currency,
                'effective_from' => $effective->toDateString(),
                'actor_id' => $actor?->id,
            ]);

            return $price;
        });
    }
}