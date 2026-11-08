<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\SchoolSubscription;
use App\Services\Billing\PricingService;
use App\Support\Money;
use App\Support\SchoolTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Platform Administrator control of the annual price per school type (no tiers, no feature pricing).
 * Changes insert a new dated price; history is retained and paid periods are never altered.
 */
class PricingController extends Controller
{
    use SharesPlatformShell;

    public function index(PricingService $pricing): Response
    {
        $current = $pricing->currentForAll();

        $types = collect(SchoolTypes::ALL)->map(function (string $type) use ($current) {
            $price = $current[$type];

            return [
                'type' => $type,
                'configured' => (bool) $price,
                'amount' => $price?->amount,
                'currency' => $price?->currency ?? config('schoolos.billing.currency', 'GMD'),
                'tax_rate' => $price?->tax_rate,
                'total_label' => $price ? Money::formatCurrency($price->totalAmount(), $price->currency) : null,
                'effective_from' => $price?->effective_from?->toDateString(),
                'description' => $price?->description,
                'schools' => SchoolSubscription::withoutGlobalScopes()->where('school_type', $type)->count(),
            ];
        })->values();

        return Inertia::render('Platform/Pricing/Index', array_merge($this->platformShellProps(), [
            'types' => $types,
            'history' => $pricing->history(),
            'storeUrl' => route('platform.pricing.store'),
            'defaultCurrency' => config('schoolos.billing.currency', 'GMD'),
        ]));
    }

    public function store(Request $request, PricingService $pricing): RedirectResponse
    {
        $validated = $request->validate([
            'school_type' => ['required', Rule::in(SchoolTypes::ALL)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $pricing->set(
                $validated['school_type'],
                (int) $validated['amount'],
                strtoupper($validated['currency']),
                $validated['effective_from'] ?? null,
                isset($validated['tax_rate']) ? (float) $validated['tax_rate'] : null,
                $validated['description'] ?? null,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        app(\App\Services\Scale\CacheTuner::class)->forgetLandingPrices();

        return back()->with('success', 'Annual price saved. Already-paid periods are unchanged.');
    }
}