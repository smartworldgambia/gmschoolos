<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use App\Services\Sms\SmsTransientException;
use Illuminate\Support\Facades\Http;

/**
 * SMS stack. Every provider call here is a FAKED http response: these tests prove the code builds the right request
 * and handles the documented answers. They do NOT prove a handset received anything - that needs a live sandbox.
 */
class Phase8SmsTest extends Phase8TestCase
{
    private function enableSms(array $extra = []): void
    {
        $this->setSchoolSettings(['communications' => ['sms_enabled' => true] + $extra]);
    }

    private function twilio(): void
    {
        config([
            'schoolos.sms.driver' => 'twilio',
            'schoolos.sms.twilio' => ['sid' => 'ACtest', 'token' => 'secret-token', 'from' => '+15005550006', 'messaging_service_sid' => null, 'status_callback' => null],
        ]);
    }

    private function africasTalking(bool $sandbox = false): void
    {
        config([
            'schoolos.sms.driver' => 'africastalking',
            'schoolos.sms.africastalking' => ['username' => 'schoolos', 'api_key' => 'at-key', 'from' => null, 'sandbox' => $sandbox, 'webhook_secret' => 'path-secret-123'],
        ]);
    }

    private function send(string $phone = '7123456', string $body = 'Hello'): ?SmsMessage
    {
        return app(SmsService::class)->queue($this->school->fresh(), $this->teacher, $phone, $body);
    }

    private function last(): ?SmsMessage
    {
        return $this->inSchool(fn () => SmsMessage::query()->latest('id')->first());
    }

    // ------------------------------------------------------------------ numbers and text

    public function test_phone_numbers_are_normalised_or_rejected(): void
    {
        $this->assertSame('+2207123456', PhoneNumber::normalise('7123456'));
        $this->assertSame('+2207123456', PhoneNumber::normalise('+220 712 3456'));
        $this->assertSame('+2207123456', PhoneNumber::normalise('00220-7123456'));
        $this->assertSame('+2207123456', PhoneNumber::normalise('2207123456'));
        $this->assertSame('+447911123456', PhoneNumber::normalise('+44 7911 123456'));

        foreach ([null, '', 'abc', '12', '+0123456789', '123456789012345678', '71234', 'call me'] as $bad) {
            $this->assertNull(PhoneNumber::normalise($bad), (string) $bad);
        }
    }

    public function test_segments_follow_gsm_and_unicode_rules(): void
    {
        $this->assertSame(1, PhoneNumber::segments(str_repeat('a', 160)));
        $this->assertSame(2, PhoneNumber::segments(str_repeat('a', 161)));
        $this->assertSame(1, PhoneNumber::segments(str_repeat('ع', 70)));
        $this->assertSame(2, PhoneNumber::segments(str_repeat('ع', 71)));
    }

    public function test_templates_use_the_school_wording_then_the_platform_default_then_english(): void
    {
        $this->inSchool(function () {
            $t = app(SmsTemplates::class);

            $this->assertStringContainsString('Alpha', (string) $t->render('test', 'en', ['school' => 'Alpha']));
            $this->assertStringContainsString('test SMS', (string) $t->render('test', 'fr', ['school' => 'Alpha']));
            $this->assertNotNull($t->render('test', 'xx', ['school' => 'Alpha']));       // unknown language -> English
            $this->assertNull($t->render('no_such_template', 'en', []));

            SmsTemplate::query()->create(['school_id' => $this->school->id, 'key' => 'test', 'locale' => 'en', 'body' => 'Custom {school} {unknown}!', 'active' => true]);
            $this->assertSame('Custom Alpha !', $t->render('test', 'en', ['school' => 'Alpha']));

            // a variable can never inject a line break or control characters
            $this->assertSame('Custom A B !', $t->render('test', 'en', ['school' => "A\r\nB"]));
        });
    }

    // ------------------------------------------------------------------ switches and the log driver

