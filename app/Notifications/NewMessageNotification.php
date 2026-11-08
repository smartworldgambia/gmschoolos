<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You have a new message." E-mail and SMS name the sender only and never carry the message text: the message stays inside
 * the school system. One unread alert per conversation is enough (see MessagingService).
 */
class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsPreferences;

    public function __construct(public Conversation $conversation, public string $senderName, public string $snippet)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function category(): string
    {
        return 'message';
    }

    public function offeredChannels(): array
    {
        return ['in_app', 'email', 'sms'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New message from '.$this->senderName)
            ->line($this->senderName.' sent you a message in SchoolOS.')
            ->action('Open messages', url(route('messages.show', $this->conversation->id, false)));
    }

    /** @return array<string,mixed> */
    public function toSms(object $notifiable): array
    {
        return ['template' => 'message_received', 'vars' => ['sender' => $this->senderName], 'category' => 'message'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New message from '.$this->senderName,
            'body' => $this->snippet,
            'action_url' => route('messages.show', $this->conversation->id, false),
            'level' => 'info',
            'category' => 'message',
            'conversation_id' => $this->conversation->id,
        ];
    }
}
