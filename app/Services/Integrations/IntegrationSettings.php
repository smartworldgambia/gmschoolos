<?php

namespace App\Services\Integrations;

use App\Exceptions\AcademicRuleException;
use App\Mail\IntegrationTestMail;
use App\Models\ActivityLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\IntegrationChangedNotification;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsService;
use App\Support\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Platform-level mail and SMS credentials, edited by a platform administrator instead of the server's `.env`.
 *
 * Safety rules (each one is covered by tests):
 *  - Secrets are encrypted with the application key before they reach the database and are NEVER returned to a browser:
 *    the screen only learns "set" and the last four characters of long secrets.
 *  - Saved credentials do nothing until they are ACTIVATED, and activation needs a successful test send of exactly
 *    these values (a fingerprint ties the test to the values). Changing a live value switches the integration back
 *    to the server default (`.env`) until it is tested and activated again, so a typo can never lock everyone out.
 *  - "Verified" is only ever set by a person confirming that the test message really arrived (inbox / handset).
 *  - Every change is written to the activity log without values, and the other platform administrators are e-mailed.
 *  - Nothing here is per school. Schools only decide whether to USE SMS and how much.
 */
class IntegrationSettings
{
    public const CACHE_KEY = 'schoolos.integrations.v1';

    /** integration => settings row key */
    public const KEYS = ['mail' => 'integrations.mail', 'sms' => 'integrations.sms'];

    /** field => [type, required, secret]. Types: string, int, bool, email, enum:a|b */
    public const FIELDS = [
        'mail' => [
            'host' => ['string', true, false],
            'port' => ['int', true, false],
            'encryption' => ['enum:tls|ssl', true, false],
            'username' => ['string', false, false],
            'password' => ['string', false, true],
            'from_address' => ['email', true, false],
            'from_name' => ['string', true, false],
        ],
        'sms' => [
            'driver' => ['enum:twilio|africastalking', true, false],
            'twilio_sid' => ['string', false, false],
            'twilio_token' => ['string', false, true],
            'twilio_from' => ['string', false, false],
            'twilio_messaging_service_sid' => ['string', false, false],
            'twilio_status_callback' => ['string', false, false],
            'at_username' => ['string', false, false],
            'at_api_key' => ['string', false, true],
            'at_sender_id' => ['string', false, false],
            'at_sandbox' => ['bool', false, false],
            'at_webhook_secret' => ['string', false, true],
        ],
    ];

    /** Fields that matter for a given SMS driver (the rest are kept but never change the fingerprint). */
    private const SMS_DRIVER_FIELDS = [
        'twilio' => ['twilio_sid', 'twilio_token', 'twilio_from', 'twilio_messaging_service_sid', 'twilio_status_callback'],
        'africastalking' => ['at_username', 'at_api_key', 'at_sender_id', 'at_sandbox', 'at_webhook_secret'],
    ];

    /** Registered as a singleton: one instance per application (and per test), never shared through statics. */
    private ?string $appliedHash = null;

    /** @var array<string, bool> integrations whose values THIS object has pushed into the config */
    private array $pushed = [];

    /** @var array<string, mixed> server defaults captured just before the first overlay */
    private array $baseline = [];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $rowsMemo = null;

    // ------------------------------------------------------------------ reading

    /** @return array<string, array<string, mixed>> raw stored rows (secrets still encrypted) */
    public function rows(): array
    {
        if ($this->rowsMemo !== null) {
            return $this->rowsMemo;
        }

        return $this->rowsMemo = Cache::rememberForever(self::CACHE_KEY, function () {
            $out = [];
            foreach (PlatformSetting::query()->whereIn('key', array_values(self::KEYS))->get() as $row) {
                $out[array_search($row->key, self::KEYS, true)] = (array) $row->value;
            }

            return $out;
        });
    }

    /** @return array<string, mixed> */
    public function stored(string $integration): array
    {
        return (array) ($this->rows()[$integration] ?? []);
    }

    /**
     * Decrypted values, or null when nothing is saved or the secrets cannot be decrypted (application key changed).
     *
     * @return array<string, mixed>|null
     */
    public function plain(string $integration): ?array
    {
        $fields = (array) ($this->stored($integration)['fields'] ?? []);
        if ($fields === []) {
            return null;
        }

        $out = [];
        foreach (self::FIELDS[$integration] as $name => [, , $secret]) {
            $value = $fields[$name] ?? null;
            if ($secret && filled($value)) {
                try {
                    $value = Crypt::decryptString((string) $value);
                } catch (DecryptException) {
                    return null;
                }
            }
            $out[$name] = $value;
        }

        return $out;
    }

