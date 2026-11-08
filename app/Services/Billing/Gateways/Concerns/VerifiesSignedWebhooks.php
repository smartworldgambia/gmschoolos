<?php

namespace App\Services\Billing\Gateways\Concerns;

/**
 * Shared "t=timestamp,v1=hex" HMAC-SHA256 verification (used by Stripe and Wave signing secrets).
 * Signed payload = timestamp + [separator] + RAW body. Constant-time comparison, replay window enforced.
 */
trait VerifiesSignedWebhooks
{
    /**
     * @param  string  $separator  '.' for Stripe ("t.body"), '' for Wave ("tbody")
     */
    protected function signatureMatches(string $rawBody, ?string $header, string $secret, int $tolerance, string $separator): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false; // replay window
        }

        $expected = hash_hmac('sha256', $timestamp.$separator.$rawBody, $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
