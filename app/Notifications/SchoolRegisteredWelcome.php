<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolRegisteredWelcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public School $school,
        public string $dashboardUrl,
        public string $schoolHost,
        public int $trialDays = 30,
        public ?string $publicSiteUrl = null,
        public ?string $trialEndsOn = null,
    ) {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Welcome to Gambia SchoolOS - '.$this->school->name)
            ->greeting('Welcome, '.$notifiable->name.'!')
            ->line('Your school **'.$this->school->name.'** is registered and ready on Gambia SchoolOS.')
            ->line('Your '.$this->trialDays.'-day free trial has started'.($this->trialEndsOn ? ' and runs until '.$this->trialEndsOn : '').'. No payment details are needed during the trial. Annual billing begins only if you choose to subscribe.')
            ->line('Verify your email address first, then follow the school setup guide to finish configuring your school.')
            ->line('School address: '.$this->schoolHost);

        if ($this->publicSiteUrl) {
            $mail->line('Your public school landing page: '.$this->publicSiteUrl);
        }

        return $mail
            ->action('Open your dashboard', $this->dashboardUrl)
            ->line('If you did not create this account, please contact support.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to Gambia SchoolOS',
            'body' => $this->school->name.' is live. Your '.$this->trialDays.'-day trial has started.',
            'action_url' => $this->dashboardUrl,
            'school_host' => $this->schoolHost,
            'public_site_url' => $this->publicSiteUrl,
            'level' => 'success',
        ];
    }
}