    public function isActive(string $integration): bool
    {
        $stored = $this->stored($integration);
        $plain = $this->plain($integration);

        // Active only while the stored fingerprint still describes the stored values (defence against hand edits).
        return (bool) ($stored['enabled'] ?? false) && $plain !== null && hash_equals((string) ($stored['fingerprint'] ?? ''), $this->fingerprint($integration, $plain));
    }

    public function fingerprint(string $integration, array $plain): string
    {
        $relevant = $plain;
        if ($integration === 'sms') {
            $driver = (string) ($plain['driver'] ?? '');
            $keep = array_merge(['driver'], self::SMS_DRIVER_FIELDS[$driver] ?? []);
            $relevant = array_intersect_key($plain, array_flip($keep));
        }
        ksort($relevant);

        return hash_hmac('sha256', json_encode($relevant, JSON_UNESCAPED_UNICODE), (string) config('app.key'));
    }

    /**
     * What the screen may know. Never contains a secret value.
     *
     * @return array<string, mixed>
     */
    public function status(string $integration): array
    {
        $stored = $this->stored($integration);
        $plain = $this->plain($integration);
        $hasFields = (array) ($stored['fields'] ?? []) !== [];
        $fp = $plain ? $this->fingerprint($integration, $plain) : null;
        $tested = (array) ($stored['tested'] ?? []);
        $testedHere = $fp && ($tested['fingerprint'] ?? null) === $fp;
        $confirmed = (array) ($stored['confirmed'] ?? []);

        $values = [];
        $secretsSet = [];
        foreach (self::FIELDS[$integration] as $name => [$type, , $secret]) {
            if ($secret) {
                $raw = $plain[$name] ?? null;
                $secretsSet[$name] = filled($raw) ? ['set' => true, 'hint' => mb_strlen((string) $raw) >= 16 ? mb_substr((string) $raw, -4) : null] : ['set' => false, 'hint' => null];
            } else {
                $values[$name] = $type === 'bool' ? (bool) ($plain[$name] ?? false) : ($plain[$name] ?? ($name === 'encryption' ? 'tls' : ($name === 'port' ? 587 : ($name === 'driver' ? 'twilio' : ''))));
            }
        }

        $state = match (true) {
            ! $hasFields => 'none',
            $plain === null => 'unreadable',
            ! $testedHere => 'untested',
            ! ($tested['ok'] ?? false) => 'failed',
            default => 'tested',
        };

        return [
            'state' => $state,
            'active' => $this->isActive($integration),
            'verified' => $testedHere && ($tested['ok'] ?? false) && ($confirmed['fingerprint'] ?? null) === $fp,
            'values' => $values,
            'secrets' => $secretsSet,
            'tested_at' => $testedHere ? ($tested['at'] ?? null) : null,
            'tested_to' => $testedHere ? ($tested['to'] ?? null) : null,
            'test_error' => $testedHere && ! ($tested['ok'] ?? false) ? ($tested['error'] ?? null) : null,
            'confirmed_at' => ($confirmed['fingerprint'] ?? null) === $fp ? ($confirmed['at'] ?? null) : null,
            'updated_at' => $stored['updated_at'] ?? null,
            'updated_by' => $stored['updated_by_name'] ?? null,
        ];
    }

    /** What the server (`.env`) provides when nothing is active. No secret values. @return array<string, mixed> */
    public function serverDefault(string $integration): array
    {
        $base = [];
        foreach (self::OWNED[$integration] as $key) {
            $base[$key] = ($this->pushed[$integration] ?? false) ? ($this->baseline[$key] ?? null) : config($key);
        }

        if ($integration === 'mail') {
            $mailer = (string) ($base['mail.default'] ?? 'log');
            $smtp = (array) ($base['mail.mailers.smtp'] ?? []);

            return ['driver' => $mailer, 'real' => $mailer === 'smtp', 'host' => $mailer === 'smtp' ? ($smtp['host'] ?? null) : null, 'from' => $base['mail.from']['address'] ?? null];
        }

        $driver = (string) ($base['schoolos.sms.driver'] ?? 'log');

        return ['driver' => $driver, 'real' => in_array($driver, ['twilio', 'africastalking'], true), 'host' => null, 'from' => null];
    }

