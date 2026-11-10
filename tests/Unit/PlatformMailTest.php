<?php

namespace Tests\Unit;

use App\Support\PlatformMail;
use Tests\TestCase;

class PlatformMailTest extends TestCase
{
    public function test_placeholder_from_address_becomes_the_platform_identity(): void
    {
        config([
            'mail.from.address' => 'hello@example.com',
            'mail.from.name' => 'Laravel',
            'schoolos.mail.from_address' => 'noreply@gambiaschoolos.com',
            'schoolos.mail.from_name' => 'Gambia SchoolOS',
            'mail.default' => 'array',
        ]);

        PlatformMail::bootstrap();

        $this->assertSame('noreply@gambiaschoolos.com', config('mail.from.address'));
        $this->assertSame('Gambia SchoolOS', config('mail.from.name'));
    }

    public function test_a_real_from_address_is_left_alone(): void
    {
        config([
            'mail.from.address' => 'office@brightfuture.gambiaschoolos.com',
            'mail.from.name' => 'Bright Future',
            'mail.default' => 'array',
        ]);

        PlatformMail::bootstrap();

        $this->assertSame('office@brightfuture.gambiaschoolos.com', config('mail.from.address'));
        $this->assertSame('Bright Future', config('mail.from.name'));
    }

    public function test_log_mailer_is_promoted_to_smtp_when_the_host_is_real(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'url' => null,
                'host' => 'smtp.gambiaschoolos.com',
                'port' => 2525,
                'username' => 'noreply@gambiaschoolos.com',
                'password' => 'from-env',
                'scheme' => null,
            ],
            'schoolos.mail.smtp.port' => 587,
            'schoolos.mail.smtp.encryption' => 'tls',
        ]);

        PlatformMail::bootstrap();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('from-env', config('mail.mailers.smtp.password'));
    }

    public function test_log_mailer_is_promoted_when_mail_url_is_set(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.url' => 'smtp://user:pass@smtp.sendgrid.net:587',
        ]);

        PlatformMail::bootstrap();

        $this->assertSame('smtp', config('mail.default'));
    }

    public function test_log_mailer_stays_when_the_host_is_local(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.url' => null,
        ]);

        PlatformMail::bootstrap();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_array_mailer_is_never_promoted(): void
    {
        config([
            'mail.default' => 'array',
            'mail.mailers.smtp.host' => 'smtp.gambiaschoolos.com',
            'mail.mailers.smtp.url' => null,
        ]);

        PlatformMail::bootstrap();

        $this->assertSame('array', config('mail.default'));
    }

    public function test_form_defaults_use_platform_identity_and_omit_dev_hosts(): void
    {
        config([
            'mail.from.address' => 'hello@example.com',
            'mail.from.name' => 'Laravel',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.username' => null,
            'schoolos.mail.from_address' => 'noreply@gambiaschoolos.com',
            'schoolos.mail.from_name' => 'Gambia SchoolOS',
            'schoolos.mail.smtp.port' => 587,
            'schoolos.mail.smtp.encryption' => 'tls',
        ]);

        $defaults = PlatformMail::formDefaults();

        $this->assertSame('', $defaults['host']);
        $this->assertSame(587, $defaults['port']);
        $this->assertSame('tls', $defaults['encryption']);
        $this->assertSame('', $defaults['username']);
        $this->assertSame('noreply@gambiaschoolos.com', $defaults['from_address']);
        $this->assertSame('Gambia SchoolOS', $defaults['from_name']);
        $this->assertArrayNotHasKey('password', $defaults);
    }

    public function test_smtp_to_localhost_is_not_treated_as_real_delivery(): void
    {
        $this->assertFalse(PlatformMail::mailerDelivers('smtp', ['host' => '127.0.0.1', 'url' => null]));
        $this->assertFalse(PlatformMail::mailerDelivers('log', ['host' => 'smtp.gambiaschoolos.com']));
        $this->assertTrue(PlatformMail::mailerDelivers('smtp', ['host' => 'smtp.gambiaschoolos.com']));
        $this->assertTrue(PlatformMail::mailerDelivers('resend'));
    }
}
