<?php

namespace Tests\Feature;

use App\Mail\IntegrationTestMail;
use App\Models\ActivityLog;
use App\Models\PlatformSetting;
use App\Models\School;
use App\Models\User;
use App\Notifications\IntegrationChangedNotification;
use App\Services\Integrations\IntegrationSettings;
use App\Services\Sms\SmsService;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Platform console: mail and SMS credentials.
 *
 * What this proves: where the secrets live, who may touch them, that nothing goes live untested, that nothing is ever
 * shown back, and that every change leaves a trail. It does NOT prove a real inbox or handset received anything: that is
 * a person's confirmation on the real provider, recorded separately ("verified").
 */
class IntegrationsConsoleTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'smtp-super-secret-password-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        config(['mail.default' => 'array', 'schoolos.sms.driver' => 'log']);
    }

    private function admin(string $role = 'platform_admin'): User
    {
        $u = User::factory()->create(['school_id' => null, 'email_verified_at' => now()]);
        $u->assignRole($role);

        return $u;
    }

    private function as(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user);
    }

    private function mailBody(array $over = []): array
    {
        return $over + [
            'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'apikey', 'password' => self::SECRET,
            'from_address' => 'no-reply@schoolos.test', 'from_name' => 'SchoolOS', 'current_password' => 'password',
        ];
    }

    private function twilioBody(array $over = []): array
    {
        return $over + [
            'driver' => 'twilio', 'twilio_sid' => 'AC1234567890abcdef', 'twilio_token' => 'twilio-auth-token-abcdef123456', 'twilio_from' => '+15005550006',
            'current_password' => 'password',
        ];
    }

    private function svc(): IntegrationSettings
    {
        return app(IntegrationSettings::class);
    }

    private function raw(string $integration): string
    {
        return (string) json_encode(PlatformSetting::query()->where('key', IntegrationSettings::KEYS[$integration])->firstOrFail()->value);
    }

    private function tested(User $a, string $integration = 'mail', ?string $target = null): void
    {
        $this->as($a)->post(route('platform.integrations.test', $integration), ['target' => $target ?? ($integration === 'mail' ? 'me@example.test' : '+2207000000')])->assertSessionHasNoErrors();
    }

    // ================================================================== who may touch it

    public function test_only_platform_administrators_may_open_or_change_credentials(): void
    {
        foreach (['platform_finance', 'platform_support', 'platform_operations', 'platform_auditor'] as $role) {
            $u = $this->admin($role);
            $this->as($u)->get(route('platform.integrations.index'))->assertForbidden();
            $this->as($u)->put(route('platform.integrations.save', 'mail'), $this->mailBody())->assertForbidden();
            $this->as($u)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password'])->assertForbidden();
        }

        $school = School::query()->create(['name' => 'S', 'slug' => 's', 'status' => 'active']);
        $head = User::factory()->create(['school_id' => $school->id, 'email_verified_at' => now()]);
        $head->assignRole('school_admin');
        $this->as($head)->get(route('platform.integrations.index'))->assertForbidden();
        $this->as($head)->put(route('platform.integrations.save', 'mail'), $this->mailBody())->assertForbidden();

        $this->as($this->admin())->get(route('platform.integrations.index'))->assertOk();
        $this->as($this->admin('super_admin'))->get(route('platform.integrations.index'))->assertOk();
        $this->assertNull(PlatformSetting::query()->where('key', 'integrations.mail')->first());
    }

    public function test_navigation_entry_is_only_offered_to_those_who_may_use_it(): void
    {
        $this->as($this->admin())->get(route('platform.integrations.index'))
            ->assertInertia(fn ($p) => $p->where('nav', fn ($nav) => collect($nav)->contains('key', 'integrations')));

        $this->as($this->admin('platform_finance'))->get(route('platform.payments.settings'))
            ->assertInertia(fn ($p) => $p->where('nav', fn ($nav) => ! collect($nav)->contains('key', 'integrations')));
    }

    // ================================================================== secrets

    public function test_changes_need_the_administrators_own_password(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['current_password' => 'wrong']))->assertSessionHasErrors('current_password');
        $this->as($a)->put(route('platform.integrations.save', 'mail'), array_diff_key($this->mailBody(), ['current_password' => 1]))->assertSessionHasErrors('current_password');
        $this->assertNull(PlatformSetting::query()->where('key', 'integrations.mail')->first());

        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody())->assertSessionHasNoErrors();
        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'nope'])->assertSessionHasErrors('current_password');
        $this->as($a)->post(route('platform.integrations.deactivate', 'mail'), [])->assertSessionHasErrors('current_password');
        $this->as($a)->delete(route('platform.integrations.destroy', 'mail'), [])->assertSessionHasErrors('current_password');
        $this->assertNotNull(PlatformSetting::query()->where('key', 'integrations.mail')->first());
    }

    public function test_secrets_are_encrypted_at_rest_and_never_sent_back_to_the_browser(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody())->assertSessionHasNoErrors();

        $this->assertStringNotContainsString(self::SECRET, $this->raw('mail'));
        $this->assertStringContainsString('smtp.example.test', $this->raw('mail'));                // not a secret, readable

        $response = $this->as($a)->get(route('platform.integrations.index'))->assertOk();
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
        $response->assertInertia(fn ($p) => $p
            ->where('items.mail.secrets.password.set', true)
            ->where('items.mail.secrets.password.hint', substr(self::SECRET, -4))                 // long secrets show their last four
            ->where('items.mail.values.host', 'smtp.example.test')
            ->missing('items.mail.values.password'));
    }

    public function test_short_secrets_show_no_hint_at_all(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['password' => 'short-pw']));

        $this->as($a)->get(route('platform.integrations.index'))
            ->assertInertia(fn ($p) => $p->where('items.mail.secrets.password.set', true)->where('items.mail.secrets.password.hint', null));
    }

    public function test_a_blank_secret_keeps_the_stored_one(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $before = $this->svc()->plain('mail')['password'];

        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['password' => '', 'from_name' => 'Other name']))->assertSessionHasNoErrors();

        $this->assertSame(self::SECRET, $before);
        $this->assertSame(self::SECRET, $this->svc()->plain('mail')['password']);
        $this->assertSame('Other name', $this->svc()->plain('mail')['from_name']);
    }

    public function test_unreadable_secrets_are_reported_and_never_activate(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $row = PlatformSetting::query()->where('key', 'integrations.mail')->firstOrFail();
        $value = $row->value;
        $value['fields']['password'] = 'eyJpdiI6ImJhZCJ9';           // not decryptable with this application key
        $value['enabled'] = true;
        $row->update(['value' => $value]);
        Cache::forget(IntegrationSettings::CACHE_KEY);
        $this->svc()->apply();

        $this->as($a)->get(route('platform.integrations.index'))->assertInertia(fn ($p) => $p->where('items.mail.state', 'unreadable')->where('items.mail.active', false));
    }

    public function test_a_hand_edited_row_cannot_switch_itself_on(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $row = PlatformSetting::query()->where('key', 'integrations.mail')->firstOrFail();
        $value = $row->value;
        $value['enabled'] = true;                                     // flipped in the database without a test
        $value['fields']['host'] = 'evil.example.test';              // and the host swapped: the fingerprint no longer matches
        $row->update(['value' => $value]);
        Cache::forget(IntegrationSettings::CACHE_KEY);

        $this->svc()->apply();

        $this->assertFalse($this->svc()->isActive('mail'));
        $this->assertSame('array', config('mail.default'));
    }

    // ================================================================== nothing goes live untested

    public function test_saved_credentials_do_nothing_until_tested_and_activated(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody())->assertSessionHasNoErrors();

        $this->assertSame('array', config('mail.default'));
        $this->assertSame('untested', $this->svc()->status('mail')['state']);

        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password'])->assertSessionHas('error', 'err.integ.testFirst');
        $this->assertFalse($this->svc()->isActive('mail'));
        $this->assertSame('array', config('mail.default'));
        $this->as($a)->post(route('platform.integrations.confirm', 'mail'))->assertSessionHas('error', 'err.integ.testFirst');
    }

    public function test_a_successful_test_sends_one_message_and_allows_activation(): void
    {
        Mail::fake();
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());

        $this->as($a)->post(route('platform.integrations.test', 'mail'), ['target' => 'me@example.test'])->assertSessionHas('success', 'ok.integ.testSentMail');
        Mail::assertSent(IntegrationTestMail::class, 1);
        Mail::assertSent(IntegrationTestMail::class, fn ($m) => $m->hasTo('me@example.test'));
        $this->assertSame('array', config('mail.default'), 'a test must not switch anything on');

        $status = $this->svc()->status('mail');
        $this->assertSame('tested', $status['state']);
        $this->assertSame('m***@example.test', $status['tested_to']);       // only a masked address is kept

        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password'])->assertSessionHas('success', 'ok.integ.activated');

        $this->assertTrue($this->svc()->isActive('mail'));
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame(self::SECRET, config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@schoolos.test', config('mail.from.address'));
        $this->assertNull(config('mail.mailers.smtp.url'));
        $this->assertFalse($this->svc()->status('mail')['verified'], 'sent is not the same as received');
    }

    public function test_a_failed_test_is_recorded_without_secrets_and_blocks_activation(): void
    {
        $a = $this->admin();
        // Nothing listens on port 1, so the connection is refused straight away.
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['host' => '127.0.0.1', 'port' => 1, 'username' => 'u-secret-name', 'password' => 'pw-secret-value']));

        $this->as($a)->post(route('platform.integrations.test', 'mail'), ['target' => 'me@example.test'])->assertSessionHas('error', 'err.integ.testFailed');

        $status = $this->svc()->status('mail');
        $this->assertSame('failed', $status['state']);
        $this->assertNotEmpty($status['test_error']);
        $this->assertStringNotContainsString('pw-secret-value', (string) $status['test_error']);
        $this->assertStringNotContainsString('u-secret-name', (string) $status['test_error']);
        $this->assertLessThanOrEqual(300, mb_strlen((string) $status['test_error']));
        $this->assertSame('array', config('mail.default'));

        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password'])->assertSessionHas('error', 'err.integ.testFirst');
        $this->assertFalse($this->svc()->isActive('mail'));
    }

    public function test_changing_a_live_value_switches_back_to_the_server_default_until_retested(): void
    {
        Mail::fake();
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $this->tested($a);
        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password']);
        $this->assertSame('smtp', config('mail.default'));

        // an edit that changes nothing keeps it live
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['password' => '']))
            ->assertSessionHas('success', 'ok.integ.saved')
            ->assertSessionHas('integration', 'mail');
        $this->assertTrue($this->svc()->isActive('mail'));

        // a real change (the host) takes it offline and puts the server default back
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['host' => 'smtp.other.test']))->assertSessionHas('success', 'ok.integ.savedOff');
        $this->assertFalse($this->svc()->isActive('mail'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('untested', $this->svc()->status('mail')['state']);

        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password'])->assertSessionHas('error', 'err.integ.testFirst');
    }

    public function test_deactivating_and_clearing_return_to_the_server_default(): void
    {
        Mail::fake();
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $this->tested($a);
        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password']);

        $this->as($a)->post(route('platform.integrations.deactivate', 'mail'), ['current_password' => 'password'])->assertSessionHas('success', 'ok.integ.deactivated');
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('tested', $this->svc()->status('mail')['state'], 'values and test result are kept, so it can be switched on again');

        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('smtp', config('mail.default'));

        $this->as($a)->delete(route('platform.integrations.destroy', 'mail'), ['current_password' => 'password'])->assertSessionHas('success', 'ok.integ.cleared');
        $this->assertSame('array', config('mail.default'));
        $this->assertNull(PlatformSetting::query()->where('key', 'integrations.mail')->first());
        $this->assertSame('none', $this->svc()->status('mail')['state']);
    }

    public function test_a_change_is_picked_up_by_a_long_running_worker(): void
    {
        Mail::fake();
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $this->tested($a);
        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password']);

        // another process wrote the row; this process only learns about it through the shared cache and the job hook
        $row = PlatformSetting::query()->where('key', 'integrations.mail')->firstOrFail();
        $row->update(['value' => array_merge($row->value, ['enabled' => false])]);
        Cache::forget(IntegrationSettings::CACHE_KEY);

        event(new \Illuminate\Queue\Events\JobProcessing('sync', \Mockery::mock(\Illuminate\Contracts\Queue\Job::class)->shouldIgnoreMissing()));

        $this->assertSame('array', config('mail.default'));
    }

    public function test_confirmation_is_the_only_way_to_verified_and_a_change_removes_it(): void
    {
        Mail::fake();
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $this->tested($a);
        $this->assertFalse($this->svc()->status('mail')['verified']);

        $this->as($a)->post(route('platform.integrations.confirm', 'mail'))->assertSessionHas('success', 'ok.integ.confirmed');
        $this->assertTrue($this->svc()->status('mail')['verified']);

        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['port' => 2525]));
        $this->assertFalse($this->svc()->status('mail')['verified']);

        $this->tested($a);                                           // a fresh test clears an older confirmation too
        $this->as($a)->post(route('platform.integrations.confirm', 'mail'));
        $this->tested($a);
        $this->assertFalse($this->svc()->status('mail')['verified']);
    }

    // ================================================================== validation

    public function test_mail_settings_are_validated_with_translatable_codes(): void
    {
        $a = $this->admin();
        $r = $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['host' => 'smtp://evil/x', 'port' => 99999, 'from_address' => 'nope', 'encryption' => 'none']));
        $r->assertSessionHasErrors(['host' => 'err.integ.invalid', 'port' => 'err.integ.port', 'from_address' => 'err.integ.email', 'encryption' => 'err.integ.invalid']);
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody(['host' => '', 'from_name' => '']))->assertSessionHasErrors(['host' => 'err.integ.required', 'from_name' => 'err.integ.required']);
        $this->assertNull(PlatformSetting::query()->where('key', 'integrations.mail')->first());
    }

    public function test_the_chosen_sms_provider_must_be_complete(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody(['twilio_token' => '']))->assertSessionHasErrors(['twilio_token' => 'err.integ.required']);
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody(['twilio_from' => '']))->assertSessionHasErrors(['twilio_from' => 'err.integ.required']);
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody(['twilio_from' => '', 'twilio_messaging_service_sid' => 'MG123']))->assertSessionHasNoErrors();
        $this->as($a)->put(route('platform.integrations.save', 'sms'), ['driver' => 'africastalking', 'current_password' => 'password'])
            ->assertSessionHasErrors(['at_username' => 'err.integ.required', 'at_api_key' => 'err.integ.required']);
        $this->as($a)->put(route('platform.integrations.save', 'sms'), ['driver' => 'carrier-pigeon', 'current_password' => 'password'])->assertSessionHasErrors('driver');
        // the Twilio token stored above is kept, so leaving it blank on a later save is fine
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody(['twilio_token' => '']))->assertSessionHasNoErrors();
    }

    public function test_the_test_target_is_validated(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $this->as($a)->post(route('platform.integrations.test', 'mail'), ['target' => 'not-an-email'])->assertSessionHasErrors(['target' => 'err.integ.email']);
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody());
        $this->as($a)->post(route('platform.integrations.test', 'sms'), ['target' => 'abc'])->assertSessionHasErrors(['target' => 'err.integ.phone']);
        $this->as($a)->post(route('platform.integrations.test', 'mail'), ['target' => ''])->assertSessionHasErrors('target');
        $this->as($a)->post(route('platform.integrations.test', 'push'), ['target' => 'x@y.test'])->assertNotFound();
    }

    public function test_testing_with_nothing_saved_is_refused(): void
    {
        $this->as($this->admin())->post(route('platform.integrations.test', 'mail'), ['target' => 'me@example.test'])->assertSessionHas('error', 'err.integ.nothingSaved');
    }

    // ================================================================== SMS

    public function test_sms_test_uses_the_saved_provider_values_and_activation_switches_the_gateway(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1', 'price' => null, 'price_unit' => null], 201)]);
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody())->assertSessionHasNoErrors();
        $this->assertSame('log', config('schoolos.sms.driver'));

        $this->as($a)->post(route('platform.integrations.test', 'sms'), ['target' => '7001234'])->assertSessionHas('success', 'ok.integ.testSentSms');

        Http::assertSent(function (HttpRequest $r) {
            return str_contains($r->url(), '/Accounts/AC1234567890abcdef/Messages.json')
                && $r->hasHeader('Authorization', 'Basic '.base64_encode('AC1234567890abcdef:twilio-auth-token-abcdef123456'))
                && $r['To'] === '+2207001234' && $r['From'] === '+15005550006';
        });
        $this->assertSame('log', config('schoolos.sms.driver'), 'a test must not switch anything on');
        $this->assertSame('***234', $this->svc()->status('sms')['tested_to']);

        $this->as($a)->post(route('platform.integrations.activate', 'sms'), ['current_password' => 'password'])->assertSessionHas('success', 'ok.integ.activated');
        $this->assertSame('twilio', config('schoolos.sms.driver'));
        $this->assertSame('twilio', app(SmsService::class)->gateway()->name());
        $this->assertTrue(app(SmsService::class)->providerState()['configured']);
        $this->assertFalse($this->svc()->status('sms')['verified']);
    }

    public function test_a_provider_rejection_is_a_failed_test_and_leaks_nothing(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['code' => 20003, 'message' => 'Authenticate: bad token twilio-auth-token-abcdef123456'], 401)]);
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody());

        $this->as($a)->post(route('platform.integrations.test', 'sms'), ['target' => '7001234'])->assertSessionHas('error', 'err.integ.testFailed');

        $status = $this->svc()->status('sms');
        $this->assertSame('failed', $status['state']);
        $this->assertStringContainsString('20003', (string) $status['test_error']);
        $this->assertStringNotContainsString('twilio-auth-token-abcdef123456', (string) $status['test_error']);
        $this->as($a)->post(route('platform.integrations.activate', 'sms'), ['current_password' => 'password'])->assertSessionHas('error', 'err.integ.testFirst');
        $this->assertSame('log', config('schoolos.sms.driver'));
    }

    public function test_africastalking_values_are_applied_including_the_webhook_secret(): void
    {
        Http::fake(['api.sandbox.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 101, 'messageId' => 'ATXid_1', 'cost' => 'GMD 1.0000', 'status' => 'Success']]]], 201)]);
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'sms'), [
            'driver' => 'africastalking', 'at_username' => 'sandbox', 'at_api_key' => 'at-api-key-0123456789abcdef', 'at_sandbox' => true,
            'at_webhook_secret' => 'path-secret_123', 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('at-api-key-0123456789abcdef', $this->raw('sms'));
        $this->assertStringNotContainsString('path-secret_123', $this->raw('sms'));

        $this->tested($a, 'sms', '+2207001234');
        $this->as($a)->post(route('platform.integrations.activate', 'sms'), ['current_password' => 'password'])->assertSessionHasNoErrors();

        $this->assertSame('africastalking', config('schoolos.sms.driver'));
        $this->assertSame('at-api-key-0123456789abcdef', config('schoolos.sms.africastalking.api_key'));
        $this->assertSame('path-secret_123', config('schoolos.sms.africastalking.webhook_secret'));
        $this->assertTrue(config('schoolos.sms.africastalking.sandbox'));

        // the inbound delivery-report URL is checked against the console value
        $this->post('/webhooks/sms/africastalking/path-secret_123', ['id' => 'ATXid_1', 'status' => 'Success'])->assertNoContent();
        $this->post('/webhooks/sms/africastalking/wrong', ['id' => 'ATXid_1', 'status' => 'Success'])->assertForbidden();
    }

    public function test_an_unrelated_providers_fields_do_not_invalidate_a_tested_provider(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody());
        $this->tested($a, 'sms');

        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody(['at_username' => 'someone', 'at_api_key' => 'at-key-for-later-0123456789']))->assertSessionHasNoErrors();
        $this->assertSame('tested', $this->svc()->status('sms')['state']);

        $this->as($a)->put(route('platform.integrations.save', 'sms'), $this->twilioBody(['twilio_from' => '+15005550099']));
        $this->assertSame('untested', $this->svc()->status('sms')['state']);
    }

    // ================================================================== trail and notices

    public function test_every_change_is_audited_without_values_and_the_other_administrators_are_told(): void
    {
        Notification::fake();
        Mail::fake();
        $a = $this->admin();
        $other = $this->admin('platform_admin');
        $finance = $this->admin('platform_finance');

        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());
        $this->tested($a);
        $this->as($a)->post(route('platform.integrations.activate', 'mail'), ['current_password' => 'password']);
        $this->as($a)->post(route('platform.integrations.deactivate', 'mail'), ['current_password' => 'password']);
        $this->as($a)->delete(route('platform.integrations.destroy', 'mail'), ['current_password' => 'password']);

        $events = app(TenantContext::class)->runAsPlatform(fn () => ActivityLog::query()->where('event', 'like', 'platform.integration.%')->orderBy('id')->get());
        $this->assertSame(['saved', 'test_ok', 'activated', 'deactivated', 'cleared'], $events->map(fn ($e) => str_replace('platform.integration.', '', $e->event))->all());
        $this->assertTrue($events->every(fn ($e) => $e->user_id === $a->id && $e->school_id === null));

        $blob = json_encode($events->map->properties);
        foreach ([self::SECRET, 'smtp.example.test', 'apikey', 'no-reply@schoolos.test'] as $value) {
            $this->assertStringNotContainsString($value, $blob);
        }
        $this->assertSame(['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'], $events->first()->properties['fields']);   // names only

        Notification::assertSentTo($other, IntegrationChangedNotification::class, 4);          // saved, activated, deactivated, cleared
        Notification::assertNotSentTo($a, IntegrationChangedNotification::class);
        Notification::assertNotSentTo($finance, IntegrationChangedNotification::class);

        $this->as($a)->get(route('platform.integrations.index'))->assertInertia(fn ($p) => $p->where('activity.0.event', 'cleared')->where('activity.0.by', $a->name)->has('activity', 5));
    }

    public function test_the_page_shows_the_server_default_without_its_secrets(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.server-default.test', 'mail.mailers.smtp.password' => 'env-secret-never-shown', 'mail.from.address' => 'env@schoolos.test']);

        $response = $this->as($this->admin())->get(route('platform.integrations.index'))->assertOk();
        $response->assertInertia(fn ($p) => $p->where('items.mail.server.driver', 'smtp')->where('items.mail.server.real', true)->where('items.mail.server.host', 'mail.server-default.test')->where('items.mail.server.from', 'env@schoolos.test'));
        $this->assertStringNotContainsString('env-secret-never-shown', $response->getContent());
    }

    public function test_the_mail_form_is_prefilled_from_platform_identity(): void
    {
        config([
            'schoolos.mail.from_address' => 'noreply@gambiaschoolos.com',
            'schoolos.mail.from_name' => 'Gambia SchoolOS',
            'schoolos.mail.smtp.port' => 587,
            'schoolos.mail.smtp.encryption' => 'tls',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2525,
            'mail.from.address' => 'hello@example.com',
            'mail.from.name' => 'Laravel',
        ]);

        $this->as($this->admin())->get(route('platform.integrations.index'))->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('items.mail.state', 'none')
                ->where('items.mail.values.from_address', 'noreply@gambiaschoolos.com')
                ->where('items.mail.values.from_name', 'Gambia SchoolOS')
                ->where('items.mail.values.port', 587)
                ->where('items.mail.values.encryption', 'tls')
                ->where('items.mail.values.host', '')
                ->missing('items.mail.values.password'));
    }

    public function test_schools_cannot_see_platform_credentials_through_their_own_pages(): void
    {
        $a = $this->admin();
        $this->as($a)->put(route('platform.integrations.save', 'mail'), $this->mailBody());

        $school = School::query()->create(['name' => 'Alpha', 'slug' => 'alpha', 'status' => 'active']);
        $head = User::factory()->create(['school_id' => $school->id, 'email_verified_at' => now()]);
        $head->assignRole('school_admin');

        foreach ([route('dashboard'), route('communications.index')] as $url) {
            $content = $this->as($head)->get($url)->getContent();
            $this->assertStringNotContainsString(self::SECRET, $content);
            $this->assertStringNotContainsString('smtp.example.test', $content);
        }
    }
}
