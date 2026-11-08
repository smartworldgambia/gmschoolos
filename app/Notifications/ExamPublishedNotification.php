<?php

namespace App\Notifications;

use App\Models\Examination;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExamPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsPreferences;

    public function __construct(public Examination $examination)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function category(): string
    {
        return 'exam';
    }

    public function offeredChannels(): array
    {
        return ['in_app', 'email', 'sms'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Results published: '.$this->examination->name)
            ->line('Examination results are now available in your portal.')
            ->action('Open dashboard', url(route('dashboard')));
    }

    /** @return array<string,mixed> */
    public function toSms(object $notifiable): array
    {
        return ['template' => 'exam_published', 'vars' => ['exam' => $this->examination->name], 'category' => 'exam'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Results published',
            'body' => $this->examination->name.' is now available.',
            'action_url' => route('dashboard'),
            'level' => 'info',
            'category' => 'exam',
            'examination_id' => $this->examination->id,
        ];
    }
}