    // ------------------------------------------------------------------ writing

    /**
     * Save (never activates). A blank secret keeps the stored one.
     *
     * @param  array<string, mixed>  $input  already validated
     * @return array{changed: list<string>, deactivated: bool}
     */
    public function save(string $integration, array $input, User $actor): array
    {
        $before = $this->plain($integration) ?? [];
        $after = [];
        $changed = [];

        foreach (self::FIELDS[$integration] as $name => [$type, , $secret]) {
            $new = $input[$name] ?? null;
            if ($type === 'bool') {
                $new = (bool) $new;
            } elseif ($type === 'int') {
                $new = $new === null || $new === '' ? null : (int) $new;
            } else {
                $new = $new === null ? null : trim((string) $new);
                $new = $new === '' ? null : $new;
            }

            if ($secret && $new === null) {
                $new = $before[$name] ?? null;           // keep the stored secret
            }

            $after[$name] = $new;
            if (($before[$name] ?? null) !== $new) {
                $changed[] = $name;
            }
        }

        $stored = $this->stored($integration);
        $enc = [];
        foreach ($after as $name => $value) {
            $enc[$name] = (self::FIELDS[$integration][$name][2] ?? false) && filled($value) ? Crypt::encryptString((string) $value) : $value;
        }

        $fp = $this->fingerprint($integration, $after);
        $wasActive = $this->isActive($integration);
        $valuesChanged = ! hash_equals($this->fingerprintOrEmpty($integration, $before), $fp);
        $deactivated = $wasActive && $valuesChanged;

        $this->write($integration, [
            'fields' => $enc,
            'fingerprint' => $fp,
            'enabled' => $deactivated ? false : (bool) ($stored['enabled'] ?? false) && ! $valuesChanged,
            'tested' => $stored['tested'] ?? null,
            'confirmed' => $stored['confirmed'] ?? null,
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $actor->id,
            'updated_by_name' => $actor->name,
        ]);

        $this->audit($actor, 'saved', $integration, ['fields' => $changed, 'deactivated' => $deactivated]);
        $this->notifyOthers($actor, 'saved', $integration);

        return ['changed' => $changed, 'deactivated' => $deactivated];
    }

    public function activate(string $integration, User $actor): void
    {
        $plain = $this->plain($integration);
        if ($plain === null) {
            throw AcademicRuleException::because('err.integ.nothingSaved');
        }

        $tested = (array) ($this->stored($integration)['tested'] ?? []);
        if (! ($tested['ok'] ?? false) || ! hash_equals((string) ($tested['fingerprint'] ?? ''), $this->fingerprint($integration, $plain))) {
            throw AcademicRuleException::because('err.integ.testFirst');
        }

        $this->patch($integration, ['enabled' => true]);
        $this->apply();
        $this->audit($actor, 'activated', $integration, []);
        $this->notifyOthers($actor, 'activated', $integration);
    }

    public function deactivate(string $integration, User $actor): void
    {
        $this->patch($integration, ['enabled' => false]);
        $this->apply();
        $this->audit($actor, 'deactivated', $integration, []);
        $this->notifyOthers($actor, 'deactivated', $integration);
    }

    /** Remove everything saved for this integration. The server default takes over. */
    public function clear(string $integration, User $actor): void
    {
        PlatformSetting::query()->where('key', self::KEYS[$integration])->delete();
        $this->forget();
        $this->apply();
        $this->audit($actor, 'cleared', $integration, []);
        $this->notifyOthers($actor, 'cleared', $integration);
    }

    /** A person confirms that the test message really arrived. This is the only way to "verified". */
    public function confirm(string $integration, User $actor): void
    {
        $plain = $this->plain($integration);
        $tested = (array) ($this->stored($integration)['tested'] ?? []);
        $fp = $plain ? $this->fingerprint($integration, $plain) : null;

        if (! $fp || ! ($tested['ok'] ?? false) || ! hash_equals((string) ($tested['fingerprint'] ?? ''), $fp)) {
            throw AcademicRuleException::because('err.integ.testFirst');
        }

        $this->patch($integration, ['confirmed' => ['at' => now()->toIso8601String(), 'fingerprint' => $fp, 'by' => $actor->id]]);
        $this->audit($actor, 'confirmed', $integration, []);
    }

