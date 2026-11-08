<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Part 19.3: conversations, direct messages, class groups, attachments, read status, reporting, moderation, isolation. */
class Phase8MessagingTest extends Phase8TestCase
{
    private User $teacher2;

    private User $parentA;

    private User $parentB;

    private User $learnerA;

    private Student $childA;

    private Student $childB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->teacher2 = $this->user('teacher');                 // teaches nobody
        $this->childA = $this->student($this->class, first: 'Ada');
        $this->childB = $this->student($this->otherClass, first: 'Bea');
        $this->parentA = $this->guardian([$this->childA])->user;
        $this->parentB = $this->guardian([$this->childB])->user;
        $learner = $this->student($this->class, login: true, first: 'Lia');
        $this->learnerA = $learner->user;
    }

    private function svc(): MessagingService
    {
        return app(MessagingService::class);
    }

    private function conversation(): Conversation
    {
        return $this->inSchool(fn () => Conversation::query()->latest('id')->firstOrFail());
    }

    private function send(User $from, User $to, string $body = 'Hello there', array $extra = [])
    {
        return $this->actAs($from)->post(route('messages.store'), ['recipient_id' => $to->id, 'body' => $body] + $extra);
    }

    // ------------------------------------------------------------------ who may talk to whom

    public function test_the_permission_matrix(): void
    {
        $this->inSchool(function () {
            $m = $this->svc();
            $finance = $this->user('finance_officer');

            // staff -> families: only the classes they teach, unless leadership or office staff
            $this->assertTrue($m->canMessage($this->teacher, $this->parentA));
            $this->assertFalse($m->canMessage($this->teacher, $this->parentB));
            $this->assertFalse($m->canMessage($this->teacher2, $this->parentA));
            $this->assertTrue($m->canMessage($this->admin, $this->parentB));
            $this->assertTrue($m->canMessage($finance, $this->parentB), 'Office staff reach any guardian (fee queries).');
            $this->assertTrue($m->canMessage($this->teacher, $this->learnerA));
            $this->assertFalse($m->canMessage($this->teacher2, $this->learnerA));

            // staff -> staff
            $this->assertTrue($m->canMessage($this->teacher, $this->teacher2));

            // guardians -> only their children's teachers and office contacts
            $this->assertTrue($m->canMessage($this->parentA, $this->teacher));
            $this->assertFalse($m->canMessage($this->parentA, $this->teacher2));
            $this->assertTrue($m->canMessage($this->parentA, $this->admin));
            $this->assertTrue($m->canMessage($this->parentA, $finance));
            $this->assertFalse($m->canMessage($this->parentA, $this->parentB), 'Families never message each other.');
            $this->assertFalse($m->canMessage($this->parentA, $this->learnerA));

            // learners may message school staff and classmates; never guardians or other classes
            $this->assertTrue($m->canMessage($this->learnerA, $this->teacher));
            $this->assertTrue($m->canMessage($this->learnerA, $this->teacher2), 'Learners may write to any school staff, not only class teachers.');
            $this->assertTrue($m->canMessage($this->learnerA, $this->admin));
            $this->assertFalse($m->canMessage($this->learnerA, $this->parentA));
            $classmate = $this->student($this->class, login: true, first: 'Omar')->user;
            $otherClassLearner = $this->student($this->otherClass, login: true, first: 'Bea')->user;
            $this->assertTrue($m->canMessage($this->learnerA, $classmate));
            $this->assertFalse($m->canMessage($this->learnerA, $otherClassLearner));
            $this->assertTrue($m->canStart($this->learnerA, $this->teacher));
            config(['schoolos.messaging.students_can_start' => false]);
            $this->assertTrue($m->canMessage($this->learnerA, $this->teacher), 'The start flag does not remove an existing relationship.');
            $this->assertFalse($m->canStart($this->learnerA, $this->teacher));
            config(['schoolos.messaging.students_can_start' => true]);

            // nobody messages themselves
            $this->assertFalse($m->canMessage($this->teacher, $this->teacher));
        });
    }

    public function test_suspended_accounts_other_schools_and_disabled_features_block_messaging(): void
    {
        $other = $this->otherSchool();
        $foreign = $this->user('teacher', $other);

        $this->inSchool(function () use ($foreign) {
            $m = $this->svc();
            $this->assertFalse($m->canMessage($this->teacher, $foreign));
            $this->assertFalse($m->canMessage($foreign, $this->teacher));

            $this->parentA->forceFill(['suspended_at' => now()])->save();
            $this->assertFalse($m->canMessage($this->teacher, $this->parentA->fresh()));
            $this->parentA->forceFill(['suspended_at' => null])->save();
            $this->assertTrue($m->canMessage($this->teacher, $this->parentA->fresh()));
        });

        $this->setSchoolSettings(['communications' => ['parent_messaging' => false]]);
        $this->inSchool(function () {
            $this->assertFalse($this->svc()->canMessage($this->teacher, $this->parentA->fresh()));
            $this->assertFalse($this->svc()->canMessage($this->parentA->fresh(), $this->teacher));
            $this->assertTrue($this->svc()->canMessage($this->teacher, $this->teacher2));
        }, $this->school->fresh());
    }

    public function test_a_blocked_recipient_id_from_the_browser_creates_nothing(): void
    {
        $other = $this->otherSchool();
        $foreign = $this->user('teacher', $other);

        $this->send($this->teacher, $foreign)->assertSessionHas('error', 'err.msg.notAllowed');
        $this->send($this->teacher, $this->parentB)->assertSessionHas('error', 'err.msg.notAllowed');
        $this->send($this->parentA, $this->parentB)->assertSessionHas('error', 'err.msg.notAllowed');
        $this->send($this->parentA, $this->teacher2)->assertSessionHas('error', 'err.msg.notAllowed');

        $this->assertSame(0, $this->inSchool(fn () => Conversation::query()->count()));
    }

    // ------------------------------------------------------------------ direct conversations

    public function test_a_teacher_and_a_parent_hold_a_conversation_with_read_status_and_notifications(): void
    {
        $this->send($this->teacher, $this->parentA, 'Ada did very well this week.', ['student_id' => $this->childA->id, 'subject' => 'Ada progress'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $c = $this->conversation();

        $this->assertSame('direct', $c->type);
        $this->assertSame($this->childA->id, $c->student_id);
        $this->assertSame(2, $this->inSchool(fn () => $c->participants()->count()));

        // the parent is notified in-app (and by e-mail, verified address) and sees it unread
        $this->assertSame(1, $this->parentA->notifications()->where('type', NewMessageNotification::class)->count());
        $this->assertSame(1, $this->inSchool(fn () => $this->svc()->unreadCount($this->parentA)));
        $this->assertSame(0, $this->inSchool(fn () => $this->svc()->unreadCount($this->teacher)), 'The sender has nothing unread.');

        $this->actAs($this->parentA)->get(route('messages.index'))->assertInertia(fn ($p) => $p->component('Messages/Index')
            ->has('conversations', 1)->where('conversations.0.unread', true)->where('conversations.0.title', 'Ada progress'));

        $this->actAs($this->parentA)->get(route('messages.show', $c->id))->assertOk()->assertInertia(fn ($p) => $p->component('Messages/Show')
            ->has('messages', 1)->where('canReply', true)->where('moderatorView', false));
        $this->assertSame(0, $this->inSchool(fn () => $this->svc()->unreadCount($this->parentA)));
        $this->assertSame(0, $this->parentA->unreadNotifications()->count(), 'Opening the conversation clears its alert.');

        // reply -> teacher notified; a second reply while the first alert is unread adds no second alert
        $this->actAs($this->parentA)->post(route('messages.reply', $c->id), ['body' => 'Thank you!'])->assertSessionHasNoErrors();
        $this->actAs($this->parentA)->post(route('messages.reply', $c->id), ['body' => 'One more thing.'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->teacher->unreadNotifications()->where('type', NewMessageNotification::class)->count());
        $this->assertSame(3, $this->inSchool(fn () => Message::query()->where('conversation_id', $c->id)->count()));

        // an existing direct thread about the same child is reused
        $this->send($this->teacher, $this->parentA, 'Another note', ['student_id' => $this->childA->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inSchool(fn () => Conversation::query()->count()));
    }

    public function test_the_child_a_conversation_is_about_must_belong_to_the_guardian(): void
    {
        $this->send($this->teacher, $this->parentA, 'About someone else', ['student_id' => $this->childB->id])->assertSessionHasNoErrors();

        $this->assertNull($this->conversation()->student_id, 'A child not linked to the guardian is dropped, not trusted.');
    }

    public function test_only_participants_can_open_a_conversation(): void
    {
        $this->send($this->teacher, $this->parentA, 'Private')->assertSessionHasNoErrors();
        $c = $this->conversation();
        $other = $this->otherSchool();

        foreach ([$this->parentB, $this->teacher2, $this->learnerA, $this->admin, $this->user('teacher', $other)] as $stranger) {
            $this->actAs($stranger)->get(route('messages.show', $c->id))->assertNotFound();
            $this->actAs($stranger)->post(route('messages.reply', $c->id), ['body' => 'let me in'])->assertNotFound();
        }

        $this->assertSame(1, $this->inSchool(fn () => Message::query()->count()));

        // and the inbox lists only the caller's own conversations
        $this->actAs($this->parentB)->get(route('messages.index'))->assertInertia(fn ($p) => $p->has('conversations', 0));
    }

    public function test_a_reply_is_refused_once_the_other_side_is_no_longer_reachable(): void
    {
        $this->send($this->teacher, $this->parentA)->assertSessionHasNoErrors();
        $c = $this->conversation();

        // the child moves to a class this teacher does not teach: the thread is read-only for the parent
        $this->childA->forceFill(['school_class_id' => $this->otherClass->id])->save();
        $this->actAs($this->parentA)->post(route('messages.reply', $c->id), ['body' => 'Still there?'])->assertSessionHas('error', 'err.msg.notAllowed');
    }

    public function test_the_body_is_validated_and_stored_as_plain_text(): void
    {
        $this->send($this->teacher, $this->parentA, '')->assertSessionHasErrors('body');
        $this->send($this->teacher, $this->parentA, str_repeat('x', 4001))->assertSessionHasErrors('body');

        $this->send($this->teacher, $this->parentA, '<script>alert(1)</script> hi')->assertSessionHasNoErrors();
        $stored = $this->inSchool(fn () => Message::query()->firstOrFail()->body);
        $this->assertSame('<script>alert(1)</script> hi', $stored, 'Stored verbatim; the React page renders it as text, never as HTML.');
    }

    // ------------------------------------------------------------------ class groups

    public function test_a_teacher_messages_the_guardians_of_their_own_class_only(): void
    {
        $sibling = $this->student($this->class, first: 'Cy');
        $this->guardian([$sibling]);

        $this->actAs($this->teacher)->post(route('messages.group.store'), [
            'class_id' => $this->class->id, 'subject' => 'Trip', 'audience' => 'guardians', 'replies_allowed' => 0, 'body' => 'School trip on Friday.',
        ])->assertSessionHasNoErrors();

        $c = $this->conversation();
        $members = $this->inSchool(fn () => $c->participants()->pluck('user_id')->all());
        $this->assertContains($this->parentA->id, $members);
        $this->assertNotContains($this->parentB->id, $members, 'Another class is not included.');
        $this->assertNotContains($this->learnerA->id, $members, 'Learners are only included when asked for.');
        $this->assertCount(3, $members);   // teacher + two guardians

        // read-only group: guardians cannot answer, staff can
        $this->actAs($this->parentA)->post(route('messages.reply', $c->id), ['body' => 'Can I come?'])->assertSessionHas('error', 'err.msg.notAllowed');
        $this->actAs($this->teacher)->post(route('messages.reply', $c->id), ['body' => 'Reminder: 8am.'])->assertSessionHasNoErrors();

        // families do not learn who else is in the group
        $this->actAs($this->parentA)->get(route('messages.show', $c->id))->assertInertia(fn ($p) => $p
            ->where('conversation.with.names', $this->teacher->name)->where('canReply', false));

        // a teacher cannot address a class they do not teach; leadership can
        $this->actAs($this->teacher)->post(route('messages.group.store'), [
            'class_id' => $this->otherClass->id, 'subject' => 'Hi', 'audience' => 'guardians', 'replies_allowed' => 1, 'body' => 'x',
        ])->assertSessionHas('error', 'err.msg.notAllowed');
        $this->actAs($this->admin)->post(route('messages.group.store'), [
            'class_id' => $this->otherClass->id, 'subject' => 'Hi', 'audience' => 'guardians', 'replies_allowed' => 1, 'body' => 'Welcome back.',
        ])->assertSessionHasNoErrors();
        $this->actAs($this->parentB)->post(route('messages.reply', $this->conversation()->id), ['body' => 'Thanks'])->assertSessionHasNoErrors();
    }

    public function test_a_group_for_learners_and_guardians_includes_both_and_honours_the_learner_switch(): void
    {
        $this->actAs($this->teacher)->post(route('messages.group.store'), [
            'class_id' => $this->class->id, 'subject' => 'Exams', 'audience' => 'both', 'replies_allowed' => 1, 'body' => 'Mock exams next week.',
        ])->assertSessionHasNoErrors();
        $members = $this->inSchool(fn () => $this->conversation()->participants()->pluck('user_id')->all());
        $this->assertContains($this->learnerA->id, $members);
        $this->assertContains($this->parentA->id, $members);

        // a learner added to a group may answer (replies are on); starting stays gated by students_can_start
        $this->actAs($this->learnerA)->post(route('messages.reply', $this->conversation()->id), ['body' => 'Understood'])->assertSessionHasNoErrors();
        config(['schoolos.messaging.students_can_start' => false]);
        $this->send($this->learnerA, $this->teacher, 'Hi sir')->assertSessionHas('error', 'err.msg.notAllowed');
        config(['schoolos.messaging.students_can_start' => true]);

        // learners switched off for the school: not added
        $this->setSchoolSettings(['communications' => ['student_messaging' => false]]);
        $this->actAs($this->teacher)->post(route('messages.group.store'), [
            'class_id' => $this->class->id, 'subject' => 'More', 'audience' => 'students', 'replies_allowed' => 1, 'body' => 'x',
        ])->assertSessionHas('error', 'err.msg.noMembers');
    }

    // ------------------------------------------------------------------ attachments

    public function test_attachments_are_private_validated_and_checked_on_download(): void
    {
        $file = UploadedFile::fake()->create('homework.pdf', 20, 'application/pdf');
        $this->send($this->teacher, $this->parentA, 'See attached', ['attachments' => [$file]])->assertSessionHasNoErrors();

        $attachment = $this->inSchool(fn () => \App\Models\MessageAttachment::query()->firstOrFail());
        $this->assertStringStartsWith('messages/'.$this->school->id.'/', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertNotSame('homework.pdf', basename($attachment->path), 'Stored under a random name.');

        $this->actAs($this->parentA)->get(route('messages.attachments.show', $attachment->id))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actAs($this->teacher)->get(route('messages.attachments.show', $attachment->id))->assertOk();
        foreach ([$this->parentB, $this->teacher2, $this->admin, $this->learnerA] as $stranger) {
            $this->actAs($stranger)->get(route('messages.attachments.show', $attachment->id))->assertNotFound();
        }

        // dangerous types, oversize files and too many files are refused
        $this->send($this->teacher, $this->parentA, 'x', ['attachments' => [UploadedFile::fake()->create('run.exe', 5)]])->assertSessionHasErrors('attachments.0');
        $this->send($this->teacher, $this->parentA, 'x', ['attachments' => [UploadedFile::fake()->create('big.pdf', 9000, 'application/pdf')]])->assertSessionHasErrors('attachments.0');
        $four = array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 5, 'application/pdf'), range(1, 4));
        $this->send($this->teacher, $this->parentA, 'x', ['attachments' => $four])->assertSessionHasErrors('attachments');
    }

    // ------------------------------------------------------------------ reporting and moderation

    private function threadWithMessageFromTeacher(): array
    {
        $file = UploadedFile::fake()->create('note.pdf', 10, 'application/pdf');
        $this->send($this->teacher, $this->parentA, 'An inappropriate remark', ['attachments' => [$file]])->assertSessionHasNoErrors();

        return [$this->conversation(), $this->inSchool(fn () => Message::query()->latest('id')->firstOrFail())];
    }

    public function test_a_participant_can_report_a_message_and_moderators_are_alerted(): void
    {
        [$c, $m] = $this->threadWithMessageFromTeacher();

        $this->actAs($this->parentA)->post(route('messages.report', $m->id), ['reason' => 'Rude'])->assertSessionHas('success', 'ok.msg.reported');
        $this->assertSame(1, $this->inSchool(fn () => MessageReport::query()->where('status', 'open')->count()));
        $this->assertTrue($this->admin->notifications()->where('data', 'like', '%reported%')->exists());

        // reporting twice does not duplicate; the author cannot report their own message; strangers cannot report
        $this->actAs($this->parentA)->post(route('messages.report', $m->id), ['reason' => 'Rude'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inSchool(fn () => MessageReport::query()->count()));
        $this->actAs($this->teacher)->post(route('messages.report', $m->id), ['reason' => 'x'])->assertSessionHas('error', 'err.msg.notAllowed');
        $this->actAs($this->parentB)->post(route('messages.report', $m->id), ['reason' => 'x'])->assertNotFound();
        $this->actAs($this->parentA)->post(route('messages.report', $m->id), ['reason' => ''])->assertSessionHasErrors('reason');
    }

    public function test_moderators_see_only_reported_conversations_and_the_view_is_audited(): void
    {
        [$c, $m] = $this->threadWithMessageFromTeacher();

        // before any report a moderator is a stranger
        $this->actAs($this->admin)->get(route('messages.show', $c->id))->assertNotFound();

        $this->actAs($this->parentA)->post(route('messages.report', $m->id), ['reason' => 'Rude']);
        $this->actAs($this->admin)->get(route('messages.moderation'))->assertOk()->assertInertia(fn ($p) => $p->component('Messages/Moderation')->has('reports', 1));
        $this->actAs($this->admin)->get(route('messages.show', $c->id))->assertOk()->assertInertia(fn ($p) => $p
            ->where('moderatorView', true)->where('canReply', false));
        $this->assertTrue(ActivityLog::query()->where('school_id', $this->school->id)->where('event', 'messages.moderator_view')->exists());

        // ordinary staff cannot use the moderation tools at all
        $this->actAs($this->teacher)->get(route('messages.moderation'))->assertForbidden();
        $this->actAs($this->teacher)->post(route('messages.hide', $m->id), ['reason' => 'x'])->assertForbidden();
    }

    public function test_hiding_a_reported_message_removes_it_and_its_files_from_the_participants(): void
    {
        [$c, $m] = $this->threadWithMessageFromTeacher();
        $attachment = $this->inSchool(fn () => \App\Models\MessageAttachment::query()->firstOrFail());

        // nothing to hide until someone has reported it
        $this->actAs($this->admin)->post(route('messages.hide', $m->id), ['reason' => 'x'])->assertSessionHas('error', 'err.msg.notAllowed');

        $this->actAs($this->parentA)->post(route('messages.report', $m->id), ['reason' => 'Rude']);
        $this->actAs($this->admin)->post(route('messages.hide', $m->id), ['reason' => 'Policy breach'])->assertSessionHas('success', 'ok.msg.hidden');

        $this->assertSame('actioned', $this->inSchool(fn () => MessageReport::query()->firstOrFail()->status));
        $this->actAs($this->parentA)->get(route('messages.show', $c->id))->assertInertia(fn ($p) => $p
            ->where('messages.0.hidden', true)->where('messages.0.body', null)->where('messages.0.attachments', []));
        $this->actAs($this->parentA)->get(route('messages.attachments.show', $attachment->id))->assertNotFound();
        $this->actAs($this->teacher)->get(route('messages.attachments.show', $attachment->id))->assertNotFound();
        $this->actAs($this->admin)->get(route('messages.attachments.show', $attachment->id))->assertOk();
        // moderators still read the original text
        $this->actAs($this->admin)->get(route('messages.show', $c->id))->assertInertia(fn ($p) => $p->where('messages.0.body', 'An inappropriate remark'));
    }

    public function test_dismissing_a_report_keeps_the_message(): void
    {
        [$c, $m] = $this->threadWithMessageFromTeacher();
        $this->actAs($this->parentA)->post(route('messages.report', $m->id), ['reason' => 'Mistake']);
        $report = $this->inSchool(fn () => MessageReport::query()->firstOrFail());

        $this->actAs($this->admin)->post(route('messages.dismiss', $report->id))->assertSessionHas('success', 'ok.msg.dismissed');
        $this->assertSame('dismissed', $this->inSchool(fn () => MessageReport::query()->firstOrFail()->status));
        $this->assertNull($this->inSchool(fn () => Message::query()->find($m->id)->hidden_at));
    }

    public function test_a_locked_conversation_accepts_no_more_messages(): void
    {
        [$c, $m] = $this->threadWithMessageFromTeacher();

        // the parent is neither owner nor moderator
        $this->actAs($this->parentA)->post(route('messages.lock', $c->id))->assertSessionHas('error', 'err.msg.notAllowed');
        // the owner (teacher) can lock their own thread
        $this->actAs($this->teacher)->post(route('messages.lock', $c->id))->assertSessionHas('success', 'ok.msg.locked');
        $this->actAs($this->parentA)->post(route('messages.reply', $c->id), ['body' => 'hello?'])->assertSessionHas('error', 'err.msg.locked');
        $this->actAs($this->teacher)->post(route('messages.reply', $c->id), ['body' => 'hello?'])->assertSessionHas('error', 'err.msg.locked');

        $this->actAs($this->teacher)->post(route('messages.unlock', $c->id))->assertSessionHas('success', 'ok.msg.unlocked');
        $this->actAs($this->parentA)->post(route('messages.reply', $c->id), ['body' => 'back'])->assertSessionHasNoErrors();
    }

    public function test_muting_stops_alerts_but_not_delivery_of_the_message(): void
    {
        $this->send($this->teacher, $this->parentA, 'First')->assertSessionHasNoErrors();
        $c = $this->conversation();
        $this->actAs($this->parentA)->get(route('messages.show', $c->id));        // reads it, clears the alert
        $this->actAs($this->parentA)->post(route('messages.mute', $c->id), ['muted' => 1])->assertSessionHasNoErrors();

        $this->travel(5)->seconds();                                    // later than the moment she read it
        $this->actAs($this->teacher)->post(route('messages.reply', $c->id), ['body' => 'Second'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->parentA->unreadNotifications()->count());
        $this->assertSame(1, $this->inSchool(fn () => $this->svc()->unreadCount($this->parentA)), 'Still shown as unread in the inbox.');
    }

    public function test_the_contact_list_only_offers_people_the_user_may_message(): void
    {
        $this->inSchool(function () {
            $parent = $this->svc()->contacts($this->parentA);
            $ids = collect($parent['staff'])->pluck('id')->all();
            $this->assertContains($this->teacher->id, $ids);
            $this->assertContains($this->admin->id, $ids);
            $this->assertNotContains($this->teacher2->id, $ids);
            $this->assertSame([], $parent['colleagues']);

            $staff = $this->svc()->contacts($this->teacher);
            $guardianIds = collect($staff['families'])->flatMap(fn ($f) => collect($f['guardians'])->pluck('id'))->all();
            $this->assertContains($this->parentA->id, $guardianIds);
            $this->assertNotContains($this->parentB->id, $guardianIds);
            $this->assertNotContains($this->teacher->id, collect($staff['colleagues'])->pluck('id')->all());

            $this->assertSame([], $this->svc()->contacts($this->learnerA)['families']);
            $learnerStaff = collect($this->svc()->contacts($this->learnerA)['staff'])->pluck('id')->all();
            $this->assertContains($this->teacher->id, $learnerStaff);
            $this->assertContains($this->admin->id, $learnerStaff);
            $this->assertContains($this->teacher2->id, $learnerStaff);
        });
    }

    public function test_a_learner_can_message_teachers_staff_and_classmates(): void
    {
        $classmate = $this->inSchool(fn () => $this->student($this->class, login: true, first: 'Omar')->user);
        $otherClassLearner = $this->inSchool(fn () => $this->student($this->otherClass, login: true, first: 'Kebba')->user);
        $finance = $this->inSchool(fn () => $this->user('finance_officer'));

        $this->actAs($this->learnerA)->get(route('messages.index'))->assertOk()->assertInertia(fn ($p) => $p
            ->component('Messages/Index')->where('canStart', true));

        $this->actAs($this->learnerA)->get(route('messages.create'))->assertOk()->assertInertia(fn ($p) => $p
            ->component('Messages/Create')
            ->where('kind', 'student')
            ->has('contacts.staff')
            ->has('contacts.classmates'));

        $this->send($this->learnerA, $this->teacher, 'Please explain yesterday’s homework.')
            ->assertSessionHasNoErrors()->assertRedirect();
        $teacherThread = $this->conversation();
        $this->assertSame('direct', $teacherThread->type);

        $this->send($this->learnerA, $finance, 'Where do I collect my fee receipt?')
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->send($this->learnerA, $classmate, 'Can we revise together after school?')
            ->assertSessionHasNoErrors()->assertRedirect();
        $classmateThread = $this->conversation();
        $this->actAs($classmate)->post(route('messages.reply', $classmateThread->id), ['body' => 'Yes, library at 4.'])
            ->assertSessionHasNoErrors();

        $this->send($this->learnerA, $otherClassLearner, 'Hi')->assertSessionHas('error', 'err.msg.notAllowed');
        $this->send($this->learnerA, $this->parentA, 'Hi')->assertSessionHas('error', 'err.msg.notAllowed');

        config(['schoolos.messaging.students_can_start' => false]);
        $this->send($this->learnerA, $this->teacher2, 'Hello')->assertSessionHas('error', 'err.msg.notAllowed');
        $this->actAs($this->learnerA)->post(route('messages.reply', $teacherThread->id), ['body' => 'Thank you sir.'])
            ->assertSessionHasNoErrors();
    }
}
