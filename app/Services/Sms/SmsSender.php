<?php

namespace App\Services\Sms;

use App\Models\School;

/**
 * Backward-compatible entry point (Phase 3 callers). New code uses {@see SmsService} or a notification with the
 * SMS channel. A message sent through here is recorded, capped and retried like any other.
 */
class SmsSender
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(string $to, string $message, array $context = []): void
    {
        $school = isset($context['school_id']) ? School::query()->find($context['school_id']) : app(\App\Support\TenantContext::class)->school();

        if ($school) {
            $this->sms->queue($school, null, $to, $message, ['category' => $context['category'] ?? null]);
        }
    }
}
