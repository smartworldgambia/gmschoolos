<?php

namespace App\Jobs;

use App\Models\School;
use App\Models\SmsMessage;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTransientException;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Sends one SMS. Transient provider trouble is retried with the configured back-off; permanent trouble is recorded and stops. */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;   // upper bound for the queue; the real limit is schoolos.sms.max_attempts below

    public function __construct(public int $smsMessageId, public int $schoolId)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function handle(SmsService $sms, TenantContext $tenant): void
    {
        $school = $tenant->runAsPlatform(fn () => School::query()->find($this->schoolId));
        if (! $school) {
            return;
        }

        $tenant->runForSchool($school, function () use ($sms) {
            $message = SmsMessage::query()->find($this->smsMessageId);
            if (! $message || $message->status !== 'queued') {
                return;
            }

            try {
                $sms->deliver($message);
            } catch (SmsTransientException $e) {
                $max = max(1, (int) config('schoolos.sms.max_attempts', 3));
                if ((int) $message->attempts >= $max) {
                    $sms->fail($message, 'gave_up_after_'.$max.'_attempts: '.$e->getMessage());

                    return;
                }

                $backoff = (array) config('schoolos.sms.backoff', [60, 300, 900]);
                $this->release((int) ($backoff[min($message->attempts - 1, count($backoff) - 1)] ?? 60));
            }
        });
    }

    public function failed(Throwable $e): void
    {
        $tenant = app(TenantContext::class);
        $school = $tenant->runAsPlatform(fn () => School::query()->find($this->schoolId));

        if ($school) {
            $tenant->runForSchool($school, function () use ($e) {
                $message = SmsMessage::query()->find($this->smsMessageId);
                if ($message && in_array($message->status, ['queued'], true)) {
                    app(SmsService::class)->fail($message, 'worker_error: '.$e->getMessage());
                }
            });
        }
    }
}
