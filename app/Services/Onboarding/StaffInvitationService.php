<?php

namespace App\Services\Onboarding;

use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffInvitation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invitations for every kind of school account: staff (setup wizard and accounts screen), students and guardians.
 *
 * - Only roles listed in `schoolos.onboarding.invitable_roles` can be invited as staff: never administrator,
 *   owner or any platform role, so an invitation can never be used to escalate privileges. Students always
 *   receive `student` and guardians `parent`; the role is decided here, never by the caller.
 * - The emailed token is random (256 bits) and only its SHA-256 hash is stored.
 * - A token works once, expires, and can be revoked. Resending rotates the token.
 * - Accepting creates the account inside the inviting school only, verifies the e-mail address (receiving the
 *   link is the proof) and links the new user to the student / guardian record the invitation was issued for.
 */
class StaffInvitationService
{
    public const KIND_STAFF = 'staff';

    public const KIND_STUDENT = 'student';

    public const KIND_GUARDIAN = 'guardian';

    /** Roles whose holders can be allocated to class subjects on acceptance. */
    protected const TEACHING_ROLES = ['teacher', 'class_teacher', 'head_of_department'];

    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    /** @return array<int, string> */
    public function invitableRoles(): array
    {
        return array_values((array) config('schoolos.onboarding.invitable_roles', []));
    }

    /**
     * Invite a staff member.
     *
     * @param  array<int,int>  $classSubjectIds  allocations to hand over on acceptance (teaching roles only, same school, still unassigned)
     */
    public function invite(School $school, User $inviter, string $name, string $email, string $role, array $classSubjectIds = []): StaffInvitation
    {
        if (! in_array($role, $this->invitableRoles(), true)) {
            throw ValidationException::withMessages(['role' => 'err.acct.roleNotInvitable']);
        }

        $allocations = [];
        if ($classSubjectIds !== [] && in_array($role, self::TEACHING_ROLES, true)) {
            $allocations = $this->tenant->runForSchool($school, fn () => ClassSubject::query()
                ->whereIn('id', $classSubjectIds)->whereNull('teacher_id')->pluck('id')->map(fn ($i) => (int) $i)->all());
        }

        return $this->issue($school, $inviter, self::KIND_STAFF, $name, $email, $role, ['class_subject_ids' => $allocations ?: null]);
    }

    /** Give a learner a login. The student record must belong to this school and have no login yet. */
    public function inviteStudent(School $school, User $inviter, Student $student, string $email): StaffInvitation
    {
        $this->tenant->runForSchool($school, function () use ($school, $student) {
            if ((int) $student->school_id !== (int) $school->id) {
                throw ValidationException::withMessages(['student' => 'err.acct.studentOtherSchool']);
            }

            if ($student->user_id !== null) {
                throw ValidationException::withMessages(['student' => 'err.acct.studentHasAccount']);
            }
        });

        return $this->issue($school, $inviter, self::KIND_STUDENT, $student->full_name, $email, 'student', ['student_id' => $student->id]);
    }

    /** Give a parent or guardian a login. Their e-mail address on the guardian record is used. */
    public function inviteGuardian(School $school, User $inviter, Guardian $guardian, ?string $email = null): StaffInvitation
    {
        $this->tenant->runForSchool($school, function () use ($school, $guardian) {
            if ((int) $guardian->school_id !== (int) $school->id) {
                throw ValidationException::withMessages(['guardian' => 'err.acct.guardianOtherSchool']);
            }

            if ($guardian->user_id !== null) {
                throw ValidationException::withMessages(['guardian' => 'err.acct.guardianHasAccount']);
            }
        });

        if (! (bool) data_get($school->settings, 'parent_access.enabled', true)) {
            throw ValidationException::withMessages(['guardian' => 'err.acct.parentAccessOff']);
        }

        $email = $email ?: $guardian->email;
        if (! filled($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'err.acct.emailRequired']);
        }

