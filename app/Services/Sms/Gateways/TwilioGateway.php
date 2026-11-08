<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Twilio Programmable Messaging (REST, HTTP basic auth). Status: Requires External Configuration - the code is covered by
 * tests against faked HTTP only; no real message has been delivered by this integration yet.
 */
class TwilioGateway implements SmsGateway
{
    public function name(): string
    {
        return 'twilio';
    }

    public function configured(): bool
    {
        $c = config('schoolos.sms.twilio');

        return filled($c['sid'] ?? null) && filled($c['token'] ?? null) && (filled($c['from'] ?? null) || filled($c['messaging_service_sid'] ?? null));
    }

    public function send(string $to, string $body, ?string $from = null): SmsResult
    {
        if (! $this->configured()) {
            return SmsResult::failed('twilio_not_configured', true);
        }

        $c = config('schoolos.sms.twilio');
        $payload = ['To' => $to, 'Body' => $body];

        if (filled($c['messaging_service_sid'] ?? null)) {
            $payload['MessagingServiceSid'] = $c['messaging_service_sid'];
        } else {
            $payload['From'] = $from ?: $c['from'];
        }

        if (filled($c['status_callback'] ?? null)) {
            $payload['StatusCallback'] = $c['status_callback'];
        }

        try {
            $response = Http::withBasicAuth($c['sid'], $c['token'])->asForm()->acceptJson()
                ->timeout((int) config('schoolos.sms.timeout', 15))
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$c['sid']}/Messages.json", $payload);
        } catch (ConnectionException $e) {
            return SmsResult::failed('network: '.$e->getMessage(), false);
        }

        if ($response->successful()) {
            $price = $response->json('price');

            return SmsResult::accepted(
                (string) $response->json('sid'),
                $price !== null ? abs((float) $price) : null,   // normally null at creation: Twilio prices a message later
                $response->json('price_unit'),
            );
        }

        $error = trim(($response->json('code') ? $response->json('code').': ' : '').($response->json('message') ?? 'HTTP '.$response->status()));

        // 429 and 5xx are worth another try; everything else (bad number, bad credentials, blocked country) is not.
        return SmsResult::failed($error, ! ($response->status() === 429 || $response->status() >= 500));
    }
}
