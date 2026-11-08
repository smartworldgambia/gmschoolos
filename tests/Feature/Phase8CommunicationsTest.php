<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\NotificationDelivery;
use App\Models\School;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** The administrators' communications centre: SMS switch, provider state, templates, SMS log, retry, test and delivery history. */
class Phase8CommunicationsTest extends Phase8TestCase
{
    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        config(['schoolos.sms.driver' => 'log']);
        $this->head = $this->user('headteacher');
    }

    private function settings(array $over = []): array
    {
        return $over + ['sms_enabled' => 1, 'sms_sender' => 'AlphaSch', 'sms_daily_cap' => 100, 'parent_messaging' => 1, 'student_messaging' => 1];
    }

    private function sms(string $status, ?School $school = null, string $phone = '+2207123456', array $over = []): SmsMessage
    {
        $school ??= $this->school;

        return app(\App\Support\TenantContext::class)->runForSchool($school, fn () => SmsMessage::query()->create($over + [
            'school_id' => $school->id, 'to_phone' => $phone, 'body' => 'secret text', 'segments' => 1, 'status' => $status, 'provider' => 'log',
            'last_error' => $status === 'failed' ? 'boom' : null,
        ]));
    }

    // ------------------------------------------------------------------ the page

    public function test_the_settings_tab_reports_the_real_provider_state(): void
    {
        $this->actAs($this->head)->get(route('communications.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Communications/Index')->where('tab', 'settings')
                ->where('provider.driver', 'log')->where('provider.real', false)
                ->where('settings.sms_enabled', false)->where('stats.sent', 0)
                ->where('callbacks.twilio', url('/webhooks/sms/twilio')));

        config(['schoolos.sms.driver' => 'twilio', 'schoolos.sms.twilio' => ['sid' => 'AC1', 'token' => 't', 'from' => '+1500']]);
        $this->actAs($this->head)->get(route('communications.index'))
            ->assertInertia(fn (Assert $p) => $p->where('provider.driver', 'twilio')->where('provider.real', true)->where('provider.configured', true));

        config(['schoolos.sms.twilio' => ['sid' => null, 'token' => null, 'from' => null]]);
        $this->actAs($this->head)->get(route('communications.index'))
            ->assertInertia(fn (Assert $p) => $p->where('provider.configured', false));
    }

    public function test_the_sms_log_masks_numbers_hides_text_filters_and_stays_in_this_school(): void
    {
        $this->sms('failed');
        $this->sms('logged');
        $foreign = $this->sms('failed', $this->otherSchool(), '+2209999999');

        $r = $this->actAs($this->head)->get(route('communications.index', ['tab' => 'sms']));
        $r->assertInertia(fn (Assert $p) => $p->has('smsLog.data', 2));
        $this->assertStringNotContainsString('7123456', json_encode($r->viewData('page')['props']['smsLog']));
        $this->assertStringNotContainsString('secret text', json_encode($r->viewData('page')['props']['smsLog']));
        $this->assertStringNotContainsString('9999999', json_encode($r->viewData('page')['props']['smsLog']));

        $this->actAs($this->head)->get(route('communications.index', ['tab' => 'sms', 'status' => 'failed']))
            ->assertInertia(fn (Assert $p) => $p->has('smsLog.data', 1)->where('smsLog.data.0.status', 'failed')->where('smsLog.data.0.can_retry', true));
        $this->assertNotNull($foreign);
    }

    public function test_the_deliveries_tab_lists_this_schools_history_only(): void
    {
        $other = $this->otherSchool();
        $this->inSchool(fn () => NotificationDelivery::query()->create(['school_id' => $this->school->id, 'user_id' => $this->head->id, 'notification_type' => 'X', 'category' => 'fees', 'channel' => 'email', 'status' => 'logged', 'summary' => 'Mine']));
        $this->inSchool(fn () => NotificationDelivery::query()->create(['school_id' => $other->id, 'user_id' => null, 'notification_type' => 'X', 'category' => 'fees', 'channel' => 'email', 'status' => 'sent', 'summary' => 'Theirs']), $other);

        $this->actAs($this->head)->get(route('communications.index', ['tab' => 'deliveries']))
            ->assertInertia(fn (Assert $p) => $p->has('deliveries.data', 1)->where('deliveries.data.0.summary', 'Mine')->where('deliveries.data.0.status', 'logged'));
    }

    public function test_an_unknown_tab_falls_back_to_settings(): void
    {
        $this->actAs($this->head)->get(route('communications.index', ['tab' => '<script>']))->assertInertia(fn (Assert $p) => $p->where('tab', 'settings'));
    }

    // ------------------------------------------------------------------ settings

    public function test_settings_are_saved_audited_and_applied_to_this_school_only(): void
    {
        $other = $this->otherSchool();

        $this->actAs($this->head)->post(route('communications.settings'), $this->settings(['parent_messaging' => 0]))->assertSessionHasNoErrors()->assertSessionHas('success', 'ok.saved');

        $mine = $this->school->fresh()->settings['communications'];
        $this->assertTrue($mine['sms_enabled']);
        $this->assertSame('AlphaSch', $mine['sms_sender']);
        $this->assertSame(100, $mine['sms_daily_cap']);
        $this->assertFalse($mine['parent_messaging']);
        $this->assertArrayNotHasKey('communications', (array) $other->fresh()->settings);
        $this->assertSame(1, $this->inSchool(fn () => ActivityLog::query()->where('event', 'communications.settings_saved')->count()));
    }

    public function test_settings_are_validated(): void
    {
        foreach ([
            ['sms_sender' => 'WayTooLongSender', 'field' => 'sms_sender'],
            ['sms_sender' => 'Bad<script>', 'field' => 'sms_sender'],
            ['sms_daily_cap' => -1, 'field' => 'sms_daily_cap'],
            ['sms_daily_cap' => 999999, 'field' => 'sms_daily_cap'],
            ['sms_daily_cap' => 'many', 'field' => 'sms_daily_cap'],
        ] as $bad) {
            $field = $bad['field'];
            unset($bad['field']);
            $this->actAs($this->head)->post(route('communications.settings'), $this->settings($bad))->assertSessionHasErrors($field);
        }

        $this->assertArrayNotHasKey('communications', (array) $this->school->fresh()->settings);
    }

    public function test_the_parent_messaging_switch_really_stops_parents_from_messaging(): void
    {
        $parent = $this->guardian([$this->student($this->class)])->user;
        $this->actAs($this->teacher)->post(route('messages.store'), ['recipient_id' => $parent->id, 'body' => 'Hello'])->assertSessionHasNoErrors();

        $this->actAs($this->head)->post(route('communications.settings'), $this->settings(['parent_messaging' => 0]));
        $this->teacher->unsetRelation('school');          // a real next request loads the person afresh

        $this->actAs($this->teacher)->post(route('messages.store'), ['recipient_id' => $parent->id, 'body' => 'Again'])->assertSessionHas('error', 'err.msg.notAllowed');
    }

    public function test_only_people_with_the_permission_can_change_anything_here(): void
    {
        $parent = $this->guardian([$this->student($this->class)])->user;

        foreach ([$this->teacher, $parent] as $who) {
            $this->actAs($who)->post(route('communications.settings'), $this->settings())->assertForbidden();
            $this->actAs($who)->post(route('communications.templates'), ['key' => 'test', 'locale' => 'en', 'body' => 'x'])->assertForbidden();
            $this->actAs($who)->post(route('communications.sms.test'), ['phone' => '7123456'])->assertForbidden();
        }

        $this->assertArrayNotHasKey('communications', (array) $this->school->fresh()->settings);
    }

    // ------------------------------------------------------------------ templates

    public function test_a_school_can_override_and_reset_a_template(): void
    {
        $post = fn (string $body, string $key = 'test', string $locale = 'en') => $this->actAs($this->head)->post(route('communications.templates'), ['key' => $key, 'locale' => $locale, 'body' => $body]);

        $post("Hello {school}\r\n\tsecond line")->assertSessionHasNoErrors();
        $row = $this->inSchool(fn () => SmsTemplate::query()->where('key', 'test')->where('locale', 'en')->first());
        $this->assertSame('Hello {school} second line', $row->body);

        $post('Updated {school}');
        $this->assertSame(1, $this->inSchool(fn () => SmsTemplate::query()->count()), 'Saving again updates, never duplicates.');

        $post('');
        $this->assertSame(0, $this->inSchool(fn () => SmsTemplate::query()->count()), 'Empty text returns to the platform default.');

        $post('x', 'not_a_template')->assertSessionHasErrors('key');
        $post('x', 'test', 'de')->assertSessionHasErrors('locale');
        $post(str_repeat('a', 481))->assertSessionHasErrors('body');
    }

    public function test_one_schools_template_never_changes_another_schools_wording(): void
    {
        $other = $this->otherSchool();
        $this->actAs($this->head)->post(route('communications.templates'), ['key' => 'test', 'locale' => 'en', 'body' => 'Alpha wording']);

        $rows = app(\App\Support\TenantContext::class)->runAsPlatform(fn () => SmsTemplate::query()->withoutGlobalScopes()->get());
        $this->assertCount(1, $rows);
        $this->assertSame($this->school->id, (int) $rows->first()->school_id);
        $this->assertNotSame($other->id, (int) $rows->first()->school_id);
    }

    // ------------------------------------------------------------------ test message and retry

    public function test_the_test_sms_needs_sms_on_and_a_usable_number(): void
    {
        $this->actAs($this->head)->post(route('communications.sms.test'), ['phone' => '7123456'])->assertSessionHas('error', 'err.sms.disabled');

        $this->actAs($this->head)->post(route('communications.settings'), $this->settings());
        $this->actAs($this->head)->post(route('communications.sms.test'), ['phone' => 'call me'])->assertSessionHas('error', 'err.sms.badPhone');
        $this->assertSame(0, $this->inSchool(fn () => SmsMessage::query()->count()));

        $this->actAs($this->head)->post(route('communications.sms.test'), ['phone' => '7123456'])->assertSessionHas('success', 'ok.sms.testQueued');
        $m = $this->inSchool(fn () => SmsMessage::query()->first());
        $this->assertSame('logged', $m->status, 'The log driver never delivers; it must not claim to.');
        $this->assertSame('test', $m->template_key);
        $this->assertSame('+2207123456', $m->to_phone);
    }

    public function test_only_failed_messages_of_this_school_can_be_retried(): void
    {
        $failed = $this->sms('failed');
        $logged = $this->sms('logged');
        $foreign = $this->sms('failed', $this->otherSchool());

        $this->actAs($this->head)->post(route('communications.sms.retry', $logged->id))->assertSessionHas('error', 'err.sms.notFailed');
        $this->actAs($this->head)->post(route('communications.sms.retry', $foreign->id))->assertNotFound();
        $this->assertSame('failed', app(\App\Support\TenantContext::class)->runAsPlatform(fn () => SmsMessage::query()->withoutGlobalScopes()->find($foreign->id)->status));

        $this->actAs($this->head)->post(route('communications.sms.retry', $failed->id))->assertSessionHas('success', 'ok.sms.retried');
        $this->assertSame('logged', $this->inSchool(fn () => $failed->fresh()->status));
    }
}