        return $this->issue($school, $inviter, self::KIND_GUARDIAN, $guardian->full_name, $email, 'parent', ['guardian_id' => $guardian->id]);
    }

    /** Issue a fresh link (the previous one stops working). */
    public function resend(School $school, int $invitationId): StaffInvitation
    {
        [$invitation, $token] = $this->tenant->runForSchool($school, function () use ($school, $invitationId) {
            $invitation = StaffInvitation::query()->where('school_id', $school->id)->findOrFail($invitationId);

            if ($invitation->accepted_at !== null) {
                throw ValidationException::withMessages(['invitation' => 'err.acct.inviteAccepted']);
            }

            $token = Str::random(64);
            $invitation->forceFill([
                'token_hash' => hash('sha256', $token),
                'revoked_at' => null,
                'expires_at' => now()->addDays((int) config('schoolos.onboarding.invitation_ttl_days', 7)),
            ])->save();

            return [$invitation, $token];
        });

        $this->send($school, $invitation, $token);

        return $invitation;
    }

    public function revoke(School $school, int $invitationId): void
    {
        $this->tenant->runForSchool($school, function () use ($school, $invitationId) {
            $invitation = StaffInvitation::query()->where('school_id', $school->id)->findOrFail($invitationId);

            if ($invitation->accepted_at === null && $invitation->revoked_at === null) {
                $invitation->forceFill(['revoked_at' => now()])->save();
            }
        });
    }

    /** Resolve a presented token to a usable invitation (platform lookup: the visitor has no tenant yet). */
    public function findUsable(string $token): ?StaffInvitation
    {
        if (strlen($token) !== 64) {
            return null;
        }

        $invitation = $this->tenant->runAsPlatform(fn () => StaffInvitation::query()
            ->with('school:id,name,slug,status,suspended_at')
            ->where('token_hash', hash('sha256', $token))
            ->first());

        if (! $invitation || ! $invitation->isPending() || ! $invitation->school) {
            return null;
        }

        return $invitation;
    }

    public function accept(StaffInvitation $invitation, string $password): User
    {
        return $this->tenant->runAsPlatform(function () use ($invitation, $password) {
            return DB::transaction(function () use ($invitation, $password) {
                // Re-read under lock so a double submit cannot create two accounts.
                $fresh = StaffInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

                if (! $fresh->isPending()) {
                    throw ValidationException::withMessages(['token' => 'err.acct.inviteInvalid']);
                }

                if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower($fresh->email)])->exists()) {
                    throw ValidationException::withMessages(['token' => 'err.acct.emailTaken']);
                }

                $kind = $fresh->kind ?: self::KIND_STAFF;
                $student = $guardian = null;

                if ($kind === self::KIND_STUDENT) {
                    $student = Student::query()->withoutGlobalScopes()->where('school_id', $fresh->school_id)->lockForUpdate()->find($fresh->student_id);
                    if (! $student || $student->user_id !== null) {
                        throw ValidationException::withMessages(['token' => 'err.acct.inviteInvalid']);
                    }
                }

                if ($kind === self::KIND_GUARDIAN) {
                    $guardian = Guardian::query()->withoutGlobalScopes()->where('school_id', $fresh->school_id)->lockForUpdate()->find($fresh->guardian_id);
                    if (! $guardian || $guardian->user_id !== null) {
                        throw ValidationException::withMessages(['token' => 'err.acct.inviteInvalid']);
                    }
                }

                $user = User::query()->create([
                    'school_id' => $fresh->school_id,
                    'name' => $fresh->name,
                    'email' => $fresh->email,
                    'phone' => $guardian?->phone,
                    'password' => Hash::make($password),
                ]);
                // Receiving the link at this address is the proof of ownership.
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole($fresh->role);

                if ($student) {
                    $student->forceFill(['user_id' => $user->id])->save();
                }

                if ($guardian) {
                    $guardian->forceFill(['user_id' => $user->id])->save();
                }

                if ($kind === self::KIND_STAFF) {
                    [$first, $last] = array_pad(explode(' ', $fresh->name, 2), 2, '');

                    Staff::query()->create([
                        'school_id' => $fresh->school_id,
                        'user_id' => $user->id,
                        'first_name' => $first,
                        'last_name' => $last !== '' ? $last : $first,
                        'email' => $fresh->email,
                        'role_title' => Str::of($fresh->role)->replace('_', ' ')->title()->toString(),
                        'status' => 'active',
                    ]);

                    // Hand over the allocations chosen at invitation time, unless someone else took them meanwhile.
                    if (! empty($fresh->class_subject_ids)) {
                        ClassSubject::query()->withoutGlobalScopes()
                            ->where('school_id', $fresh->school_id)->whereIn('id', $fresh->class_subject_ids)->whereNull('teacher_id')
                            ->update(['teacher_id' => $user->id]);
                    }
                }

                $fresh->forceFill(['accepted_at' => now(), 'user_id' => $user->id])->save();

                return $user;
            });
        });
    }

    /** @param  array<string,mixed>  $extra */
    private function issue(School $school, User $inviter, string $kind, string $name, string $email, string $role, array $extra = []): StaffInvitation
    {
        $email = mb_strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'err.acct.emailRequired']);
        }

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'err.acct.emailTaken']);
        }

        [$invitation, $token] = $this->tenant->runForSchool($school, function () use ($school, $inviter, $kind, $name, $email, $role, $extra) {
            $open = StaffInvitation::query()->where('school_id', $school->id)
                ->where(fn ($q) => $q->where('email', $email)
                    ->when(isset($extra['student_id']), fn ($q) => $q->orWhere('student_id', $extra['student_id']))
                    ->when(isset($extra['guardian_id']), fn ($q) => $q->orWhere('guardian_id', $extra['guardian_id'])))
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists();

            if ($open) {
                throw ValidationException::withMessages(['email' => 'err.acct.invitePending']);
            }

            $token = Str::random(64);

            $invitation = StaffInvitation::query()->create([
                'school_id' => $school->id,
                'name' => trim($name),
                'email' => $email,
                'role' => $role,
                'kind' => $kind,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays((int) config('schoolos.onboarding.invitation_ttl_days', 7)),
            ] + $extra);

            return [$invitation, $token];
        });

        $this->send($school, $invitation, $token);

        return $invitation;
    }

    private function send(School $school, StaffInvitation $invitation, string $token): void
    {
        // Named route on the app URL — never /sites/{slug}/invitations/{token}, which is not a real route.
        $acceptUrl = route('invitations.show', ['token' => $token], absolute: true);

        try {
            Notification::route('mail', $invitation->email)->notifyNow(new StaffInvitationNotification(
                school: $school,
                inviteeName: $invitation->name,
                roleLabel: Str::of($invitation->role)->replace('_', ' ')->title()->toString(),
                acceptUrl: $acceptUrl,
                expiresOn: $invitation->expires_at->toFormattedDateString(),
            ));
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['email' => 'err.acct.inviteMailFailed']);
        }
    }
}