    public function test_nothing_is_queued_when_the_school_has_sms_off_or_there_is_no_number(): void
    {
        $this->assertNull($this->send());
        $this->enableSms();
        $this->assertNull($this->send(''));
        $this->assertSame(0, $this->inSchool(fn () => SmsMessage::query()->count()));
    }

    public function test_the_log_driver_records_logged_never_sent(): void
    {
        config(['schoolos.sms.driver' => 'log']);
        $this->enableSms();

        $this->send('7123456', 'Hi there');
        $m = $this->last();

        $this->assertSame('logged', $m->status);
        $this->assertSame('log', $m->provider);
        $this->assertNull($m->sent_at, 'A logged message never left the server.');
        $this->assertSame('+2207123456', $m->to_phone);
    }

    public function test_an_invalid_number_is_recorded_as_failed_and_cannot_be_retried(): void
    {
        $this->enableSms();
        $this->send('not-a-number');
        $m = $this->last();

        $this->assertSame('failed', $m->status);
        $this->assertSame('invalid_phone', $m->last_error);
        $this->assertSame('failed', $this->inSchool(fn () => app(SmsService::class)->retry($m)->status));
    }

    public function test_the_daily_cap_stops_further_messages(): void
    {
        config(['schoolos.sms.driver' => 'log']);
        $this->enableSms(['sms_daily_cap' => 2]);

        $this->send();
        $this->send();
        $this->send();

        $statuses = $this->inSchool(fn () => SmsMessage::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(['logged', 'logged', 'failed'], $statuses);
        $this->assertSame('daily_cap_reached', $this->last()->last_error);
    }

    // ------------------------------------------------------------------ Twilio (faked http)

    public function test_twilio_success_builds_the_documented_request(): void
    {
        $this->twilio();
        $this->enableSms(['sms_sender' => 'AlphaSch']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM111', 'price' => null, 'price_unit' => null], 201)]);

        $this->send('7123456', 'Hello');
        $m = $this->last();

        $this->assertSame('sent', $m->status);
        $this->assertSame('SM111', $m->provider_ref);
        $this->assertNotNull($m->sent_at);
        $this->assertNull($m->delivered_at, 'Accepted by the provider is not delivered.');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages.json'
                && $request['To'] === '+2207123456' && $request['Body'] === 'Hello' && $request['From'] === 'AlphaSch'
                && str_starts_with($request->header('Authorization')[0], 'Basic ');
        });
    }

    public function test_twilio_permanent_rejection_fails_at_once(): void
    {
        $this->twilio();
        $this->enableSms();
        Http::fake(['api.twilio.com/*' => Http::response(['code' => 21211, 'message' => 'Invalid To number'], 400)]);

        $this->send();
        $m = $this->last();

        $this->assertSame('failed', $m->status);
        $this->assertStringContainsString('21211', $m->last_error);
        $this->assertSame(1, (int) $m->attempts);
        Http::assertSentCount(1);
    }

    public function test_a_transient_provider_error_is_retried_and_then_given_up(): void
    {
        $this->twilio();
        $this->enableSms();
        config(['schoolos.sms.max_attempts' => 3]);
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Service unavailable'], 503)]);

        $this->send();
        $m = $this->last();
        $this->assertSame('queued', $m->status, 'A transient failure keeps the message queued for the retry.');
        $this->assertSame(1, (int) $m->attempts);
        $this->assertNotNull($m->last_error);

        $this->inSchool(function () use ($m) {
            $this->expectException(SmsTransientException::class);
            app(SmsService::class)->deliver($m->fresh());
        });
    }

    public function test_the_job_gives_up_after_the_configured_attempts(): void
    {
        $this->twilio();
        $this->enableSms();
        config(['schoolos.sms.max_attempts' => 2]);
        Http::fake(['api.twilio.com/*' => Http::response([], 500)]);

        $this->send();                                       // attempt 1 (inline), stays queued
        $id = $this->last()->id;

        (new SendSmsJob($id, $this->school->id))->handle(app(SmsService::class), app(\App\Support\TenantContext::class));   // attempt 2
        $m = $this->last();

        $this->assertSame('failed', $m->status);
        $this->assertStringContainsString('gave_up_after_2_attempts', $m->last_error);
        Http::assertSentCount(2);
    }

