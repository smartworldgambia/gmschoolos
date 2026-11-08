<?php

namespace App\Notifications\Concerns;

use App\Services\Notifications\NotificationPreferences;

/**
 * Channel selection by the person's own preferences. A notification names its `category()`, and may narrow the channels it
 * can use with `offeredChannels()` (in_app, email, sms). Everything else - mandatory categories, verified e-mail, SMS
 * opt-in, school SMS switch - is decided in {@see NotificationPreferences}.
 */
trait RespectsPreferences
{
    abstract public function category(): string;

    /** @return array<int,string> */
    public function offeredChannels(): array
    {
        return ['in_app', 'email'];
    }

    public function via(object $notifiable): array
    {
        return app(NotificationPreferences::class)->channels($notifiable, $this->category(), $this->offeredChannels());
    }
}
