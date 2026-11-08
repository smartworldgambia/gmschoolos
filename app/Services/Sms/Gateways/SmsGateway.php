<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\SmsResult;

interface SmsGateway
{
    /** Provider key stored with every message (twilio, africastalking, log, ...). */
    public function name(): string;

    /** True when the credentials needed to send are present. Says nothing about whether delivery works. */
    public function configured(): bool;

    /** Hand one message to the provider. Network errors and 5xx / 429 answers are transient (permanent = false). */
    public function send(string $to, string $body, ?string $from = null): SmsResult;
}
