<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One-time sign-in code. Not queued: it must leave in the same request as invitations do.
 */
class TwoFactorCodeNotification extends Notification
{
    public function __construct(
        public string $code,
        public int $expireMinutes = 10,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Gambia SchoolOS sign-in code')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your sign-in code is **'.$this->code.'**.')
            ->line('It expires in '.$this->expireMinutes.' minutes. If you did not try to sign in, you can ignore this email.');
    }
}
