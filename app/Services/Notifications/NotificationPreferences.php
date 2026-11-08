<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\Communications\CommunicationSettings;
use App\Services\Sms\PhoneNumber;
use App\Support\TenantContext;

/**
 * Decides which channels a notification really uses for one person.
 *
 *   in-app ("database") : always, unless the person turned the category off (never for mandatory categories)
 *   e-mail ("mail")     : needs a verified address; the person may turn it off per category (never for mandatory ones)
 *   SMS                 : only when the school has SMS on, the category allows it, the person has a usable phone number
 *                         and has not opted out. The starting preference per category is `sms_default`.
 */
class NotificationPreferences
{
    public const CHANNELS = ['in_app', 'email', 'sms'];

    /** @return array<string,array{mandatory:bool,sms:bool,sms_default:bool}> */
    public function categories(): array
    {
        return (array) config('schoolos.notifications.categories', []);
    }

    public function isMandatory(string $category): bool
    {
        return (bool) ($this->categories()[$category]['mandatory'] ?? false);
    }

    public function enabled(User $user, string $category, string $channel): bool
    {
        $meta = $this->categories()[$category] ?? null;
        if ($meta === null) {
            return $channel !== 'sms';
        }

        if ($meta['mandatory'] && $channel !== 'sms') {
            return true;
        }

        if ($channel === 'sms' && ! $meta['sms']) {
            return false;
        }

        $stored = $this->stored($user)[$category.'|'.$channel] ?? null;

        if ($stored !== null) {
            return $stored;
        }

        return $channel === 'sms' ? (bool) $meta['sms_default'] : true;
    }

    /**
     * Laravel channel names for this notification and person.
     *
     * @param  array<int,string>  $offered  which of in_app / email / sms the notification can use
     * @return array<int,string>
     */
    public function channels(object $notifiable, string $category, array $offered = self::CHANNELS): array
    {
        if (! $notifiable instanceof User) {
            return in_array('email', $offered, true) ? ['mail'] : [];   // on-demand (invitation) notifications
        }

        $channels = [];

        if (in_array('in_app', $offered, true) && $this->enabled($notifiable, $category, 'in_app')) {
            $channels[] = 'database';
        }

        if (in_array('email', $offered, true) && filled($notifiable->email) && $notifiable->email_verified_at !== null
            && $this->enabled($notifiable, $category, 'email')) {
            $channels[] = 'mail';
        }

        if (in_array('sms', $offered, true) && $this->smsPossible($notifiable) && $this->enabled($notifiable, $category, 'sms')) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function smsPossible(User $user): bool
    {
        return $user->school !== null
            && CommunicationSettings::smsEnabled($user->school)
            && PhoneNumber::normalise($user->phone) !== null;
    }

    /**
     * What the preferences screen shows: every category with the current value per channel.
     *
     * @return array<int,array<string,mixed>>
     */
    public function matrix(User $user): array
    {
        $rows = [];
        foreach ($this->categories() as $key => $meta) {
            $rows[] = [
                'category' => $key,
                'mandatory' => (bool) $meta['mandatory'],
                'in_app' => $this->enabled($user, $key, 'in_app'),
                'email' => $this->enabled($user, $key, 'email'),
                'sms' => $this->enabled($user, $key, 'sms'),
                'sms_available' => (bool) $meta['sms'],
            ];
        }

        return $rows;
    }

    /**
     * Save the screen. Unknown categories and channels are ignored; mandatory in-app / e-mail cannot be switched off.
     *
     * @param  array<string,array<string,mixed>>  $input  category => [in_app, email, sms] booleans
     */
    public function save(User $user, array $input): void
    {
        app(TenantContext::class)->runForSchool($user->school, function () use ($user, $input) {
            foreach ($this->categories() as $category => $meta) {
                foreach (self::CHANNELS as $channel) {
                    if (! isset($input[$category]) || ! array_key_exists($channel, (array) $input[$category])) {
                        continue;
                    }

                    if (($meta['mandatory'] && $channel !== 'sms') || ($channel === 'sms' && ! $meta['sms'])) {
                        continue;
                    }

                    NotificationPreference::query()->updateOrCreate(
                        ['user_id' => $user->id, 'category' => $category, 'channel' => $channel],
                        ['school_id' => $user->school_id, 'enabled' => filter_var($input[$category][$channel], FILTER_VALIDATE_BOOLEAN)],
                    );
                }
            }
        });
        $this->forget($user);
    }

    /** @var array<int,array<string,bool>> */
    private array $cache = [];

    /** @return array<string,bool> "category|channel" => enabled */
    private function stored(User $user): array
    {
        return $this->cache[$user->id] ??= app(TenantContext::class)->runAsPlatform(
            fn () => NotificationPreference::query()->where('user_id', $user->id)->get()
                ->mapWithKeys(fn ($p) => [$p->category.'|'.$p->channel => (bool) $p->enabled])->all()
        );
    }

    public function forget(User $user): void
    {
        unset($this->cache[$user->id]);
    }
}
