<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Digest for staff: results / certificates that are still waiting to be collected. Carries counts only (no student data). */
class CollectionReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $schoolName, public int $results, public int $certificates)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Outstanding collections - {$this->schoolName}")
            ->line("{$this->results} printed result slip(s) and {$this->certificates} certificate(s) are still waiting to be collected.")
            ->action('Open collection register', route('external-exams.collections.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Outstanding collections',
            'body' => "{$this->results} result(s) and {$this->certificates} certificate(s) awaiting collection",
            'action_url' => route('external-exams.collections.index'),
            'level' => 'warning',
            'results' => $this->results,
            'certificates' => $this->certificates,
        ];
    }
}
