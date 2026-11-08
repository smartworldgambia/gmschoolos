<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Log;

/** Development gateway: writes the text to the log. Messages are recorded as "logged" - never as sent or delivered. */
class LogGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function configured(): bool
    {
        return true;
    }

    public function send(string $to, string $body, ?string $from = null): SmsResult
    {
        Log::channel('single')->info('schoolos.sms', ['to' => $to, 'from' => $from, 'body' => $body]);

        return SmsResult::logged();
    }
}