    public function test_an_unconfigured_provider_fails_visibly_instead_of_pretending(): void
    {
        config(['schoolos.sms.driver' => 'twilio', 'schoolos.sms.twilio' => ['sid' => null, 'token' => null, 'from' => null]]);
        $this->enableSms();
        Http::fake();

        $this->send();

        $this->assertSame('failed', $this->last()->status);
        $this->assertSame('twilio_not_configured', $this->last()->last_error);
        Http::assertNothingSent();
    }

    public function test_a_sent_message_is_never_sent_twice(): void
    {
        $this->twilio();
        $this->enableSms();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM222'], 201)]);

        $this->send();
        $m = $this->last();
        $this->inSchool(fn () => app(SmsService::class)->deliver($m->fresh()));

        Http::assertSentCount(1);
    }

    // ------------------------------------------------------------------ Africa's Talking (faked http)

    public function test_africas_talking_success_records_the_reference_and_cost(): void
    {
        $this->africasTalking();
        $this->enableSms();
        Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Message' => 'Sent to 1/1', 'Recipients' => [
            ['statusCode' => 101, 'number' => '+2207123456', 'status' => 'Success', 'cost' => 'GMD 0.8000', 'messageId' => 'ATXid_1'],
        ]]], 201)]);

        $this->send();
        $m = $this->last();

        $this->assertSame('sent', $m->status);
        $this->assertSame('ATXid_1', $m->provider_ref);
        $this->assertSame('GMD', $m->cost_currency);
        $this->assertEquals(0.8, (float) $m->cost_amount);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.africastalking.com/version1/messaging' && $r->hasHeader('apiKey', 'at-key') && $r['username'] === 'schoolos');
    }

    public function test_africas_talking_rejection_and_sandbox_endpoint(): void
    {
        $this->africasTalking(true);
        $this->enableSms();
        Http::fake(['api.sandbox.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [
            ['statusCode' => 403, 'number' => '+2207123456', 'status' => 'InvalidPhoneNumber', 'cost' => '0', 'messageId' => 'None'],
        ]]], 201)]);

        $this->send();

        $this->assertSame('failed', $this->last()->status);
        $this->assertStringContainsString('InvalidPhoneNumber', $this->last()->last_error);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.sandbox.africastalking.com/'));
    }

    // ------------------------------------------------------------------ delivery reports

    private function sentMessage(string $provider, string $ref): SmsMessage
    {
        return $this->inSchool(fn () => SmsMessage::query()->create([
            'school_id' => $this->school->id, 'to_phone' => '+2207123456', 'body' => 'x', 'segments' => 1, 'status' => 'sent',
            'provider' => $provider, 'provider_ref' => $ref, 'sent_at' => now(),
        ]));
    }

    private function twilioSigned(array $params, ?string $token = null): array
    {
        $url = 'http://localhost/webhooks/sms/twilio';
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return ['X-Twilio-Signature' => base64_encode(hash_hmac('sha1', $data, $token ?? 'secret-token', true))];
    }

    public function test_a_signed_twilio_report_marks_the_message_delivered(): void
    {
        $this->twilio();
        $m = $this->sentMessage('twilio', 'SM900');
        $p = ['MessageSid' => 'SM900', 'MessageStatus' => 'delivered'];

        $this->post('/webhooks/sms/twilio', $p, $this->twilioSigned($p))->assertNoContent();

        $m = $m->fresh();
        $this->assertSame('delivered', $m->status);
        $this->assertNotNull($m->delivered_at);
    }

    public function test_twilio_reports_with_a_bad_or_missing_signature_are_refused(): void
    {
        $this->twilio();
        $m = $this->sentMessage('twilio', 'SM901');
        $p = ['MessageSid' => 'SM901', 'MessageStatus' => 'delivered'];

        $this->post('/webhooks/sms/twilio', $p)->assertForbidden();
        $this->post('/webhooks/sms/twilio', $p, $this->twilioSigned($p, 'wrong-token'))->assertForbidden();
        $this->post('/webhooks/sms/twilio', array_merge($p, ['MessageStatus' => 'failed']), $this->twilioSigned($p))->assertForbidden();   // tampered body

        config(['schoolos.sms.twilio.token' => '']);
        $this->post('/webhooks/sms/twilio', $p, $this->twilioSigned($p, ''))->assertForbidden();

        $this->assertSame('sent', $m->fresh()->status);
    }

    public function test_a_failed_report_records_the_reason_and_delivered_is_never_downgraded(): void
    {
        $this->twilio();
        $failed = $this->sentMessage('twilio', 'SM902');
        $done = $this->sentMessage('twilio', 'SM903');

        $p = ['MessageSid' => 'SM902', 'MessageStatus' => 'undelivered', 'ErrorCode' => '30003'];
        $this->post('/webhooks/sms/twilio', $p, $this->twilioSigned($p))->assertNoContent();
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame('twilio_error_30003', $failed->fresh()->last_error);

        $ok = ['MessageSid' => 'SM903', 'MessageStatus' => 'delivered'];
        $this->post('/webhooks/sms/twilio', $ok, $this->twilioSigned($ok))->assertNoContent();
        $late = ['MessageSid' => 'SM903', 'MessageStatus' => 'failed'];
        $this->post('/webhooks/sms/twilio', $late, $this->twilioSigned($late))->assertNoContent();
        $this->assertSame('delivered', $done->fresh()->status);

        $unknown = ['MessageSid' => 'SM-none', 'MessageStatus' => 'delivered'];
        $this->post('/webhooks/sms/twilio', $unknown, $this->twilioSigned($unknown))->assertNoContent();
    }

    public function test_africas_talking_reports_need_the_path_secret(): void
    {
        $this->africasTalking();
        $m = $this->sentMessage('africastalking', 'ATXid_9');

        $this->post('/webhooks/sms/africastalking/wrong-secret', ['id' => 'ATXid_9', 'status' => 'Success'])->assertForbidden();
        $this->assertSame('sent', $m->fresh()->status);

        $this->post('/webhooks/sms/africastalking/path-secret-123', ['id' => 'ATXid_9', 'status' => 'Success'])->assertNoContent();
        $this->assertSame('delivered', $m->fresh()->status);

        config(['schoolos.sms.africastalking.webhook_secret' => '']);
        $this->post('/webhooks/sms/africastalking/', ['id' => 'x', 'status' => 'Success'])->assertNotFound();
    }

    public function test_a_report_cannot_touch_another_providers_message(): void
    {
        $this->twilio();
        $m = $this->sentMessage('africastalking', 'SM904');
        $p = ['MessageSid' => 'SM904', 'MessageStatus' => 'delivered'];

        $this->post('/webhooks/sms/twilio', $p, $this->twilioSigned($p))->assertNoContent();

        $this->assertSame('sent', $m->fresh()->status);
    }

    public function test_a_manual_retry_requeues_a_failed_message_only(): void
    {
        config(['schoolos.sms.driver' => 'log']);
        $this->enableSms();
        $m = $this->inSchool(fn () => SmsMessage::query()->create([
            'school_id' => $this->school->id, 'to_phone' => '+2207123456', 'body' => 'x', 'segments' => 1, 'status' => 'failed',
            'provider' => 'log', 'last_error' => 'boom', 'failed_at' => now(), 'attempts' => 3,
        ]));

        $this->inSchool(fn () => app(SmsService::class)->retry($m));

        $m = $this->inSchool(fn () => $m->fresh());
        $this->assertSame('logged', $m->status);      // sync queue: it ran again straight away
        $this->assertNull($m->last_error);
    }
}
