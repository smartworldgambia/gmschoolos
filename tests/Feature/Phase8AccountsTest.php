<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\StaffInvitation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Support\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/** Part 16: invite, assign roles / school / classes, verify, activate, reset, suspend, revoke. */
class Phase8AccountsTest extends Phase8TestCase
{
    private const PASSWORD = 'Another-Str0ng-Pass!';

    private function lastInviteToken(): string
    {
        $url = null;
        Notification::assertSentOnDemand(StaffInvitationNotification::class, function (StaffInvitationNotification $n) use (&$url) {
            $url = $n->acceptUrl;

            return true;
        });

        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function accept(string $token): \Illuminate\Testing\TestResponse
    {
        auth()->logout();
        app(TenantContext::class)->clear();
        $this->flushSession();

        return $this->post(route('invitations.accept', $token), ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD]);
    }

    private function platform(callable $fn): mixed
    {
        return app(TenantContext::class)->runAsPlatform($fn);
    }

    public function test_a_learner_invitation_creates_a_student_login_linked_to_their_record(): void
    {
        Notification::fake();
        $student = $this->student();

        $this->actAs($this->admin)->post(route('accounts.invite.student'), ['student_id' => $student->id, 'email' => 'awa@alpha.test'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'ok.acct.invited');

        $token = $this->lastInviteToken();
        $invitation = $this->platform(fn () => StaffInvitation::query()->firstOrFail());
        $this->assertSame('student', $invitation->kind);
        $this->assertSame('student', $invitation->role);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);

        $this->accept($token)->assertRedirect(route('dashboard'));

        $this->platform(function () use ($student) {
            $user = User::query()->where('email', 'awa@alpha.test')->firstOrFail();
            $this->assertSame($this->school->id, $user->school_id);
            $this->assertTrue($user->hasRole('student'));
            $this->assertNotNull($user->email_verified_at, 'Opening the e-mailed link verifies the address.');
            $this->assertSame($user->id, Student::query()->find($student->id)->user_id);
        });

        // the link works once
        auth()->logout();
        $this->flushSession();
        $this->get(route('invitations.show', $token))->assertStatus(410);
    }

    public function test_a_learner_who_already_has_a_login_or_belongs_to_another_school_cannot_be_invited(): void
    {
        Notification::fake();
        $with = $this->student(login: true);
        $other = $this->otherSchool();
        $foreign = $this->student(school: $other, class: $this->classNamed('Beta 1', $other));

        $this->actAs($this->admin)->post(route('accounts.invite.student'), ['student_id' => $with->id, 'email' => 'x@alpha.test'])->assertSessionHasErrors('student');
        $this->actAs($this->admin)->post(route('accounts.invite.student'), ['student_id' => $foreign->id, 'email' => 'y@alpha.test'])->assertSessionHasErrors('student_id');

        Notification::assertNothingSent();
    }

    public function test_a_guardian_invitation_links_the_account_and_the_parent_only_sees_linked_children(): void
    {
        Notification::fake();
        $mine = $this->student(first: 'Mine');
        $notMine = $this->student(first: 'Other');
        $guardian = $this->guardian([$mine], login: false, phone: '7123456');

        $this->actAs($this->admin)->post(route('accounts.invite.guardian'), ['guardian_id' => $guardian->id])->assertSessionHasNoErrors();
        $this->accept($this->lastInviteToken())->assertRedirect(route('dashboard'));

        $user = $this->platform(fn () => User::query()->where('email', $guardian->email)->firstOrFail());
        $this->assertTrue($user->hasRole('parent'));
        $this->assertSame('7123456', $user->phone, 'The guardian phone is copied so SMS can reach them.');
        $this->assertSame($user->id, $this->platform(fn () => Guardian::query()->find($guardian->id))->user_id);

        $this->actAs($user->fresh())->get(route('parent.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Parent/Dashboard')->has('children', 1)->where('children.0.id', $mine->id));

        // the other child's record is not reachable by this parent
        $this->actAs($user->fresh())->get(route('students.show', $notMine->id))->assertForbidden();
    }

    public function test_guardian_invitations_respect_the_school_parent_access_switch(): void
    {
        Notification::fake();
        $this->setSchoolSettings(['parent_access' => ['enabled' => false]]);
        $guardian = $this->guardian([$this->student()], login: false);

        $this->actAs($this->admin)->post(route('accounts.invite.guardian'), ['guardian_id' => $guardian->id])->assertSessionHasErrors('guardian');
        Notification::assertNothingSent();
    }

