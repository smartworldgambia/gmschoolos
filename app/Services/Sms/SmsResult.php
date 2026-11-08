<?php

namespace App\Services\Sms;

/** What a provider said about one send attempt. `permanent` failures are never retried. */
final class SmsResult
{
    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerRef = null,
        public readonly bool $logged = false,
        public readonly ?float $cost = null,
        public readonly ?string $currency = null,
        public readonly ?string $error = null,
        public readonly bool $permanent = false,
    ) {}

    public static function accepted(?string $ref, ?float $cost = null, ?string $currency = null): self
    {
        return new self(true, $ref, false, $cost, $currency);
    }

    /** The log driver "sent" nothing to anybody. */
    public static function logged(): self
    {
        return new self(true, null, true);
    }

    public static function failed(string $error, bool $permanent): self
    {
        return new self(false, null, false, null, null, mb_substr($error, 0, 480), $permanent);
    }
}
