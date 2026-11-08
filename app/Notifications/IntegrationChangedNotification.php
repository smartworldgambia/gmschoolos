<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Told to the OTHER platform administrators whenever mail / SMS credentials are saved, switched on or off, or removed.
 * Deliberately sent immediately (not queued) and never contains a value.
 */
class IntegrationChangedNotification extends Notification
{
    public function __construct(public string $integration, public string $what, public string $actorName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $thing = $this->integration === 'mail' ? 'e-mail (SMTP)' : 'SMS';
        $did = match ($this->what) {
            'activated' => 'switched ON the platform '.$thing.' credentials',
            'deactivated' => 'switched OFF the platform '.$thing.' credentials (the server default is used again)',
            'cleared' => 'removed the saved platform '.$thing.' credentials',
            default => 'changed the saved platform '.$thing.' credentials',
        };

        return (new MailMessage)
            ->subject('Platform '.$thing.' settings changed')
            ->greeting('Security notice')
            ->line($this->actorName.' '.$did.'.')
            ->line('If you do not recognise this change, review the platform console and the audit trail straight away.');
    }
}
