<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trial / annual-subscription lifecycle notice (reminders, expiry, suspension, activation).
 * Carries plain data only (no tenant-scoped models) so queue workers can render it fail-closed.
 */
class SubscriptionLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data  school_name, school_type_label, amount_label, expiry_date, days_left, payment_url, support_email, support_phone, payment_methods
     */
    public function __construct(public string $kind, public array $data, public ?int $logId = null)
    {
        $this->onQueue(config('schoolos.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        // Anonymous (billing e-mail fallback) recipients get mail only.
        return $notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->data;
        $name = $notifiable->name ?? 'Administrator';
        [$subject, $lead] = $this->copy();

        $mail = (new MailMessage)
            ->subject($subject.' - '.$d['school_name'])
            ->greeting("Hello {$name},")
            ->line($lead)
            ->line('School: '.$d['school_name'])
            ->line('School type: '.$d['school_type_label'])
            ->line('Annual subscription amount: '.($d['amount_label'] ?? 'to be confirmed by the platform'))
            ->line('Expiry date: '.($d['expiry_date'] ?? 'n/a'))
            ->line('Payment methods: '.implode(', ', $d['payment_methods'] ?? []));

        if ($this->kind !== 'activated' && ! empty($d['payment_url'])) {
            $mail->action('Open billing', $d['payment_url']);
        }

        return $mail->line('Support: '.trim(($d['support_email'] ?? '').' '.($d['support_phone'] ?? '')))
            ->line('Gambia SchoolOS subscriptions are annual. Your school records are never deleted if a subscription lapses.');
    }

    public function toArray(object $notifiable): array
    {
        [$subject, $lead] = $this->copy();

        return [
            'title' => $subject,
            'body' => $lead,
            'action_url' => $this->data['payment_url'] ?? null,
            'level' => in_array($this->kind, ['activated'], true) ? 'success'
                : (str_contains($this->kind, 'expired') || $this->kind === 'suspended' ? 'danger' : 'warning'),
        ];
    }

    /** @return array{0: string, 1: string} [subject, lead sentence] */
    protected function copy(): array
    {
        $d = $this->data;
        $days = $d['days_left'] ?? null;
        $when = $days === 0 ? 'today' : ($days === 1 ? 'in 1 day' : "in {$days} days");

        return match (true) {
            $this->kind === 'activated' => [
                'Annual subscription activated',
                'Your payment was verified and your annual subscription is active until '.($d['expiry_date'] ?? 'n/a').'.',
            ],
            $this->kind === 'payment_rejected' => [
                'Payment could not be verified',
                'We could not verify payment '.($d['payment_reference'] ?? '').'. Reason: '.($d['reason'] ?? 'not provided').'. Your subscription has not changed. You can start a new payment from the billing page.',
            ],
            $this->kind === 'trial_expired' => [
                'Free trial expired',
                'Your 30-day free trial has ended. Protected school services are paused until the annual subscription is paid. Your data is safe.',
            ],
            $this->kind === 'paid_expired' => [
                'Annual subscription expired',
                'Your annual subscription has ended. Protected school services are paused until it is renewed. Your data is safe.',
            ],
            $this->kind === 'suspended' => [
                'Account suspended',
                'Your school account is suspended because the annual subscription is unpaid. Pay the annual amount to restore access. Your data is safe.',
            ],
            str_starts_with($this->kind, 'trial_') => [
                'Free trial ends '.$when,
                "Your free trial ends {$when}. Subscribe annually to keep using Gambia SchoolOS without interruption.",
            ],
            default => [
                'Annual subscription renews '.$when,
                "Your annual subscription ends {$when}. Renew to keep your school online without interruption.",
            ],
        };
    }
}