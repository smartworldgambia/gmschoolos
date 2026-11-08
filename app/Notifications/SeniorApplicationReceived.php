<?php

namespace App\Notifications;

use App\Models\SsApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** `staff` alerts the admissions team; `applicant` acknowledges receipt to the guardian (never an offer of a place). */
class SeniorApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SsApplication $application, public string $schoolName, public string $mode = 'staff')
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return $this->mode === 'staff' ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($this->application->first_name.' '.$this->application->last_name);

        if ($this->mode === 'applicant') {
            return (new MailMessage)
                ->subject("Senior Secondary application received - {$this->schoolName}")
                ->line("{$this->schoolName} has received the Senior Secondary application for {$name}.")
                ->line('Your application reference is '.$this->application->reference.'. Please quote it when you contact the school.')
                ->line('Receiving an application is not an offer of a place. All applications are reviewed by the school.');
        }

        return (new MailMessage)
            ->subject('New Senior Secondary application: '.$name)
            ->line("{$name} applied ({$this->application->reference}).")
            ->action('Review application', route('senior-secondary.applications.show', $this->application->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Senior Secondary application',
            'body' => trim($this->application->first_name.' '.$this->application->last_name).' - '.$this->application->reference,
            'action_url' => route('senior-secondary.applications.show', $this->application->id),
            'level' => 'info',
            'application_id' => $this->application->id,
        ];
    }
}