    // ------------------------------------------------------------------ testing

    /**
     * Send one real message with the SAVED values (active or not) and remember the outcome. Never throws for a provider
     * failure: the failure is the result. The reason is stored with every secret removed.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function test(string $integration, string $target, User $actor): array
    {
        $plain = $this->plain($integration);
        if ($plain === null) {
            throw AcademicRuleException::because('err.integ.nothingSaved');
        }

        $error = null;
        $masked = $integration === 'mail' ? $this->maskEmail($target) : $this->maskPhone($target);

        try {
            $this->withTemporary($integration, $plain, function () use ($integration, $target, $plain) {
                $integration === 'mail' ? $this->sendTestMail($target) : $this->sendTestSms($target, (string) $plain['driver']);
            });
        } catch (Throwable $e) {
            $error = $this->redact($e->getMessage() ?: class_basename($e), $plain);
        }

        $ok = $error === null;
        $this->patch($integration, ['tested' => [
            'at' => now()->toIso8601String(), 'ok' => $ok, 'error' => $error, 'to' => $masked,
            'fingerprint' => $this->fingerprint($integration, $plain), 'by' => $actor->id,
        ], 'confirmed' => null]);
        $this->audit($actor, $ok ? 'test_ok' : 'test_failed', $integration, ['to' => $masked]);

        return ['ok' => $ok, 'error' => $error];
    }

    private function sendTestMail(string $to): void
    {
        $manager = app('mail.manager');
        if (method_exists($manager, 'purge')) {
            $manager->purge('smtp');
        }

        try {
            Mail::mailer('smtp')->to($to)->send(new IntegrationTestMail);
        } finally {
            if (method_exists($manager, 'purge')) {
                $manager->purge('smtp');
            }
        }
    }

    private function sendTestSms(string $to, string $driver): void
    {
        $e164 = PhoneNumber::normalise($to);
        if ($e164 === null) {
            throw new \RuntimeException('invalid_phone');
        }

        $gateway = app(SmsService::class)->gateway();
        if (! $gateway->configured()) {
            throw new \RuntimeException($driver.'_not_configured');
        }

        $result = $gateway->send($e164, 'SchoolOS test message: SMS sending from the platform console works. No action needed.');
        if (! $result->accepted) {
            throw new \RuntimeException((string) ($result->error ?: 'rejected_by_provider'));
        }
    }

    // ------------------------------------------------------------------ applying to the running app

    /**
     * Push ACTIVE values into the framework config. Safe to call often; a no-op when nothing is active, and it never
     * throws (before the first migration there is no table yet).
     */
    public function apply(): void
    {
        try {
            $this->rowsMemo = null;
            $applied = [];

            foreach (array_keys(self::KEYS) as $integration) {
                if ($this->isActive($integration)) {
                    if (! ($this->pushed[$integration] ?? false)) {
                        $this->captureBaseline($integration);     // the server default, taken just before the first overlay
                    }
                    $plain = (array) $this->plain($integration);
                    $this->push($integration, $plain);
                    $this->pushed[$integration] = true;
                    $applied[$integration] = $this->fingerprint($integration, $plain);
                } elseif ($this->pushed[$integration] ?? false) {
                    // it was switched off (or cleared): put the server default back, but only what we ourselves changed
                    foreach (self::OWNED[$integration] as $key) {
                        config([$key => $this->baseline[$key] ?? null]);
                    }
                    $this->pushed[$integration] = false;
                }
            }

            $hash = md5((string) json_encode($applied));
            if ($this->appliedHash !== null && $this->appliedHash !== $hash) {
                $this->purgeMailers();         // a long-running queue worker must not keep using the old transport
            }
            $this->appliedHash = $hash;
        } catch (Throwable) {
            // no table / no cache yet: the server defaults stay in force
        }
    }

    /** @param 'mail'|'sms' $integration */
    private function captureBaseline(string $integration): void
    {
        foreach (self::OWNED[$integration] as $key) {
            $this->baseline[$key] = config($key);
        }
    }