    public function test_a_teacher_invitation_hands_over_the_chosen_allocation_and_creates_a_staff_record(): void
    {
        Notification::fake();
        $subject = $this->subject('Biology');
        $free = ClassSubject::query()->create(['school_id' => $this->school->id, 'school_class_id' => $this->otherClass->id, 'subject_id' => $subject->id, 'periods_per_week' => 2]);

        $this->actAs($this->admin)->post(route('accounts.invite.staff'), [
            'name' => 'Musa Ceesay', 'email' => 'musa@alpha.test', 'role' => 'teacher', 'class_subject_ids' => [$free->id],
        ])->assertSessionHasNoErrors();
        $this->accept($this->lastInviteToken())->assertRedirect(route('dashboard'));

        $this->platform(function () use ($free) {
            $user = User::query()->where('email', 'musa@alpha.test')->firstOrFail();
            $this->assertTrue($user->hasRole('teacher'));
            $this->assertSame($user->id, ClassSubject::query()->find($free->id)->teacher_id);
            $this->assertSame(1, Staff::query()->where('user_id', $user->id)->count());
        });
    }

    public function test_an_allocation_from_another_school_cannot_be_smuggled_into_an_invitation(): void
    {
        Notification::fake();
        $other = $this->otherSchool();
        $foreignClass = $this->classNamed('Beta 1', $other);
        $foreign = ClassSubject::query()->withoutGlobalScopes()->create([
            'school_id' => $other->id, 'school_class_id' => $foreignClass->id,
            'subject_id' => \App\Models\Subject::query()->withoutGlobalScopes()->create(['school_id' => $other->id, 'name' => 'Art', 'code' => 'ART'])->id,
        ]);

        $this->actAs($this->admin)->post(route('accounts.invite.staff'), [
            'name' => 'Musa', 'email' => 'musa@alpha.test', 'role' => 'teacher', 'class_subject_ids' => [$foreign->id],
        ])->assertSessionHasErrors('class_subject_ids.0');
    }

    public function test_staff_invitations_can_never_grant_privileged_roles(): void
    {
        Notification::fake();

        foreach (['school_admin', 'school_owner', 'platform_admin', 'super_admin', 'student', 'parent'] as $i => $role) {
            $this->actAs($this->admin)->post(route('accounts.invite.staff'), ['name' => 'X', 'email' => "x{$i}@alpha.test", 'role' => $role])->assertSessionHasErrors('role');
        }

        Notification::assertNothingSent();
        $this->assertSame(0, $this->platform(fn () => StaffInvitation::query()->count()));
    }

    public function test_only_account_managers_can_open_the_accounts_screen(): void
    {
        $this->actAs($this->admin)->get(route('accounts.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Accounts/Index'));
        $this->actAs($this->teacher)->get(route('accounts.index'))->assertForbidden();
        $this->actAs($this->user('parent'))->get(route('accounts.index'))->assertForbidden();
        $this->actAs($this->user('student'))->post(route('accounts.suspend', $this->teacher->id), ['reason' => 'x'])->assertForbidden();
    }

