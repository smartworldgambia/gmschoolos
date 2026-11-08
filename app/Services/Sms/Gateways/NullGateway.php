<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\SmsResult;

/** SMS switched off at platform level: every attempt is recorded as a permanent failure so nobody believes it went out. */
class NullGateway implements SmsGateway
{
    public function name(): string
    {
        return 'null';
    }

    public function configured(): bool
    {
        return false;
    }

    public function send(string $to, string $body, ?string $from = null): SmsResult
    {
        return SmsResult::failed('sms_driver_disabled', true);
    }
}
