<?php

namespace App\Support;

/**
 * Runtime mail identity and a safe promotion of the log driver when the server already
 * has a real SMTP host or MAIL_URL. Does not invent passwords; local Mailpit stays on log.
 */
final class PlatformMail
{
    /** @var list<string> */
    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1', '0.0.0.0', 'mailpit', 'mailhog'];

    /** @var list<int> */
    private const DEV_SMTP_PORTS = [1025, 2525];

    /** @var list<string> */
    private const SILENT_MAILERS = ['log', 'null', ''];

    /** @var list<string> */
    private const TEST_MAILERS = ['array'];

    public static function fromAddress(): string
    {
        $address = strtolower(trim((string) config('schoolos.mail.from_address')));

        return $address !== '' ? $address : 'noreply@gambiaschoolos.com';
    }

    public static function fromName(): string
    {
        $name = trim((string) config('schoolos.mail.from_name'));

        return $name !== '' ? $name : (string) config('app.name', 'Gambia SchoolOS');
    }

    /**
     * Replace Laravel placeholder From headers, and if the default mailer is still `log`
     * while MAIL_HOST / MAIL_URL look deliverable, switch to smtp (typical Laravel Cloud copy
     * of .env.example). Console Integrations overlay still wins when it is active.
     */
    public static function bootstrap(): void
    {
        self::applyIdentity();
        self::promoteEnvSmtpIfIdle();
    }

    public static function applyIdentity(): void
    {
        $from = config('mail.from', []);
        $address = is_array($from) ? ($from['address'] ?? null) : null;
        $name = is_array($from) ? ($from['name'] ?? null) : null;

        if (self::isPlaceholderFrom(is_string($address) ? $address : null)) {
            config(['mail.from.address' => self::fromAddress()]);
        }

        if (self::isPlaceholderName(is_string($name) ? $name : null)) {
            config(['mail.from.name' => self::fromName()]);
        }
    }

    public static function promoteEnvSmtpIfIdle(): void
    {
        $mailer = strtolower(trim((string) config('mail.default')));
        if (in_array($mailer, self::TEST_MAILERS, true) || ! in_array($mailer, self::SILENT_MAILERS, true)) {
            return;
        }

        if (! self::envLooksDeliverable()) {
            return;
        }

        $smtp = (array) config('mail.mailers.smtp', []);
        $port = (int) ($smtp['port'] ?? 0);
        if ($port < 1 || in_array($port, self::DEV_SMTP_PORTS, true)) {
            $smtp['port'] = self::defaultSmtpPort();
        }

        $scheme = strtolower(trim((string) ($smtp['scheme'] ?? '')));
        if ($scheme === '' || $scheme === 'null') {
            $smtp['scheme'] = self::defaultSmtpEncryption() === 'ssl' ? 'smtps' : 'smtp';
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => $smtp,
        ]);
    }

    public static function envLooksDeliverable(): bool
    {
        $smtp = (array) config('mail.mailers.smtp', []);

        if (filled($smtp['url'] ?? null)) {
            return true;
        }

        return self::usableHost($smtp['host'] ?? null) !== null;
    }

    public static function mailerDelivers(?string $mailer, ?array $smtp = null): bool
    {
        $mailer = strtolower(trim((string) $mailer));
        if (in_array($mailer, [...self::SILENT_MAILERS, ...self::TEST_MAILERS], true)) {
            return false;
        }

        if ($mailer !== 'smtp') {
            return true;
        }

        $smtp ??= (array) config('mail.mailers.smtp', []);

        return filled($smtp['url'] ?? null) || self::usableHost($smtp['host'] ?? null) !== null;
    }

    public static function usableHost(mixed $host): ?string
    {
        $host = strtolower(trim((string) $host));
        if ($host === '' || in_array($host, self::LOCAL_HOSTS, true)) {
            return null;
        }

        return $host;
    }

    /**
     * Empty Integrations form values. Never includes a password.
     *
     * @return array<string, mixed>
     */
    public static function formDefaults(): array
    {
        $smtp = (array) config('mail.mailers.smtp', []);
        $port = (int) ($smtp['port'] ?? 0);
        if ($port < 1 || in_array($port, self::DEV_SMTP_PORTS, true)) {
            $port = self::defaultSmtpPort();
        }

        $scheme = strtolower(trim((string) ($smtp['scheme'] ?? '')));
        $encryption = $scheme === 'smtps' || $scheme === 'ssl' ? 'ssl' : self::defaultSmtpEncryption();

        $username = trim((string) ($smtp['username'] ?? ''));
        if ($username === '' || strcasecmp($username, 'null') === 0) {
            $username = '';
        }

        $from = (array) config('mail.from', []);
        $fromAddress = (string) ($from['address'] ?? '');
        $fromName = (string) ($from['name'] ?? '');

        return [
            'host' => self::usableHost($smtp['host'] ?? null) ?? '',
            'port' => $port,
            'encryption' => in_array($encryption, ['tls', 'ssl'], true) ? $encryption : 'tls',
            'username' => $username,
            'from_address' => self::isPlaceholderFrom($fromAddress) ? self::fromAddress() : ($fromAddress !== '' ? $fromAddress : self::fromAddress()),
            'from_name' => self::isPlaceholderName($fromName) ? self::fromName() : ($fromName !== '' ? $fromName : self::fromName()),
        ];
    }

    public static function isPlaceholderFrom(?string $address): bool
    {
        $address = strtolower(trim((string) $address));

        return $address === '' || $address === 'hello@example.com' || str_ends_with($address, '@example.com');
    }

    public static function isPlaceholderName(?string $name): bool
    {
        $name = trim((string) $name);

        return $name === '' || strcasecmp($name, 'Laravel') === 0;
    }

    private static function defaultSmtpPort(): int
    {
        $port = (int) config('schoolos.mail.smtp.port', 587);

        return $port > 0 ? $port : 587;
    }

    private static function defaultSmtpEncryption(): string
    {
        $encryption = strtolower(trim((string) config('schoolos.mail.smtp.encryption', 'tls')));

        return $encryption === 'ssl' ? 'ssl' : 'tls';
    }
}