    /** @param array<string, mixed> $plain */
    private function push(string $integration, array $plain): void
    {
        if ($integration === 'mail') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => array_merge((array) config('mail.mailers.smtp'), [
                    'transport' => 'smtp', 'url' => null, 'host' => $plain['host'], 'port' => (int) $plain['port'],
                    'username' => $plain['username'], 'password' => $plain['password'],
                    'scheme' => ($plain['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
                ]),
                'mail.from' => ['address' => $plain['from_address'], 'name' => $plain['from_name']],
            ]);

            return;
        }

        config([
            'schoolos.sms.driver' => $plain['driver'],
            'schoolos.sms.twilio' => [
                'sid' => $plain['twilio_sid'], 'token' => $plain['twilio_token'], 'from' => $plain['twilio_from'],
                'messaging_service_sid' => $plain['twilio_messaging_service_sid'], 'status_callback' => $plain['twilio_status_callback'],
            ],
            'schoolos.sms.africastalking' => [
                'username' => $plain['at_username'], 'api_key' => $plain['at_api_key'], 'from' => $plain['at_sender_id'],
                'sandbox' => (bool) $plain['at_sandbox'], 'webhook_secret' => $plain['at_webhook_secret'],
            ],
        ]);
    }

    /** Config keys this integration owns, so the server default can be put back. */
    private const OWNED = [
        'mail' => ['mail.default', 'mail.mailers.smtp', 'mail.from'],
        'sms' => ['schoolos.sms.driver', 'schoolos.sms.twilio', 'schoolos.sms.africastalking'],
    ];

    /** @param array<string, mixed> $plain */
    private function withTemporary(string $integration, array $plain, callable $fn): void
    {
        $snapshot = [];
        foreach (self::OWNED[$integration] as $key) {
            $snapshot[$key] = config($key);
        }

        try {
            $this->push($integration, $plain);
            $fn();
        } finally {
            foreach ($snapshot as $key => $value) {
                config([$key => $value]);
            }
        }
    }

    private function purgeMailers(): void
    {
        $manager = app('mail.manager');
        if (method_exists($manager, 'purge')) {
            $manager->purge();
        }
    }

    // ------------------------------------------------------------------ plumbing

    /** @param array<string, mixed> $row */
    private function write(string $integration, array $row): void
    {
        PlatformSetting::putValue(self::KEYS[$integration], $row);
        $this->forget();
        $this->apply();
    }

    /** @param array<string, mixed> $changes */
    private function patch(string $integration, array $changes): void
    {
        $row = array_merge($this->stored($integration), $changes);
        PlatformSetting::putValue(self::KEYS[$integration], $row);
        $this->forget();
    }

    private function forget(): void
    {
        $this->rowsMemo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @param array<string, mixed> $plain */
    private function fingerprintOrEmpty(string $integration, array $plain): string
    {
        return $plain === [] ? '' : $this->fingerprint($integration, $plain);
    }

    /** @param array<string, mixed> $props */
    private function audit(User $actor, string $event, string $integration, array $props): void
    {
        app(TenantContext::class)->runAsPlatform(fn () => ActivityLog::query()->create([
            'school_id' => null, 'user_id' => $actor->id, 'event' => 'platform.integration.'.$event,
            'description' => $integration, 'properties' => ['integration' => $integration] + $props,
        ]));
    }

    private function notifyOthers(User $actor, string $what, string $integration): void
    {
        try {
            $others = app(TenantContext::class)->runAsPlatform(fn () => User::query()
                ->whereNull('school_id')->whereNull('suspended_at')->whereNull('access_revoked_at')
                ->where('id', '!=', $actor->id)->permission('platform.integrations.configure')->get());

            if ($others->isNotEmpty()) {
                Notification::send($others, new IntegrationChangedNotification($integration, $what, $actor->name));
            }
        } catch (Throwable) {
            // the notice is a safeguard, never a reason to fail the change itself
        }
    }

    /** Remove every secret (and the account names) from a provider/transport error before it is stored or shown. */
    private function redact(string $message, array $plain): string
    {
        foreach ($plain as $name => $value) {
            if (is_string($value) && mb_strlen($value) >= 4 && ! in_array($name, ['encryption', 'driver', 'port'], true)) {
                $message = str_ireplace($value, '***', $message);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $message)), 0, 300);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return '***'.substr($digits, -3);
    }
}
