<?php

namespace App\Support;

/**
 * Minor-unit arithmetic for payment gateways. Gateways charge in MINOR units of a currency
 * (cents / bani); a few currencies (XOF, JPY, ...) have none. Conversions between currencies only ever
 * happen with an explicit, configured rate and always round UP to the next minor unit so the platform is
 * never short - and the converted amount is shown to the payer before they pay.
 */
class Currency
{
    /** ISO 4217 currencies without a minor unit (Stripe / Wave treat amounts as whole units). */
    public const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public static function normalise(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public static function decimals(string $code): int
    {
        return in_array(self::normalise($code), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /** Whole major units (e.g. 1000 GMD) to minor units of the SAME currency. */
    public static function wholeToMinor(int $whole, string $code): int
    {
        return $whole * (10 ** self::decimals($code));
    }

    /**
     * Convert a whole-unit amount in $from to minor units of $to.
     * Returns null when the currencies differ and no usable rate is configured (callers must refuse the payment).
     *
     * @param  float|null  $rate  units of $to per 1 unit of $from
     */
    public static function convertToMinor(int $whole, string $from, string $to, ?float $rate): ?int
    {
        $from = self::normalise($from);
        $to = self::normalise($to);

        if ($from === $to) {
            return self::wholeToMinor($whole, $to);
        }

        if ($rate === null || $rate <= 0) {
            return null;
        }

        return (int) ceil(round($whole * $rate * (10 ** self::decimals($to)), 6));
    }

    /** Minor units to the decimal string a gateway expects ("1000", "14.50"). */
    public static function minorToDecimalString(int $minor, string $code): string
    {
        $decimals = self::decimals($code);

        return $decimals === 0
            ? (string) $minor
            : number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    /** Decimal string from a gateway ("1000", "14.5") to minor units. */
    public static function decimalStringToMinor(string|int|float $amount, string $code): int
    {
        return (int) round(((float) $amount) * (10 ** self::decimals($code)));
    }

    /** Human label for minor units, e.g. "USD 14.50" / "XOF 1,000". */
    public static function labelMinor(int $minor, string $code): string
    {
        $decimals = self::decimals($code);

        return self::normalise($code).' '.number_format($minor / (10 ** $decimals), $decimals);
    }
}
