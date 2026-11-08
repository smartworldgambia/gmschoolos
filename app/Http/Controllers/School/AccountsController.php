<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\StaffInvitation;
use App\Models\Student;
use App\Models\User;
use App\Services\Accounts\AccountService;
use App\Services\Onboarding\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account provisioning and lifecycle for administrators (users.manage): invite staff, learners and guardians, suspend,
 * reinstate, revoke / restore access and send password-reset links. Roles are never chosen by the browser for learners
 * and guardians, and administrator / owner roles can never be invited.
 */
class AccountsController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request, AccountService $accounts): Response
    {
        $school = $request->user()->school;
        $status = in_array($request->query('status'), ['active', 'suspended', 'revoked'], true) ? $request->query('status') : null;
        $search = trim((string) $request->query('q', ''));
        $kind = in_array($request->query('kind'), ['student', 'guardian'], true) ? $request->query('kind') : null;
        $candidateSearch = trim((string) $request->query('cq', ''));

        $users = User::query()->with('roles:id,name')->where('school_id', $school->id)
            ->when($status === 'active', fn ($q) => $q->whereNull('suspended_at')->whereNull('access_revoked_at'))
            ->when($status === 'suspended', fn ($q) => $q->whereNotNull('suspended_at')->whereNull('access_revoked_at'))
            ->when($status === 'revoked', fn ($q) => $q->whereNotNull('access_revoked_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->paginate(20)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'roles' => $u->getRoleNames()->values(),
                'verified' => $u->email_verified_at !== null,
                'status' => $u->access_revoked_at ? 'revoked' : ($u->suspended_at ? 'suspended' : 'active'),
                'suspension_reason' => $u->suspension_reason,
                'last_login_at' => $u->last_login_at?->toDateTimeString(),
                'manageable' => $this->manageable($request->user(), $u),
                'restorable' => $u->access_revoked_at ? $accounts->restorableRoles($u) : [],
            ]);

        $candidates = [];
        if ($kind === 'student') {
            $candidates = Student::query()->whereNull('user_id')->where('status', 'active')
                ->when($candidateSearch !== '', fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', '%'.$candidateSearch.'%')
                    ->orWhere('last_name', 'like', '%'.$candidateSearch.'%')->orWhere('student_number', 'like', '%'.$candidateSearch.'%')))
                ->orderBy('first_name')->limit(30)->get()
                ->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->full_name, 'ref' => $s->student_number, 'email' => null])->all();
        } elseif ($kind === 'guardian') {
            $candidates = Guardian::query()->whereNull('user_id')
                ->when($candidateSearch !== '', fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', '%'.$candidateSearch.'%')
                    ->orWhere('last_name', 'like', '%'.$candidateSearch.'%')->orWhere('email', 'like', '%'.$candidateSearch.'%')))
                ->orderBy('first_name')->limit(30)->get()
                ->map(fn (Guardian $g) => ['id' => $g->id, 'name' => $g->full_name, 'ref' => $g->relationship, 'email' => $g->email])->all();
        }

        $invitations = StaffInvitation::query()->latest()->limit(60)->get()->map(fn (StaffInvitation $i) => [
            'id' => $i->id, 'name' => $i->name, 'email' => $i->email, 'role' => $i->role, 'kind' => $i->kind ?: 'staff',
            'state' => $i->state(), 'expires_at' => $i->expires_at?->toDateTimeString(),
        ]);

        return Inertia::render('Accounts/Index', array_merge($this->shellProps(), [
            'users' => $users,
            'invitations' => $invitations,
            'filters' => ['q' => $search, 'status' => $status, 'kind' => $kind, 'cq' => $candidateSearch],
            'roles' => app(StaffInvitationService::class)->invitableRoles(),
            'allocations' => ClassSubject::query()->with(['schoolClass:id,name', 'subject:id,name'])->whereNull('teacher_id')->limit(300)->get()
                ->map(fn (ClassSubject $c) => ['id' => $c->id, 'label' => ($c->schoolClass?->name ?? '').' · '.($c->subject?->name ?? '')])->values(),
            'candidates' => $candidates,
            'parentAccess' => (bool) data_get($school->settings, 'parent_access.enabled', true),
            'canRevoke' => $request->user()->can('users.manage'),
        ]));
    }

    public function inviteStaff(Request $request, StaffInvitationService $invitations): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'max:60'],
            'class_subject_ids' => ['nullable', 'array', 'max:40'],
            'class_subject_ids.*' => ['integer', $this->inSchool('class_subjects')],
        ]);

        $invitations->invite($request->user()->school, $request->user(), $data['name'], $data['email'], $data['role'], $data['class_subject_ids'] ?? []);

        return back()->with('success', 'ok.acct.invited');
    }

    public function inviteStudent(Request $request, StaffInvitationService $invitations): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', $this->inSchool('students')],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $invitations->inviteStudent($request->user()->school, $request->user(), Student::query()->findOrFail($data['student_id']), $data['email']);

        return back()->with('success', 'ok.acct.invited');
    }

    public function inviteGuardian(Request $request, StaffInvitationService $invitations): RedirectResponse
    {
        $data = $request->validate([
            'guardian_id' => ['required', 'integer', $this->inSchool('guardians')],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $invitations->inviteGuardian($request->user()->school, $request->user(), Guardian::query()->findOrFail($data['guardian_id']), $data['email'] ?? null);

        return back()->with('success', 'ok.acct.invited');
    }

    public function resend(Request $request, int $invitation, StaffInvitationService $invitations): RedirectResponse
    {
        $invitations->resend($request->user()->school, $invitation);

        return back()->with('success', 'ok.acct.resent');
    }

    public function revokeInvitation(Request $request, int $invitation, StaffInvitationService $invitations): RedirectResponse
    {
        $invitations->revoke($request->user()->school, $invitation);

        return back()->with('success', 'ok.acct.inviteRevoked');
    }

    public function suspend(Request $request, int $user, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);

        return $this->attempt(fn () => $accounts->suspend($request->user(), $this->target($request, $user), $data['reason']), 'ok.acct.suspended');
    }

    public function reinstate(Request $request, int $user, AccountService $accounts): RedirectResponse
    {
        return $this->attempt(fn () => $accounts->reinstate($request->user(), $this->target($request, $user)), 'ok.acct.reinstated');
    }

    public function revokeAccess(Request $request, int $user, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);

        return $this->attempt(fn () => $accounts->revokeAccess($request->user(), $this->target($request, $user), $data['reason']), 'ok.acct.revoked');
    }

    public function restoreAccess(Request $request, int $user, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'string', 'max:60']]);

        return $this->attempt(fn () => $accounts->restoreAccess($request->user(), $this->target($request, $user), $data['role']), 'ok.acct.restored');
    }

    public function sendReset(Request $request, int $user, AccountService $accounts): RedirectResponse
    {
        return $this->attempt(fn () => $accounts->sendPasswordReset($request->user(), $this->target($request, $user)), 'ok.acct.resetSent');
    }

    /** Users are global rows (not tenant scoped), so the school is part of the lookup. */
    private function target(Request $request, int $id): User
    {
        return User::query()->where('school_id', $request->user()->school_id)->findOrFail($id);
    }

    private function manageable(User $actor, User $target): bool
    {
        if ($actor->is($target) || $target->hasRole('school_owner')) {
            return false;
        }

        return ! ($target->hasRole('school_admin') && ! $actor->hasRole('school_owner'));
    }
}
