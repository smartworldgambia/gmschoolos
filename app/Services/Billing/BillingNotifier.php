<?php

namespace App\Services\Billing;

use App\Models\BillingNotificationLog;
use App\Models\SchoolSubscription;
use App\Models\User;
use App\Notifications\SubscriptionLifecycleNotification;
use App\Services\School\SchoolDomainService;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends lifecycle notices once per (subscription, kind, expiry date) and records the outcome.
 * "Queued" is NOT "delivered": status moves to sent/failed only from the notification events.
 */
class BillingNotifier
{
    public function __construct(protected TenantContext $tenant) {}

    /** @return bool true when a notification was dispatched (false = duplicate or no recipients) */
    public function send(SchoolSubscription $subscription, string $kind, string $periodKey, array $extra = []): bool
    {
        try {
            $log = BillingNotificationLog::query()->create([
                'school_id' => $subscription->school_id,
                'school_subscription_id' => $subscription->id,
                'kind' => $kind,
                'period_key' => $periodKey,
                'status' => 'queued',
            ]);
        } catch (UniqueConstraintViolationException) {
            return false; // already sent for this period
        }

        try {
            $school = $subscription->school ?? \App\Models\School::query()->find($subscription->school_id);
            $recipients = $this->recipients($school);
            $payload = $extra + $this->payload($subscription, $kind, $school);
            $notification = new SubscriptionLifecycleNotification($kind, $payload, $log->id);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, $notification);
            } elseif ($school->billing_email ?: $school->email) {
                Notification::route('mail', $school->billing_email ?: $school->email)->notify($notification);
                $recipients = collect([1]);
            } else {
                $log->forceFill(['status' => 'failed', 'error' => 'No recipients (no school administrator or billing e-mail).'])->save();

                return false;
            }

            $log->forceFill(['recipients' => $recipients->count()])->save();

            return true;
        } catch (Throwable $e) {
            $log->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
            report($e);

            return false;
        }
    }

    protected function recipients(\App\Models\School $school)
    {
        return $this->tenant->runForSchool($school, fn () => User::query()
            ->where('school_id', $school->id)
            ->permission('subscriptions.manage')
            ->whereNotNull('email_verified_at')
            ->get()
            ->unique('id')
            ->values());
    }

    /** @return array<string, mixed> */
    protected function payload(SchoolSubscription $subscription, string $kind, \App\Models\School $school): array
    {
        $lifecycle = app(SubscriptionLifecycle::class);
        $quote = $lifecycle->quote($subscription);
        $urls = app(SchoolDomainService::class)->urlsFor($school);

        return [
            'school_name' => $school->name,
            'school_type_label' => ucwords(str_replace('_', ' ', (string) $subscription->school_type)),
            'amount_label' => $quote['configured'] ? Money::formatCurrency($quote['total'], $quote['currency']) : null,
            'expiry_date' => $subscription->ends_at?->toFormattedDateString(),
            'days_left' => $lifecycle->daysRemaining($subscription),
            'payment_url' => preg_replace('#/admin/?$#', '/billing', $urls['admin_url']),
            'support_email' => config('schoolos.marketing.support_email'),
            'support_phone' => config('schoolos.marketing.phone'),
            'payment_methods' => ['Cash', 'Wave', 'Bank transfer', 'Card (Stripe)'],
        ];
    }
}