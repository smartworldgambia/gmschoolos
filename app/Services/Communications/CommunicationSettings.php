<?php

namespace App\Services\Communications;

use App\Models\School;

/**
 * Per-school communication switches, stored in `schools.settings.communications`.
 *
 *  - sms_enabled   : SMS is off until the school turns it on (and the platform has a working provider)
 *  - sms_sender    : optional alphanumeric sender id (provider rules apply; many networks ignore or rewrite it)
 *  - sms_daily_cap : hard stop on messages per school per day, so a mistake cannot become a large bill
 *  - parent_messaging / student_replies : may guardians / learners use internal messaging at all
 */
class CommunicationSettings
{
    public static function smsEnabled(School $school): bool
    {
        return (bool) data_get($school->settings, 'communications.sms_enabled', false);
    }

    public static function smsSender(School $school): ?string
    {
        $v = data_get($school->settings, 'communications.sms_sender');

        return filled($v) ? (string) $v : null;
    }

    public static function dailyCap(School $school): int
    {
        return max(0, (int) data_get($school->settings, 'communications.sms_daily_cap', config('schoolos.sms.default_daily_cap', 500)));
    }

    public static function parentMessaging(School $school): bool
    {
        return (bool) data_get($school->settings, 'communications.parent_messaging', true)
            && (bool) data_get($school->settings, 'parent_access.enabled', true);
    }

    public static function studentMessaging(School $school): bool
    {
        return (bool) data_get($school->settings, 'communications.student_messaging', true);
    }

    /** @param  array<string,mixed>  $values */
    public static function save(School $school, array $values): void
    {
        $settings = (array) $school->settings;
        $current = (array) ($settings['communications'] ?? []);

        foreach (['sms_enabled', 'sms_sender', 'sms_daily_cap', 'parent_messaging', 'student_messaging'] as $key) {
            if (array_key_exists($key, $values)) {
                $current[$key] = $values[$key];
            }
        }

        $settings['communications'] = $current;
        $school->forceFill(['settings' => $settings])->save();
    }

    /** @return array<string,mixed> */
    public static function all(School $school): array
    {
        return [
            'sms_enabled' => self::smsEnabled($school),
            'sms_sender' => self::smsSender($school),
            'sms_daily_cap' => self::dailyCap($school),
            'parent_messaging' => (bool) data_get($school->settings, 'communications.parent_messaging', true),
            'student_messaging' => self::studentMessaging($school),
        ];
    }
}
