<?php

namespace App\Services\Accounts;

use App\Exceptions\AcademicRuleException;
use App\Models\ActivityLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Onboarding\StaffInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Lifecycle of school accounts after they exist: suspend, reinstate, revoke and restore access, password reset.
 *
 * Rules (each one is covered by a test):
 *  - an administrator can only act inside their own school, never on themselves;
 *  - the school owner can never be touched; another administrator only by the owner;
 *  - suspension and revocation end live sessions immediately (database sessions are deleted and the
 *    remember-me token rotated; the `account.active` middleware stops anything that is left);
 *  - nothing is deleted: records created by the person stay intact and attributable;
 *  - every action is written to the school's audit log.
 */
class AccountService
{
    public function __construct(private readonly StaffInvitationService $invitations) {}

    public function suspend(User $actor, User $target, string $reason): User
    {
        $this->assertManageable($actor, $target);

        $target->forceFill([
            'suspended_at' => now(), 'suspended_by' => $actor->id, 'suspension_reason' => Str::limit(trim($reason), 250, ''),
        ])->save();
        $this->endSessions($target);
        $this->audit($actor, 'account.suspended', $target, ['reason' => trim($reason)]);

        return $target->fresh();
    }

    public function reinstate(User $actor, User $target): User
    {
        $this->assertManageable($actor, $target);

        if ($target->access_revoked_at !== null) {
            throw AcademicRuleException::because('err.acct.revoked');
        }

        $target->forceFill(['suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
        $this->audit($actor, 'account.reinstated', $target);

        return $target->fresh();
    }

    /** Remove every role and block the account. The person's history stays. */
    public function revokeAccess(User $actor, User $target, string $reason): User
    {
        $this->assertManageable($actor, $target);

        DB::transaction(function () use ($actor, $target, $reason) {
            $previous = $target->getRoleNames()->all();
            $target->syncRoles([]);
            $target->forceFill([
                'access_revoked_at' => now(), 'suspended_at' => $target->suspended_at ?? now(), 'suspended_by' => $actor->id,
                'suspension_reason' => Str::limit(trim($reason), 250, ''),
            ])->save();
            $this->audit($actor, 'account.access_revoked', $target, ['reason' => trim($reason), 'previous_roles' => $previous]);
        });
        $this->endSessions($target);

        return $target->fresh();
    }

    /** Give access back with exactly one role that fits the person's profile. */
    public function restoreAccess(User $actor, User $target, string $role): User
    {
        $this->assertManageable($actor, $target);

        if (! in_array($role, $this->restorableRoles($target), true)) {
            throw AcademicRuleException::because('err.acct.roleNotAllowed');
        }

        DB::transaction(function () use ($actor, $target, $role) {
            $target->syncRoles([$role]);
            $target->forceFill(['access_revoked_at' => null, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
            $this->audit($actor, 'account.access_restored', $target, ['role' => $role]);
        });

        return $target->fresh();
    }

    /** @return array<int,string> */
    public function restorableRoles(User $target): array
    {
        if (Student::query()->where('user_id', $target->id)->exists()) {
            return ['student'];
        }

        if (Guardian::query()->where('user_id', $target->id)->exists()) {
            return ['parent'];
        }

        return $this->invitations->invitableRoles();
    }

    /** E-mail the standard password-reset link. The administrator never sees or sets the password. */
    public function sendPasswordReset(User $actor, User $target): void
    {
        $this->assertManageable($actor, $target);

        if ($target->isBlocked()) {
            throw AcademicRuleException::because('err.acct.blocked');
        }

        $status = Password::sendResetLink(['email' => $target->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw AcademicRuleException::because('err.acct.resetFailed');
        }

        $this->audit($actor, 'account.reset_sent', $target);
    }

    public function assertManageable(User $actor, User $target): void
    {
        if (! $actor->can('users.manage') || ! $actor->school_id || (int) $target->school_id !== (int) $actor->school_id) {
            throw AcademicRuleException::because('err.acct.notAllowed');
        }

        if ($actor->is($target)) {
            throw AcademicRuleException::because('err.acct.self');
        }

        if ($target->hasRole('school_owner')) {
            throw AcademicRuleException::because('err.acct.protected');
        }

        if ($target->hasRole('school_admin') && ! $actor->hasRole('school_owner')) {
            throw AcademicRuleException::because('err.acct.protected');
        }
    }

    private function endSessions(User $target): void
    {
        $target->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
        }
    }

    /** @param  array<string,mixed>  $extra */
    private function audit(User $actor, string $event, User $target, array $extra = []): void
    {
        ActivityLog::query()->create([
            'school_id' => $actor->school_id, 'user_id' => $actor->id, 'event' => $event,
            'description' => $target->email,
            'properties' => ['target_user_id' => $target->id] + $extra,
        ]);
    }
}
