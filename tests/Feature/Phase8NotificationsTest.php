<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Concerns\RespectsPreferences;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/** A small real notification that uses the production preference trait, so the whole channel pipeline is exercised. */
class Phase8Probe extends Notification
{
    use RespectsPreferences;

    public function __construct(private readonly string $cat, private readonly array $offered = ['in_app', 'email', 'sms']) {}

    public function category(): string
    {
        return $this->cat;
    }

    public function offeredChannels(): array
    {
        return $this->offered;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Probe')->line('Hello');
    }

    public function toSms(object $notifiable): array
    {
        return ['template' => 'test', 'vars' => [], 'category' => $this->cat];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Probe '.$this->cat, 'body' => 'b', 'category' => $this->cat, 'level' => 'info'];
    }
}

/** Channel selection, the per-person preference screen, the delivery history and the notification centre. */
class Phase8NotificationsTest extends Phase8TestCase
{
    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = $this->user('parent', null, ['phone' => '7123456']);
    }

    private function prefs(): NotificationPreferences
    {
        return app(NotificationPreferences::class);
    }

    private function channels(User $u, string $category, array $offered = ['in_app', 'email', 'sms']): array
    {
        $this->prefs()->forget($u);

        return $this->inSchool(fn () => $this->prefs()->channels($u->fresh(), $category, $offered));
    }

    // ------------------------------------------------------------------ channel selection

    public function test_defaults_give_in_app_and_email_and_sms_only_where_it_is_the_starting_choice(): void
    {
        $this->assertSame(['database', 'mail'], $this->channels($this->parent, 'announcement'));
        $this->assertSame(['database', 'mail'], $this->channels($this->parent, 'fees'));
    }

    public function test_sms_needs_school_switch_a_usable_number_and_the_category_to_allow_it(): void
    {
        // school SMS off: never, even for the category that defaults to SMS
        $this->assertNotContains(SmsChannel::class, $this->channels($this->parent, 'attendance'));

        $this->setSchoolSettings(['communications' => ['sms_enabled' => true]]);
        $this->parent->unsetRelation('school');
        $this->assertContains(SmsChannel::class, $this->channels($this->parent, 'attendance'));
        $this->assertNotContains(SmsChannel::class, $this->channels($this->parent, 'announcement'), 'Announcements are opt-in.');
        $this->assertNotContains(SmsChannel::class, $this->channels($this->parent, 'lms'), 'LMS never uses SMS.');
        $this->assertNotContains(SmsChannel::class, $this->channels($this->parent, 'account'), 'Security notices never use SMS.');

        // no / bad number
        $noPhone = $this->user('parent', null, ['phone' => null]);
        $badPhone = $this->user('parent', null, ['phone' => 'call me']);
        $this->assertNotContains(SmsChannel::class, $this->channels($noPhone, 'attendance'));
        $this->assertNotContains(SmsChannel::class, $this->channels($badPhone, 'attendance'));
    }

    public function test_a_person_can_opt_in_and_out_per_category_and_channel(): void
    {
        $this->setSchoolSettings(['communications' => ['sms_enabled' => true]]);
        $this->parent->unsetRelation('school');

        $this->actAs($this->parent)->post(route('notifications.preferences.save'), ['prefs' => [
            'announcement' => ['email' => 0, 'sms' => 1],
            'attendance' => ['sms' => 0, 'in_app' => 0],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['database', SmsChannel::class], $this->channels($this->parent, 'announcement'));
        $this->assertSame(['mail'], $this->channels($this->parent, 'attendance'));
    }

    public function test_mandatory_categories_cannot_be_switched_off_and_unknown_ones_are_ignored(): void
    {
        $this->actAs($this->parent)->post(route('notifications.preferences.save'), ['prefs' => [
            'account' => ['in_app' => 0, 'email' => 0, 'sms' => 1],
            'made_up' => ['email' => 0],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['database', 'mail'], $this->channels($this->parent, 'account'));
        $this->assertSame(0, $this->inSchool(fn () => NotificationPreference::query()->where('category', 'made_up')->count()));
        $this->assertSame(0, $this->inSchool(fn () => NotificationPreference::query()->where('category', 'account')->count()));
    }

    public function test_an_unverified_address_never_receives_e_mail(): void
    {
        $this->parent->forceFill(['email_verified_at' => null])->save();

        $this->assertSame(['database'], $this->channels($this->parent, 'announcement'));
    }

    public function test_preferences_belong_to_the_person_who_saved_them(): void
    {
        $other = $this->user('parent');
        $this->actAs($this->parent)->post(route('notifications.preferences.save'), ['prefs' => ['fees' => ['email' => 0]]]);

        $this->assertSame(['database'], $this->channels($this->parent, 'fees'));
        $this->assertSame(['database', 'mail'], $this->channels($other, 'fees'));
    }

    // ------------------------------------------------------------------ delivery history

    public function test_delivery_history_says_logged_for_the_array_mailer_never_delivered(): void
    {
        $this->parent->notify(new Phase8Probe('fees', ['in_app', 'email']));

        $rows = $this->inSchool(fn () => NotificationDelivery::query()->where('user_id', $this->parent->id)->orderBy('channel')->get());

        $this->assertSame(['email', 'in_app'], $rows->pluck('channel')->all());
        $this->assertSame('logged', $rows->firstWhere('channel', 'email')->status, 'Nothing left the server: it must not say sent.');
        $this->assertSame('sent', $rows->firstWhere('channel', 'in_app')->status);
        $this->assertSame('Probe fees', $rows->first()->summary);
        $this->assertSame('fees', $rows->first()->category);
    }

    public function test_a_failed_channel_is_recorded_as_failed_with_the_reason(): void
    {
        event(new NotificationFailed($this->parent, new Phase8Probe('fees'), 'mail', ['message' => 'smtp down']));

        $row = $this->inSchool(fn () => NotificationDelivery::query()->where('user_id', $this->parent->id)->first());
        $this->assertSame('failed', $row->status);
        $this->assertSame('smtp down', $row->error);
    }

    public function test_sms_goes_through_the_sms_log_not_the_delivery_history(): void
    {
        config(['schoolos.sms.driver' => 'log']);
        $this->setSchoolSettings(['communications' => ['sms_enabled' => true]]);
        $this->parent->unsetRelation('school');

        $this->parent->notify(new Phase8Probe('attendance', ['sms']));

        $sms = $this->inSchool(fn () => SmsMessage::query()->get());
        $this->assertCount(1, $sms);
        $this->assertSame('logged', $sms->first()->status);
        $this->assertSame('+2207123456', $sms->first()->to_phone);
        $this->assertSame(0, $this->inSchool(fn () => NotificationDelivery::query()->count()));
    }

    // ------------------------------------------------------------------ notification centre

    public function test_the_centre_lists_only_the_callers_notifications(): void
    {
        $other = $this->user('parent');
        $this->parent->notify(new Phase8Probe('fees', ['in_app']));
        $this->parent->notify(new Phase8Probe('exam', ['in_app']));
        $other->notify(new Phase8Probe('fees', ['in_app']));

        $this->actAs($this->parent)->get(route('notifications.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Notifications/Index')->where('unreadCount', 2)->has('notifications.data', 2));

        $this->actAs($this->parent)->get(route('notifications.index', ['category' => 'exam']))
            ->assertInertia(fn (Assert $p) => $p->has('notifications.data', 1)->where('notifications.data.0.category', 'exam'));
    }

    public function test_nobody_can_mark_someone_elses_notification_as_read(): void
    {
        $other = $this->user('parent');
        $other->notify(new Phase8Probe('fees', ['in_app']));
        $id = $other->notifications()->first()->id;

        $this->actAs($this->parent)->post(route('notifications.read', $id))->assertNotFound();
        $this->assertSame(1, $other->unreadNotifications()->count());

        $this->actAs($other)->post(route('notifications.read', $id))->assertRedirect();
        $this->assertSame(0, $other->unreadNotifications()->count());
    }

    public function test_mark_all_read_only_touches_the_caller(): void
    {
        $other = $this->user('parent');
        $this->parent->notify(new Phase8Probe('fees', ['in_app']));
        $other->notify(new Phase8Probe('fees', ['in_app']));

        $this->actAs($this->parent)->post(route('notifications.read-all'))->assertSessionHas('success', 'ok.notif.allRead');

        $this->assertSame(0, $this->parent->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    // ------------------------------------------------------------------ preferences screen and phone

    public function test_the_preferences_screen_shows_the_matrix_and_only_my_history(): void
    {
        $other = $this->user('parent');
        $this->parent->notify(new Phase8Probe('fees', ['in_app', 'email']));
        $other->notify(new Phase8Probe('exam', ['in_app', 'email']));

        $this->actAs($this->parent)->get(route('notifications.preferences'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Notifications/Preferences')
                ->has('matrix', count(config('schoolos.notifications.categories')))
                ->where('phoneValid', true)->where('emailVerified', true)->where('smsEnabled', false)
                ->has('history', 2)->where('history.0.category', 'fees'));
    }

    public function test_the_phone_number_is_validated_and_can_be_cleared(): void
    {
        $this->actAs($this->parent)->post(route('notifications.phone'), ['phone' => 'call me'])->assertSessionHas('error', 'err.sms.badPhone');
        $this->assertSame('7123456', $this->parent->fresh()->phone);

        $this->actAs($this->parent)->post(route('notifications.phone'), ['phone' => '+220 987 6543'])->assertSessionHasNoErrors();
        $this->assertSame('+220 987 6543', $this->parent->fresh()->phone);

        $this->actAs($this->parent)->post(route('notifications.phone'), ['phone' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->parent->fresh()->phone);
    }

    public function test_guests_cannot_reach_the_notification_pages(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->get(route('notifications.preferences'))->assertRedirect(route('login'));
    }
}
