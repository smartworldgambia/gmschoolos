<?php

namespace App\Services\Sms;

use App\Jobs\SendSmsJob;
use App\Models\School;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Communications\CommunicationSettings;
use App\Services\Sms\Gateways\AfricasTalkingGateway;
use App\Services\Sms\Gateways\LogGateway;
use App\Services\Sms\Gateways\NullGateway;
use App\Services\Sms\Gateways\SmsGateway;
use App\Services\Sms\Gateways\TwilioGateway;
use App\Support\TenantContext;

/**
 * Queues, sends, retries and tracks SMS.
 *
 * Status meanings (shown as such in the SMS log):
 *   queued    - waiting for a worker
 *   sent      - accepted by the provider (this is NOT proof the handset received it)
 *   delivered - the provider's delivery report says it reached the handset (needs the provider webhook configured)
 *   failed    - rejected, capped, invalid number, or out of retries; the reason is kept
 *   logged    - the `log` driver wrote it to a file; nothing left the server
 */
class SmsService
{
    public function __construct(private readonly SmsTemplates $templates, private readonly TenantContext $tenant) {}

    public function gateway(): SmsGateway
    {
        return match (config('schoolos.sms.driver', 'log')) {
            'twilio' => new TwilioGateway,
            'africastalking' => new AfricasTalkingGateway,
            'null' => new NullGateway,
            default => new LogGateway,
        };
    }

    /** @return array{driver:string,configured:bool,real:bool} */
    public function providerState(): array
    {
        $gw = $this->gateway();

        return ['driver' => $gw->name(), 'configured' => $gw->configured(), 'real' => in_array($gw->name(), ['twilio', 'africastalking'], true)];
    }

    /** True when this school has SMS switched on. The provider may still be unconfigured; messages then fail visibly. */
    public function enabledFor(School $school): bool
    {
        return CommunicationSettings::smsEnabled($school);
    }

    /** @param  array<string,scalar|null>  $vars */
    public function queueTemplate(School $school, ?User $user, ?string $phone, string $key, array $vars, ?string $category = null): ?SmsMessage
    {
        $locale = $user?->locale ?: (string) data_get($school->settings, 'defaults.locale', 'en');

        $body = $this->tenant->runForSchool($school, fn () => $this->templates->render($key, $locale, $vars + ['school' => $school->name]));
        if ($body === null || $body === '') {
            return null;
        }

        return $this->queue($school, $user, $phone, $body, ['template' => $key, 'category' => $category]);
    }

    /**
     * Record the message and hand it to the queue. Returns null when SMS is off for the school or there is no number at
     * all; an unusable number or an exhausted daily cap is recorded as a failed message so the history explains it.
     *
     * @param  array{template?:?string,category?:?string}  $opts
     */
    public function queue(School $school, ?User $user, ?string $phone, string $body, array $opts = []): ?SmsMessage
    {
        if (! $this->enabledFor($school) || blank($phone)) {
            return null;
        }

        return $this->tenant->runForSchool($school, function () use ($school, $user, $phone, $body, $opts) {
            $e164 = PhoneNumber::normalise($phone);

            $message = SmsMessage::query()->create([
                'school_id' => $school->id, 'user_id' => $user?->id, 'to_phone' => $e164 ?? mb_substr((string) $phone, 0, 24),
                'template_key' => $opts['template'] ?? null, 'category' => $opts['category'] ?? null,
                'body' => $body, 'segments' => PhoneNumber::segments($body), 'status' => 'queued', 'provider' => $this->gateway()->name(),
            ]);

            if ($e164 === null) {
                return $this->fail($message, 'invalid_phone');
            }

            $today = SmsMessage::query()->where('school_id', $school->id)->whereDate('created_at', now()->toDateString())
                ->where('status', '!=', 'failed')->where('id', '!=', $message->id)->count();
            if ($today >= CommunicationSettings::dailyCap($school)) {
                return $this->fail($message, 'daily_cap_reached');
            }

            SendSmsJob::dispatch($message->id, $school->id);

            return $message;
        });
    }

    /** One delivery attempt. Throws {@see SmsTransientException} when a retry makes sense. */
    public function deliver(SmsMessage $message): SmsMessage
    {
        if (in_array($message->status, ['sent', 'delivered', 'logged'], true)) {
            return $message;          // idempotent: a retried job never sends twice
        }

        $school = $message->school;
        $result = $this->gateway()->send($message->to_phone, $message->body, $school ? CommunicationSettings::smsSender($school) : null);
        $message->attempts = (int) $message->attempts + 1;

        if ($result->accepted) {
            $message->forceFill([
                'status' => $result->logged ? 'logged' : 'sent', 'provider_ref' => $result->providerRef, 'sent_at' => $result->logged ? null : now(),
                'cost_amount' => $result->cost, 'cost_currency' => $result->currency, 'last_error' => null, 'failed_at' => null,
            ])->save();

            return $message;
        }

        if ($result->permanent) {
            return $this->fail($message, (string) $result->error);
        }

        $message->forceFill(['last_error' => $result->error])->save();

        throw new SmsTransientException((string) $result->error);
    }

    public function fail(SmsMessage $message, string $reason): SmsMessage
    {
        $message->forceFill(['status' => 'failed', 'failed_at' => now(), 'last_error' => mb_substr($reason, 0, 480)])->save();

        return $message;
    }

    /** A manual retry of a failed message (staff action). */
    public function retry(SmsMessage $message): SmsMessage
    {
        if ($message->status !== 'failed' || ! PhoneNumber::normalise($message->to_phone)) {
            return $message;
        }

        $message->forceFill(['status' => 'queued', 'failed_at' => null, 'last_error' => null, 'attempts' => 0])->save();
        SendSmsJob::dispatch($message->id, (int) $message->school_id);

        return $message;
    }

    /**
     * Apply a provider delivery report. Final states are never downgraded, and a report for an unknown reference is ignored.
     *
     * @param  string  $status  one of queued|sent|delivered|failed
     */
    public function applyReport(string $provider, string $ref, string $status, ?string $error = null): bool
    {
        if ($ref === '') {
            return false;
        }

        return (bool) $this->tenant->runAsPlatform(function () use ($provider, $ref, $status, $error) {
            $message = SmsMessage::query()->where('provider', $provider)->where('provider_ref', $ref)->first();
            if (! $message || $message->status === 'delivered') {
                return false;
            }

            if ($status === 'delivered') {
                $message->forceFill(['status' => 'delivered', 'delivered_at' => now(), 'last_error' => null])->save();
            } elseif ($status === 'failed') {
                $message->forceFill(['status' => 'failed', 'failed_at' => now(), 'last_error' => mb_substr((string) ($error ?: 'provider_reported_failure'), 0, 480)])->save();
            } elseif ($status === 'sent' && $message->status === 'queued') {
                $message->forceFill(['status' => 'sent', 'sent_at' => $message->sent_at ?? now()])->save();
            }

            return true;
        });
    }
}
