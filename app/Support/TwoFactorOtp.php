<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Short-lived 6-digit codes for email / SMS two-factor. The hashed code lives in cache only.
 */
final class TwoFactorOtp
{
    public const PURPOSE_SETUP = 'setup';

    public const PURPOSE_LOGIN = 'login';

    public static function expireMinutes(): int
    {
        return max(2, (int) config('schoolos.security.two_factor_otp_expire', 10));
    }

    public static function resendSeconds(): int
    {
        return max(15, (int) config('schoolos.security.two_factor_otp_resend', 45));
    }

    public static function smsAvailable(): bool
    {
        $state = app(SmsService::class)->providerState();

        if (! $state['configured']) {
            return false;
        }

        return app()->isProduction() ? $state['real'] : true;
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_substr($local, 0, 1);

        return ($keep !== '' ? $keep : '*').'***@'.$domain;
    }

    public static function maskPhone(?string $phone): ?string
    {
        $e164 = PhoneNumber::normalise($phone);
        if (! $e164) {
            return null;
        }

        $tail = substr($e164, -3);
        if (str_starts_with($e164, '+220')) {
            return '+220 ***'.$tail;
        }

        return '***'.$tail;
    }

    public static function sendWait(User $user, string $purpose = self::PURPOSE_SETUP): int
    {
        $key = self::sendKey($user, $purpose);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    /** @return array{ok:bool,error:?string} */
    public static function issue(User $user, string $channel, string $purpose, ?string $phone = null): array
    {
        $sendKey = self::sendKey($user, $purpose);
        if (RateLimiter::tooManyAttempts($sendKey, 1)) {
            return ['ok' => false, 'error' => 'err.2fa.throttled'];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = now()->addMinutes(self::expireMinutes());
        $cacheKey = self::cacheKey($user, $purpose);

        Cache::put($cacheKey, [
            'hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'channel' => $channel,
            'attempts' => 0,
        ], $ttl);

        if (app()->runningUnitTests()) {
            Cache::put($cacheKey.':plain', $code, $ttl);
        }

        if ($channel === 'email') {
            $user->notifyNow(new TwoFactorCodeNotification($code, self::expireMinutes()));
        } else {
            $e164 = PhoneNumber::normalise($phone ?: $user->phone);
            if (! $e164) {
                Cache::forget($cacheKey);

                return ['ok' => false, 'error' => 'err.2fa.invalidPhone'];
            }
            if (! self::smsAvailable()) {
                Cache::forget($cacheKey);

                return ['ok' => false, 'error' => 'err.2fa.unavailable'];
            }
            $body = 'Gambia SchoolOS code: '.$code.'. Expires in '.self::expireMinutes().' min.';
            $result = app(SmsService::class)->gateway()->send($e164, $body);
            if (! $result->accepted) {
                Cache::forget($cacheKey);

                return ['ok' => false, 'error' => 'err.2fa.smsFailed'];
            }
        }

        RateLimiter::hit($sendKey, self::resendSeconds());

        return ['ok' => true, 'error' => null];
    }

    public static function verify(User $user, string $purpose, string $code): bool
    {
        $key = self::cacheKey($user, $purpose);
        $payload = Cache::get($key);
        if (! is_array($payload) || empty($payload['hash'])) {
            return false;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= 5) {
            return false;
        }

        $payload['attempts'] = $attempts + 1;
        Cache::put($key, $payload, now()->addMinutes(self::expireMinutes()));

        $candidate = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($candidate) !== 6
            || ! hash_equals($payload['hash'], hash_hmac('sha256', $candidate, (string) config('app.key')))) {
            return false;
        }

        Cache::forget($key);
        Cache::forget($key.':plain');

        return true;
    }

    public static function codeForTests(User $user, string $purpose): ?string
    {
        return app()->runningUnitTests()
            ? Cache::get(self::cacheKey($user, $purpose).':plain')
            : null;
    }

    public static function forget(User $user, string $purpose): void
    {
        Cache::forget(self::cacheKey($user, $purpose));
        Cache::forget(self::cacheKey($user, $purpose).':plain');
    }

    private static function cacheKey(User $user, string $purpose): string
    {
        return '2fa-otp:'.$purpose.':'.$user->getKey();
    }

    private static function sendKey(User $user, string $purpose): string
    {
        return '2fa-otp-send:'.$purpose.':'.$user->getKey();
    }
}
