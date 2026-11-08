<?php

namespace App\Notifications;

use App\Models\AdmissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** `staff` mode alerts the admissions team; `applicant` mode acknowledges receipt to the guardian. */
class AdmissionApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AdmissionApplication $application, public string $schoolName, public string $mode = 'staff')
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return $this->mode === 'staff' ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->mode === 'applicant') {
            return (new MailMessage)
                ->subject("Application received - {$this->schoolName}")
                ->line("{$this->schoolName} has received the application for {$this->application->full_name}.")
                ->line('Your application number is '.$this->application->application_number.'. Please quote it when you contact the school.')
                ->line('Receiving an application is not an offer of a place. The school will contact you after review.');
        }

        return (new MailMessage)
            ->subject('New admission application: '.$this->application->full_name)
            ->line("{$this->application->full_name} applied online ({$this->application->application_number}).")
            ->action('Review applications', route('admissions.show', $this->application->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New admission application',
            'body' => $this->application->full_name.' - '.$this->application->application_number,
            'action_url' => route('admissions.show', $this->application->id),
            'level' => 'info',
            'application_id' => $this->application->id,
        ];
    }
}
