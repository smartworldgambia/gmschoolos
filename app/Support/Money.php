<?php

namespace App\Support;

class Money
{
    public static function format(int|float|null $amount): string
    {
        $symbol = config('schoolos.currency.symbol', 'D');

        return $symbol.' '.number_format((float) $amount, 0);
    }

    /** Explicit-currency formatting for billing (never silently assumes or converts a currency). */
    public static function formatCurrency(int|float|null $amount, ?string $currency = null): string
    {
        $currency = $currency ?: config('schoolos.billing.currency', 'GMD');

        return $currency.' '.number_format((float) $amount, 0);
    }
}