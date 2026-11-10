<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on-demand to the invitee's address. Carries the one-time acceptance link.
 *
 * Not queued: the `notifications` worker is optional/sheddable, and Laravel Cloud
 * typically only processes `default`. Invitation mail must leave in the same request.
 */
class StaffInvitationNotification extends Notification
{
    public function __construct(
        public School $school,
        public string $inviteeName,
        public string $roleLabel,
        public string $acceptUrl,
        public string $expiresOn,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are invited to join '.$this->school->name.' on Gambia SchoolOS')
            ->greeting('Hello '.$this->inviteeName.',')
            ->line($this->school->name.' has invited you to join its Gambia SchoolOS workspace as **'.$this->roleLabel.'**.')
            ->line('Use the button below to choose your password and activate your account. The link works once and expires on '.$this->expiresOn.'.')
            ->action('Accept invitation', $this->acceptUrl)
            ->line('If you were not expecting this invitation you can ignore this email.');
    }
}
