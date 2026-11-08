<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Africa's Talking bulk SMS. Status: Requires External Configuration - covered by tests against faked HTTP only;
 * no real message has been delivered by this integration yet.
 */
class AfricasTalkingGateway implements SmsGateway
{
    public function name(): string
    {
        return 'africastalking';
    }

    public function configured(): bool
    {
        $c = config('schoolos.sms.africastalking');

        return filled($c['username'] ?? null) && filled($c['api_key'] ?? null);
    }

    public function send(string $to, string $body, ?string $from = null): SmsResult
    {
        if (! $this->configured()) {
            return SmsResult::failed('africastalking_not_configured', true);
        }

        $c = config('schoolos.sms.africastalking');
        $url = ($c['sandbox'] ?? false)
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';

        $payload = ['username' => $c['username'], 'to' => $to, 'message' => $body];
        if ($sender = ($from ?: ($c['from'] ?? null))) {
            $payload['from'] = $sender;
        }

        try {
            $response = Http::withHeaders(['apiKey' => $c['api_key']])->asForm()->acceptJson()
                ->timeout((int) config('schoolos.sms.timeout', 15))->post($url, $payload);
        } catch (ConnectionException $e) {
            return SmsResult::failed('network: '.$e->getMessage(), false);
        }

        if (! $response->successful()) {
            return SmsResult::failed('HTTP '.$response->status(), ! ($response->status() === 429 || $response->status() >= 500));
        }

        $recipient = $response->json('SMSMessageData.Recipients.0');
        if (! is_array($recipient)) {
            return SmsResult::failed((string) ($response->json('SMSMessageData.Message') ?: 'no_recipient_result'), true);
        }

        // statusCode: 100 processed, 101 sent, 102 queued. Anything else is a rejection (invalid number, risk hold, sender id ...).
        $code = (int) ($recipient['statusCode'] ?? 0);
        if (in_array($code, [100, 101, 102], true)) {
            [$amount, $currency] = $this->cost((string) ($recipient['cost'] ?? ''));

            return SmsResult::accepted((string) ($recipient['messageId'] ?? ''), $amount, $currency);
        }

        return SmsResult::failed(($recipient['status'] ?? 'rejected').' ('.$code.')', true);
    }

    /** "GMD 0.8000" -> [0.8, 'GMD'] */
    private function cost(string $raw): array
    {
        if (preg_match('/^([A-Z]{3})\s*([0-9]+(?:\.[0-9]+)?)$/', trim($raw), $m)) {
            return [(float) $m[2], $m[1]];
        }

        return [null, null];
    }
}
