<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\SchoolAnnouncement;
use App\Services\Communications\AnnouncementService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/** Part 19.1: school and class announcements, scheduling, the feed, read receipts, channels and isolation. */
class Phase8AnnouncementsTest extends Phase8TestCase
{
    private User $teacher2;

    private User $parentA;

    private User $parentB;

    private User $learnerA;

    private User $learnerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher2 = $this->user('teacher');                  // teaches nobody
        $childA = $this->student($this->class, login: true, first: 'Ada');
        $childB = $this->student($this->otherClass, login: true, first: 'Bea');
        $this->learnerA = $childA->user;
        $this->learnerB = $childB->user;
        $this->parentA = $this->guardian([$childA])->user;
        $this->parentB = $this->guardian([$childB])->user;
    }

    private function svc(): AnnouncementService
    {
        return app(AnnouncementService::class);
    }

    private function announce(User $by, array $data, string $action = 'publish')
    {
        return $this->actAs($by)->post(route('announcements.store'), $data + ['title' => 'Sports day', 'body' => 'Friday at 9.', 'action' => $action]);
    }

    private function latest(): ?Announcement
    {
        return $this->inSchool(fn () => Announcement::query()->latest('id')->first());
    }

    /** @return array<int,int> user ids that received the announcement */
    private function recipients(?Announcement $a = null): array
    {
        $a ??= $this->latest();

        return $this->inSchool(fn () => AnnouncementRecipient::query()->where('announcement_id', $a->id)->orderBy('user_id')->pluck('user_id')->map(fn ($i) => (int) $i)->all());
    }

    private function ids(User ...$users): array
    {
        $ids = array_map(fn (User $u) => (int) $u->id, $users);
        sort($ids);

        return $ids;
    }

    // ------------------------------------------------------------------ audiences

    public function test_a_school_wide_announcement_reaches_every_active_portal_user_of_this_school_only(): void
    {
        $other = $this->otherSchool();
        $foreign = $this->user('teacher', $other);
        $suspended = $this->user('parent', null, ['suspended_at' => now()]);
        $revoked = $this->user('parent', null, ['access_revoked_at' => now()]);

        $this->announce($this->admin, ['audience' => 'school'])->assertSessionHasNoErrors()->assertSessionHas('success', 'ok.ann.published');

        $got = $this->recipients();
        $this->assertSame($this->ids($this->admin, $this->teacher, $this->teacher2, $this->parentA, $this->parentB, $this->learnerA, $this->learnerB), $got);
        $this->assertNotContains($foreign->id, $got);
        $this->assertNotContains($suspended->id, $got);
        $this->assertNotContains($revoked->id, $got);
        $this->assertSame(7, (int) $this->latest()->recipients_count);
    }

    public function test_staff_teacher_learner_and_guardian_audiences(): void
    {
        $this->announce($this->admin, ['audience' => 'staff']);
        $this->assertSame($this->ids($this->admin, $this->teacher, $this->teacher2), $this->recipients());

        $this->announce($this->admin, ['audience' => 'teachers']);
        $this->assertSame($this->ids($this->teacher, $this->teacher2), $this->recipients());

        $this->announce($this->admin, ['audience' => 'students']);
        $this->assertSame($this->ids($this->learnerA, $this->learnerB), $this->recipients());

        $this->announce($this->admin, ['audience' => 'guardians']);
        $this->assertSame($this->ids($this->parentA, $this->parentB), $this->recipients());
    }

    public function test_a_class_announcement_reaches_only_that_class_and_honours_the_who_flags(): void
    {
        $this->announce($this->admin, ['audience' => 'classes', 'class_ids' => [$this->class->id], 'include_students' => 1, 'include_guardians' => 1]);
        $this->assertSame($this->ids($this->learnerA, $this->parentA), $this->recipients());

        $this->announce($this->admin, ['audience' => 'classes', 'class_ids' => [$this->class->id], 'include_students' => 0, 'include_guardians' => 1]);
        $this->assertSame($this->ids($this->parentA), $this->recipients());

        $this->announce($this->admin, ['audience' => 'classes', 'class_ids' => [$this->class->id, $this->otherClass->id], 'include_students' => 1, 'include_guardians' => 0]);
        $this->assertSame($this->ids($this->learnerA, $this->learnerB), $this->recipients());

        $this->announce($this->admin, ['audience' => 'classes', 'class_ids' => [$this->class->id], 'include_students' => 0, 'include_guardians' => 0])
            ->assertSessionHas('error', 'err.ann.noRecipients');
        $this->announce($this->admin, ['audience' => 'classes', 'class_ids' => []])->assertSessionHas('error', 'err.ann.classRequired');
    }

    public function test_a_class_cannot_come_from_another_school(): void
    {
        $foreignClass = $this->classNamed('Foreign 1', $this->otherSchool());

        $this->announce($this->admin, ['audience' => 'classes', 'class_ids' => [$foreignClass->id]])->assertSessionHasErrors('class_ids.0');
        $this->assertNull($this->latest());
    }

    // ------------------------------------------------------------------ who may write

    public function test_a_teacher_can_announce_to_their_own_class_only(): void
    {
        $this->announce($this->teacher, ['audience' => 'classes', 'class_ids' => [$this->class->id]])->assertSessionHas('success', 'ok.ann.published');
        $this->assertSame($this->ids($this->learnerA, $this->parentA), $this->recipients());

        $this->announce($this->teacher, ['audience' => 'classes', 'class_ids' => [$this->otherClass->id]])->assertSessionHas('error', 'err.ann.classNotYours');
        $this->announce($this->teacher, ['audience' => 'school'])->assertSessionHas('error', 'err.ann.classOnly');
        $this->announce($this->teacher, ['audience' => 'guardians'])->assertSessionHas('error', 'err.ann.classOnly');
        $this->assertSame(1, $this->inSchool(fn () => Announcement::query()->count()));
    }

    public function test_a_teacher_with_no_classes_cannot_announce_and_families_cannot_write_at_all(): void
    {
        $this->announce($this->teacher2, ['audience' => 'classes', 'class_ids' => [$this->class->id]])->assertSessionHas('error', 'err.ann.classNotYours');

        foreach ([$this->parentA, $this->learnerA] as $family) {
            $this->announce($family, ['audience' => 'school'])->assertSessionHas('error', 'err.ann.notAllowed');
            $this->actAs($family)->get(route('announcements.create'))->assertForbidden();
        }
        $this->assertNull($this->latest());
    }

    public function test_authors_manage_only_their_own_unless_they_run_the_school(): void
    {
        $this->announce($this->teacher, ['audience' => 'classes', 'class_ids' => [$this->class->id]], 'draft');
        $draft = $this->latest();
        $this->assertSame('draft', $draft->status);

        // another teacher cannot see, edit or cancel it
        $this->actAs($this->teacher2)->get(route('announcements.edit', $draft->id))->assertNotFound();
        $this->actAs($this->teacher2)->put(route('announcements.update', $draft->id), ['title' => 'x', 'body' => 'y', 'audience' => 'classes', 'class_ids' => [$this->class->id], 'action' => 'draft'])
            ->assertSessionHas('error', 'err.ann.notAllowed');
        $this->actAs($this->teacher2)->post(route('announcements.cancel', $draft->id))->assertSessionHas('error', 'err.ann.notAllowed');

        // the author edits and publishes it
        $this->actAs($this->teacher)->put(route('announcements.update', $draft->id), ['title' => 'New title', 'body' => 'Body', 'audience' => 'classes', 'class_ids' => [$this->class->id], 'action' => 'publish'])
            ->assertSessionHas('success', 'ok.ann.published');
        $this->assertSame('published', $this->latest()->status);
        $this->assertSame('New title', $this->latest()->title);

        // published is frozen; cancel is the way out; the head can cancel a teacher's announcement
        $this->actAs($this->teacher)->put(route('announcements.update', $draft->id), ['title' => 'Again', 'body' => 'b', 'audience' => 'classes', 'class_ids' => [$this->class->id], 'action' => 'draft'])
            ->assertSessionHas('error', 'err.ann.locked');
        $this->actAs($this->admin)->post(route('announcements.cancel', $draft->id))->assertSessionHas('success', 'ok.ann.cancelled');
        $this->assertSame('cancelled', $this->latest()->status);
    }

    public function test_an_administrator_of_another_school_cannot_reach_the_announcement(): void
    {
        $this->announce($this->admin, ['audience' => 'school'], 'draft');
        $a = $this->latest();
        $outsider = $this->user('school_admin', $this->otherSchool());

        $this->actAs($outsider)->get(route('announcements.edit', $a->id))->assertNotFound();
        $this->actAs($outsider)->post(route('announcements.cancel', $a->id))->assertNotFound();
        $this->actAs($outsider)->put(route('announcements.update', $a->id), ['title' => 'x', 'body' => 'y', 'audience' => 'school', 'action' => 'publish'])->assertNotFound();
        $this->assertSame('draft', $this->latest()->status);
    }

    // ------------------------------------------------------------------ publish, schedule

    public function test_a_draft_notifies_nobody_until_it_is_published(): void
    {
        $this->announce($this->admin, ['audience' => 'school'], 'draft')->assertSessionHasNoErrors();

        $this->assertSame([], $this->recipients());
        $this->assertSame(0, $this->parentA->notifications()->count());

        $this->svc();
        $this->inSchool(fn () => $this->svc()->publish($this->latest()));
        $this->assertSame(1, $this->parentA->notifications()->count());
        $this->assertSame('announcement', $this->parentA->notifications()->first()->data['category']);
    }

    public function test_publishing_twice_does_not_notify_twice(): void
    {
        $this->announce($this->admin, ['audience' => 'guardians']);
        $a = $this->latest();

        $this->assertFalse($this->inSchool(fn () => $this->svc()->publish($a->fresh())));
        $this->assertSame(1, $this->parentA->notifications()->count());
        $this->assertCount(2, $this->recipients());
    }

    public function test_scheduling_needs_a_future_time_and_the_scheduler_publishes_when_due(): void
    {
        $this->announce($this->admin, ['audience' => 'guardians'], 'schedule')->assertSessionHas('error', 'err.ann.scheduleFuture');
        $this->announce($this->admin, ['audience' => 'guardians', 'publish_at' => now()->subHour()->toDateTimeString()], 'schedule')->assertSessionHas('error', 'err.ann.scheduleFuture');

        $this->announce($this->admin, ['audience' => 'guardians', 'publish_at' => now()->addHours(2)->toDateTimeString()], 'schedule')->assertSessionHas('success', 'ok.ann.scheduled');
        $later = $this->latest();
        $this->announce($this->admin, ['audience' => 'teachers', 'publish_at' => now()->addDay()->toDateTimeString()], 'schedule');
        $tomorrow = $this->latest();

        $this->assertSame(0, $this->svc()->publishDue(), 'Nothing is due yet.');
        $this->assertSame('scheduled', $this->inSchool(fn () => $later->fresh()->status));

        $this->travel(3)->hours();
        $this->assertSame(1, $this->svc()->publishDue());
        $this->assertSame('published', $this->inSchool(fn () => $later->fresh()->status));
        $this->assertSame('scheduled', $this->inSchool(fn () => $tomorrow->fresh()->status));
        $this->assertSame(1, $this->parentA->notifications()->count());
        $this->assertSame(0, $this->svc()->publishDue(), 'Running the scheduler again does nothing.');
        $this->assertSame(1, $this->parentA->notifications()->count());
    }

    public function test_the_scheduler_command_publishes_due_announcements_across_schools(): void
    {
        $this->announce($this->admin, ['audience' => 'guardians', 'publish_at' => now()->addHour()->toDateTimeString()], 'schedule');
        $this->travel(2)->hours();

        $this->artisan('schoolos:announcements:publish')->assertSuccessful();

        $this->assertSame('published', $this->latest()->status);
    }

    public function test_the_expiry_must_follow_the_publishing_time(): void
    {
        $this->announce($this->admin, ['audience' => 'school', 'expires_at' => now()->subDay()->toDateTimeString()])->assertSessionHas('error', 'err.ann.expiry');
        $this->assertNull($this->latest());
    }

    public function test_title_and_message_are_required(): void
    {
        $this->actAs($this->admin)->post(route('announcements.store'), ['title' => '', 'body' => '', 'audience' => 'school', 'action' => 'publish'])
            ->assertSessionHasErrors(['title', 'body']);
        $this->announce($this->admin, ['audience' => 'made_up'])->assertSessionHasErrors('audience');
    }

    // ------------------------------------------------------------------ channels

    public function test_in_app_is_always_on_and_e_mail_and_sms_only_when_chosen_and_allowed(): void
    {
        Notification::fake();
        $this->setSchoolSettings(['communications' => ['sms_enabled' => true]]);
        $this->parentA->unsetRelation('school');
        $this->parentA->forceFill(['phone' => '7123456'])->save();

        $this->announce($this->admin, ['audience' => 'guardians']);
        Notification::assertSentTo($this->parentA, SchoolAnnouncement::class, fn ($n, $channels) => $channels === ['database']);

        $this->announce($this->admin, ['audience' => 'guardians', 'channels' => ['email']]);
        Notification::assertSentTo($this->parentA, SchoolAnnouncement::class, fn ($n, $channels) => $channels === ['database', 'mail']);

        // SMS needs the person's opt-in as well: announcements are opt-in
        $this->announce($this->admin, ['audience' => 'guardians', 'channels' => ['email', 'sms']]);
        Notification::assertSentTo($this->parentA, SchoolAnnouncement::class, fn ($n, $channels) => ! in_array(SmsChannel::class, $channels, true));

        $this->actAs($this->parentA)->post(route('notifications.preferences.save'), ['prefs' => ['announcement' => ['sms' => 1]]]);
        $this->announce($this->admin, ['audience' => 'guardians', 'channels' => ['sms']]);
        Notification::assertSentTo($this->parentA, SchoolAnnouncement::class, fn ($n, $channels) => $channels === ['database', SmsChannel::class]);
    }

    // ------------------------------------------------------------------ feed and read receipts

    public function test_the_feed_shows_only_what_was_addressed_to_me_and_opening_it_marks_it_read(): void
    {
        $this->announce($this->admin, ['audience' => 'classes', 'class_ids' => [$this->class->id], 'title' => 'Grade 10A trip']);
        $a = $this->latest();

        $this->actAs($this->parentA)->get(route('announcements.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Announcements/Index')->has('feed', 1)->where('feed.0.title', 'Grade 10A trip')->where('feed.0.unread', true)->where('canAuthor', false));

        $this->actAs($this->parentA)->get(route('announcements.index'))
            ->assertInertia(fn (Assert $p) => $p->where('feed.0.unread', false));

        $this->actAs($this->parentB)->get(route('announcements.index'))->assertInertia(fn (Assert $p) => $p->has('feed', 0));

        $this->assertSame(0, $this->inSchool(fn () => $this->svc()->unreadCount($this->parentA)));
        $this->assertSame(1, $this->inSchool(fn () => $this->svc()->unreadCount($this->learnerA)));
        $this->assertSame(['recipients' => 2, 'read' => 1], $this->inSchool(fn () => $this->svc()->stats($a->fresh())));
    }

    public function test_cancelled_and_expired_announcements_leave_the_feed(): void
    {
        $this->announce($this->admin, ['audience' => 'guardians', 'title' => 'Cancelled one']);
        $cancelled = $this->latest();
        $this->announce($this->admin, ['audience' => 'guardians', 'title' => 'Short lived', 'expires_at' => now()->addHour()->toDateTimeString()]);
        $this->announce($this->admin, ['audience' => 'guardians', 'title' => 'Stays']);

        $this->actAs($this->admin)->post(route('announcements.cancel', $cancelled->id));
        $this->travel(2)->hours();

        $titles = $this->inSchool(fn () => $this->svc()->feed($this->parentA)->pluck('title')->all());
        $this->assertSame(['Stays'], $titles);
    }

    public function test_the_author_sees_reach_and_a_reader_does_not_see_the_management_list(): void
    {
        $this->announce($this->admin, ['audience' => 'guardians']);

        $this->actAs($this->admin)->get(route('announcements.index'))
            ->assertInertia(fn (Assert $p) => $p->where('canAuthor', true)->has('mine', 1)->where('mine.0.stats.recipients', 2)->where('mine.0.editable', false));

        $this->actAs($this->parentA)->get(route('announcements.index'))->assertInertia(fn (Assert $p) => $p->has('mine', 0));
    }
}
