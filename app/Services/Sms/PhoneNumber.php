<?php

namespace App\Services\Sms;

/**
 * Turns what people type ("7123456", "+220 712 3456", "00220-7123456") into E.164 ("+2207123456"), or null when
 * it cannot be a real number. A local number without a country code gets the configured country code
 * (The Gambia, 220, by default) - nothing else is ever guessed.
 */
class PhoneNumber
{
    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '' || preg_match('/[A-Za-z]/', $raw)) {
            return null;
        }

        $plus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (! $plus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $plus = true;
        }

        if (! $plus) {
            $cc = (string) config('schoolos.sms.country_code', '220');
            $local = ltrim($digits, '0');
            // Gambian local numbers have 7 digits; a longer string without "+" must already carry the country code.
            $digits = strlen($local) === 7 ? $cc.$local : (str_starts_with($local, $cc) ? $local : '');
        }

        // E.164: at most 15 digits; shorter than 8 cannot be a full international number.
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15 || $digits[0] === '0') {
            return null;
        }

        return '+'.$digits;
    }

    /** Number of SMS parts the text will be billed as (GSM-7 160/153, anything else UCS-2 70/67). */
    public static function segments(string $text): int
    {
        $len = mb_strlen($text);
        if ($len === 0) {
            return 1;
        }

        $gsm = preg_match('/^[\x20-\x7E\n\r£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¡ÄÖÑÜ§¿äöñüà]*$/u', $text) === 1;
        [$single, $multi] = $gsm ? [160, 153] : [70, 67];

        return $len <= $single ? 1 : (int) ceil($len / $multi);
    }
}
