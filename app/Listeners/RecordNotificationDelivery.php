<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use App\Models\School;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Support\TenantContext;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivery history for in-app and e-mail notifications.
 *
 * Honest wording: "sent" means the mail transport accepted the message; there are no bounce or read receipts, so e-mail is
 * never shown as "delivered". With the `log` or `array` mailer nothing leaves the server and the row says "logged".
 * SMS has its own detailed log (sms_messages) and is not repeated here.
 */
class RecordNotificationDelivery
{
    public function onSent(NotificationSent $event): void
    {
        $this->record($event->notifiable, $event->notification, (string) $event->channel, $this->okStatus((string) $event->channel), null);
    }

    public function onFailed(NotificationFailed $event): void
    {
        $this->record($event->notifiable, $event->notification, (string) $event->channel, 'failed', (string) ($event->data['message'] ?? 'channel_failure'));
    }

    /** A queued e-mail that the transport refused ends in a failed job: make that visible in the history too. */
    public function onJobFailed(JobFailed $event): void
    {
        try {
            if ($event->job->resolveName() !== SendQueuedNotifications::class) {
                return;
            }

            $command = unserialize($event->job->payload()['data']['command'] ?? '');
            if (! $command instanceof SendQueuedNotifications) {
                return;
            }

            foreach ((array) $command->notifiables as $notifiable) {
                foreach ((array) $command->channels as $channel) {
                    $this->record($notifiable, $command->notification, (string) $channel, 'failed', $event->exception->getMessage());
                }
            }
        } catch (Throwable) {
            // never let history keeping interfere with the queue
        }
    }

    private function okStatus(string $channel): string
    {
        return $channel === 'mail' && in_array(config('mail.default'), ['log', 'array'], true) ? 'logged' : 'sent';
    }

    private function record(mixed $notifiable, object $notification, string $channel, string $status, ?string $error): void
    {
        $label = match ($channel) {
            'database' => 'in_app',
            'mail' => 'email',
            default => null,
        };

        if ($label === null || $channel === SmsChannel::class) {
            return;
        }

        try {
            $schoolId = $notifiable instanceof User ? $notifiable->school_id : ($notification->school->id ?? null);
            if (! $schoolId) {
                return;
            }

            $school = School::query()->find($schoolId);
            if (! $school) {
                return;
            }

            $category = method_exists($notification, 'category') ? $notification->category() : null;
            $summary = null;
            if (method_exists($notification, 'toArray')) {
                $summary = $notification->toArray($notifiable)['title'] ?? null;
            }
            $summary ??= $notification->title ?? class_basename($notification);

            app(TenantContext::class)->runForSchool($school, fn () => NotificationDelivery::query()->create([
                'school_id' => $schoolId,
                'user_id' => $notifiable instanceof User ? $notifiable->id : null,
                'notification_id' => isset($notification->id) ? (string) $notification->id : null,
                'notification_type' => get_class($notification),
                'category' => $category,
                'channel' => $label,
                'status' => $status,
                'summary' => mb_substr((string) $summary, 0, 190),
                'error' => $error !== null ? mb_substr($error, 0, 480) : null,
            ]));
        } catch (Throwable $e) {
            Log::warning('notification.delivery_record_failed', ['error' => $e->getMessage()]);
        }
    }
}
