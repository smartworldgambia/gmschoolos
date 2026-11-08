<?php

namespace App\Notifications;

use App\Models\SchoolInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A visitor used the contact form on the school's public website.
 * School admins get an in-app notification; the school's contact address gets an email
 * (reply-to is the visitor, so "Reply" just works).
 */
class SchoolInquiryReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SchoolInquiry $inquiry)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->inquiry;

        $mail = (new MailMessage)
            ->subject('New website enquiry'.($i->subject ? ': '.$i->subject : ' from '.$i->name))
            ->replyTo($i->email, $i->name)
            ->line("{$i->name} <{$i->email}>".($i->phone ? " · {$i->phone}" : ''))
            ->line($i->message);

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $i = $this->inquiry;

        return [
            'title' => 'New website enquiry'.($i->subject ? ': '.$i->subject : ' from '.$i->name),
            'body' => "{$i->name} ({$i->email}".($i->phone ? ", {$i->phone}" : ''). '): '.Str::limit($i->message, 240),
            'action_url' => null,
            'level' => 'info',
        ];
    }
}