    public function test_suspending_an_account_stops_login_and_ends_the_live_session(): void
    {
        $user = $this->teacher;

        $this->actAs($user)->get(route('teacher.dashboard'))->assertOk();

        $this->actAs($this->admin)->post(route('accounts.suspend', $user->id), ['reason' => 'Under review'])->assertSessionHas('success', 'ok.acct.suspended');
        $this->assertNotNull($user->fresh()->suspended_at);

        // the open session is refused on its next request
        $this->actAs($user->fresh())->get(route('teacher.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        // and a fresh login is refused with a translated code
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors(['email' => 'err.acct.suspended']);
        $this->assertGuest();

        // reinstating restores access
        $this->actAs($this->admin)->post(route('accounts.reinstate', $user->id))->assertSessionHas('success', 'ok.acct.reinstated');
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();

        $events = ActivityLog::query()->where('school_id', $this->school->id)->pluck('event')->all();
        $this->assertContains('account.suspended', $events);
        $this->assertContains('account.reinstated', $events);
    }

    public function test_revoking_access_removes_roles_and_restoring_gives_back_only_a_fitting_role(): void
    {
        $learner = $this->student(login: true);
        $user = $learner->user;

        $this->actAs($this->admin)->post(route('accounts.revoke', $user->id), ['reason' => 'Left school'])->assertSessionHas('success', 'ok.acct.revoked');
        $user = $user->fresh();
        $this->assertSame([], $user->getRoleNames()->all());
        $this->assertNotNull($user->access_revoked_at);
        $this->assertTrue($user->isBlocked());

        // a revoked account cannot simply be "reinstated"
        $this->actAs($this->admin)->post(route('accounts.reinstate', $user->id))->assertSessionHas('error', 'err.acct.revoked');

        // a learner can only come back as a learner, never as a teacher or administrator
        foreach (['teacher', 'school_admin', 'parent'] as $role) {
            $this->actAs($this->admin)->post(route('accounts.restore', $user->id), ['role' => $role])->assertSessionHas('error', 'err.acct.roleNotAllowed');
        }
        $this->actAs($this->admin)->post(route('accounts.restore', $user->id), ['role' => 'student'])->assertSessionHas('success', 'ok.acct.restored');
        $this->assertTrue($user->fresh()->hasRole('student'));
        $this->assertFalse($user->fresh()->isBlocked());
        // the learner's record was never deleted
        $this->assertSame($user->id, Student::query()->find($learner->id)->user_id);
    }

    public function test_protected_accounts_cannot_be_suspended_and_nobody_acts_on_themselves(): void
    {
        $owner = $this->user('school_owner');
        $otherAdmin = $this->user('school_admin');

        $this->actAs($this->admin)->post(route('accounts.suspend', $this->admin->id), ['reason' => 'x'])->assertSessionHas('error', 'err.acct.self');
        $this->actAs($this->admin)->post(route('accounts.suspend', $owner->id), ['reason' => 'x'])->assertSessionHas('error', 'err.acct.protected');
        $this->actAs($this->admin)->post(route('accounts.suspend', $otherAdmin->id), ['reason' => 'x'])->assertSessionHas('error', 'err.acct.protected');
        $this->actAs($this->admin)->post(route('accounts.revoke', $owner->id), ['reason' => 'x'])->assertSessionHas('error', 'err.acct.protected');

        // only the owner may suspend another administrator, and nobody may touch the owner
        $this->actAs($owner)->post(route('accounts.suspend', $otherAdmin->id), ['reason' => 'x'])->assertSessionHas('success');
        $this->assertNull($owner->fresh()->suspended_at);
    }

    public function test_an_administrator_cannot_reach_users_of_another_school(): void
    {
        $other = $this->otherSchool();
        $victim = $this->user('teacher', $other);

        $this->actAs($this->admin)->post(route('accounts.suspend', $victim->id), ['reason' => 'x'])->assertNotFound();
        $this->actAs($this->admin)->post(route('accounts.reset', $victim->id))->assertNotFound();
        $this->assertNull($victim->fresh()->suspended_at);

        // the list shows only this school's people
        $this->actAs($this->admin)->get(route('accounts.index'))->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($rows) => collect($rows)->every(fn ($r) => $r['id'] !== $victim->id)));
    }

    public function test_a_password_reset_link_is_e_mailed_and_never_shown_to_the_administrator(): void
    {
        Notification::fake();

        $this->actAs($this->admin)->post(route('accounts.reset', $this->teacher->id))->assertSessionHas('success', 'ok.acct.resetSent');
        Notification::assertSentTo($this->teacher, ResetPassword::class);

        // a blocked account gets no link
        $this->teacher->forceFill(['suspended_at' => now()])->save();
        $this->actAs($this->admin)->post(route('accounts.reset', $this->teacher->id))->assertSessionHas('error', 'err.acct.blocked');
    }

    public function test_pending_invitations_can_be_resent_and_revoked(): void
    {
        Notification::fake();
        $this->actAs($this->admin)->post(route('accounts.invite.staff'), ['name' => 'Musa', 'email' => 'musa@alpha.test', 'role' => 'teacher'])->assertSessionHasNoErrors();
        $first = $this->lastInviteToken();
        $id = $this->platform(fn () => StaffInvitation::query()->firstOrFail()->id);

        $this->actAs($this->admin)->post(route('accounts.invitations.resend', $id))->assertSessionHas('success', 'ok.acct.resent');
        $this->assertNull($this->platform(fn () => StaffInvitation::query()->first()->accepted_at));
        // the old link stops working once a new one is issued
        auth()->logout();
        app(TenantContext::class)->clear();
        $this->get(route('invitations.show', $first))->assertStatus(410);

        $this->actAs($this->admin)->delete(route('accounts.invitations.destroy', $id))->assertSessionHas('success', 'ok.acct.inviteRevoked');
        $this->assertNotNull($this->platform(fn () => StaffInvitation::query()->first()->revoked_at));
    }
}
