<?php

namespace App\Notifications;

use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * General in-app + e-mail (+ optional SMS) notice. `category` decides which preference applies (announcement, lms, account...);
 * `offered` is what the sender allows for this one notice (an announcement author chooses e-mail and SMS per announcement).
 */
class SchoolAnnouncement extends Notification implements ShouldQueue
{
    use Queueable, RespectsPreferences;

    /** @param  array<int,string>  $offered */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public string $level = 'info',
        public string $category = 'announcement',
        public array $offered = ['in_app', 'email'],
    ) {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function category(): string
    {
        return $this->category;
    }

    public function offeredChannels(): array
    {
        return $this->offered;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);

        if ($this->actionUrl) {
            $mail->action('Open in SchoolOS', url($this->actionUrl));
        }

        return $mail;
    }

    /** @return array<string,mixed> */
    public function toSms(object $notifiable): array
    {
        return ['template' => 'announcement', 'vars' => ['title' => $this->title], 'category' => $this->category];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'level' => $this->level,
            'category' => $this->category,
        ];
    }
}
