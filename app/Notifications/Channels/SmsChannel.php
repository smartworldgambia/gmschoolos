<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Notifications\Notification;

/**
 * SMS notification channel. A notification offers `toSms($notifiable)` returning
 * `['template' => key, 'vars' => [...], 'category' => 'attendance']`. The channel only queues the message;
 * SmsService decides whether the school has SMS on, applies the daily cap and records the outcome.
 */
class SmsChannel
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notifiable->school || ! method_exists($notification, 'toSms')) {
            return;
        }

        $spec = $notification->toSms($notifiable);
        if (! is_array($spec) || ! isset($spec['template'])) {
            return;
        }

        $this->sms->queueTemplate(
            $notifiable->school, $notifiable, $notifiable->routeNotificationFor('sms', $notification),
            $spec['template'], (array) ($spec['vars'] ?? []), $spec['category'] ?? null,
        );
    }
}
