<?php

namespace App\Notifications;

use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Notifications\Concerns\RespectsPreferences;
use App\Services\Sms\SmsTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Guardian alert: absent / late / left early. In-app, e-mail and (opt-in, school-enabled) SMS. Delivery of e-mail and SMS is NOT verified. */
class AttendanceAlertNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsPreferences;

    public function __construct(public AttendanceRecord $record, public ?Student $student)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function category(): string
    {
        return 'attendance';
    }

    public function offeredChannels(): array
    {
        return ['in_app', 'email', 'sms'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->student?->full_name ?? 'Your child';

        return (new MailMessage)
            ->subject('Attendance alert: '.$name)
            ->line("{$name} was marked {$this->record->status} on {$this->record->attended_on?->toFormattedDateString()}.")
            ->action('Open dashboard', url(route('dashboard')));
    }

    /** @return array<string,mixed> */
    public function toSms(object $notifiable): array
    {
        $locale = $notifiable->locale ?: 'en';

        return [
            'template' => 'attendance_alert', 'category' => 'attendance',
            'vars' => [
                'student' => $this->student?->full_name ?? '',
                'status' => app(SmsTemplates::class)->statusWord((string) $this->record->status, $locale),
                'date' => $this->record->attended_on?->toDateString(),
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Attendance alert',
            'body' => ($this->student?->full_name ?? '').' - '.$this->record->status.' - '.$this->record->attended_on?->toDateString(),
            'action_url' => route('dashboard'),
            'level' => 'warning',
            'category' => 'attendance',
            'student_id' => $this->record->student_id,
            'attendance_status' => $this->record->status,
        ];
    }
}
