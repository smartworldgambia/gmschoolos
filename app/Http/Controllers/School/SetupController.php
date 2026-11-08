<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Level;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StaffInvitation;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Onboarding\DuplicateDetector;
use App\Services\Onboarding\SetupWizard;
use App\Services\Onboarding\StaffInvitationService;
use App\Services\Scale\CacheTuner;
use App\Services\School\SchoolDomainService;
use App\Support\Money;
use App\Support\SchoolTypes;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-registration setup wizard (12 steps). Every action is limited to the signed-in administrator's
 * own school and requires `school.settings.manage`.
 */
class SetupController extends Controller
{
    use SharesSchoolShell;

    /** Steps that need at least one record before they can be confirmed. */
    private const NEEDS_DATA = ['structure' => 'structure', 'classes' => 'classes', 'subjects' => 'subjects'];

    public function __construct(
        private readonly SetupWizard $wizard,
        private readonly TenantContext $tenant,
    ) {}

    public function index(Request $request, SchoolDomainService $domains): Response
    {
        $school = $this->school();
        $statuses = $this->wizard->statuses($school);
        $progress = $this->wizard->progress($school);

        $requested = (string) $request->query('step', '');
        $current = $this->wizard->isStep($requested) ? $requested : ($progress['next'] ?? SetupWizard::STEPS[0]);

        $year = AcademicYear::query()->where('school_id', $school->id)->where('is_current', true)->first();
        $urls = $domains->urlsFor($school);
        $subscription = $school->subscription;

        $steps = [];
        foreach (SetupWizard::STEPS as $key) {
            $steps[] = [
                'key' => $key,
                'status' => $statuses[$key]['status'],
                'count' => $statuses[$key]['count'],
                'skippable' => in_array($key, SetupWizard::SKIPPABLE, true),
            ];
        }

        return Inertia::render('Setup/Index', array_merge($this->shellProps(), [
            'steps' => $steps,
            'current' => $current,
            'progress' => $progress,
            'setupCompletedAt' => $school->setup_completed_at?->toDateString(),
            'profile' => [
                'name' => $school->name,
                'registration_number' => $school->registration_number,
                'school_email' => $school->email,
                'phone' => $school->phone,
                'address' => $school->address,
                'region' => $school->region,
                'district' => $school->district,
                'tagline' => $school->tagline,
            ],
            'branding' => [
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
                'primary_color' => $school->primary_color ?: '#1A2B56',
                'accent_color' => $school->accent_color ?: '#1683F8',
            ],
            'schoolType' => [
                'category' => $school->category,
                'annual_price' => $subscription?->annual_amount !== null
                    ? Money::formatCurrency($subscription->annual_amount, $subscription->currency)
                    : null,
                'trial_ends_at' => $subscription?->trial_ends_at?->toDateString(),
            ],
            'levels' => Level::query()->where('school_id', $school->id)->orderBy('sort_order')->get(['id', 'name', 'code']),
            'academicYear' => $year ? [
                'name' => $year->name,
                'starts_on' => $year->starts_on?->toDateString(),
                'ends_on' => $year->ends_on?->toDateString(),
                'terms' => Term::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)
                    ->orderBy('starts_on')->get(['id', 'name', 'starts_on', 'ends_on'])
                    ->map(fn (Term $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'starts_on' => $t->starts_on?->toDateString(),
                        'ends_on' => $t->ends_on?->toDateString(),
                    ])->values(),
            ] : null,
            'departments' => Department::query()->where('school_id', $school->id)->orderBy('name')->get(['id', 'name', 'code']),
            'classes' => SchoolClass::query()->where('school_id', $school->id)->orderBy('level_id')->orderBy('name')->orderBy('stream')
                ->get(['id', 'level_id', 'name', 'stream', 'capacity']),
            'subjects' => Subject::query()->where('school_id', $school->id)->orderBy('name')->get(['id', 'name', 'code']),
            'invitations' => StaffInvitation::query()->where('school_id', $school->id)->latest('id')->limit(50)->get()
                ->map(fn (StaffInvitation $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'email' => $i->email,
                    'role' => $i->role,
                    'state' => $i->state(),
                    'expires_on' => $i->expires_at?->toDateString(),
                ])->values(),
            'invitableRoles' => app(StaffInvitationService::class)->invitableRoles(),
            'parentAccess' => (bool) data_get($school->settings, 'parent_access.enabled', false),
            'website' => [
                'published' => (bool) $school->website_published,
                'url' => $urls['primary_url'],
            ],
            'links' => [
                'import' => route('students.import.index'),
                'admissions' => route('admissions.create'),
                'websiteEdit' => route('website.edit'),
                'dashboard' => route('admin.dashboard'),
            ],
        ]));
    }

    public function profile(Request $request, DuplicateDetector $detector, CacheTuner $cache): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            'registration_number' => ['nullable', 'string', 'max:60'],
            'school_email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:160'],
        ]);

        // A changed registration number must not collide with another institution (trial-abuse control).
        if (filled($data['registration_number'] ?? null)
            && $detector->registrationNumberTakenByOther($data['registration_number'], $school->id)) {
            throw ValidationException::withMessages([
                'registration_number' => 'This registration number is already linked to another school. Contact support if you believe this is a mistake.',
            ]);
        }

        $school->update([
            'registration_number' => $data['registration_number'] ?? null,
            'email' => $data['school_email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'region' => $data['region'],
            'district' => $data['district'],
            'tagline' => $data['tagline'] ?? null,
        ]);

        $this->wizard->markDone($school, 'profile', $request->user());
        $cache->forgetPublicSite($school->id);

        return $this->next($school, 'profile');
    }

    public function branding(Request $request, CacheTuner $cache): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $update = ['primary_color' => $data['primary_color'], 'accent_color' => $data['accent_color']];

        if ($request->hasFile('logo')) {
            $update['logo_path'] = $request->file('logo')->store('schools/logos', 'public');
        }

        $old = $school->logo_path;
        $school->update($update);

        if (isset($update['logo_path']) && $old && $old !== $update['logo_path']) {
            Storage::disk('public')->delete($old);
        }

        $this->wizard->markDone($school, 'branding', $request->user());
        $cache->forgetPublicSite($school->id);

        return $this->next($school, 'branding');
    }

    public function confirmType(Request $request): RedirectResponse
    {
        $school = $this->school();
        abort_unless(SchoolTypes::isValid($school->category), 422);

        $this->wizard->markDone($school, 'school_type', $request->user());

        return $this->next($school, 'school_type');
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'code' => ['nullable', 'string', 'max:20']]);
        $this->wizard->addLevel($this->school(), $data['name'], $data['code'] ?? null);

        return back()->with('success', 'Level added.');
    }

    public function destroyLevel(int $level): RedirectResponse
    {
        $this->wizard->removeLevel($this->school(), $level);

        return back()->with('success', 'Level removed.');
    }

    public function saveYear(Request $request): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
            'terms' => ['required', 'array', 'min:1', 'max:6'],
            'terms.*.id' => ['nullable', 'integer'],
            'terms.*.name' => ['required', 'string', 'max:50'],
            'terms.*.starts_on' => ['required', 'date'],
            'terms.*.ends_on' => ['required', 'date'],
        ]);

        $this->wizard->saveAcademicYear($school, $data);
        $this->wizard->markDone($school, 'academic_year', $request->user());

        return $this->next($school, 'academic_year');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'code' => ['nullable', 'string', 'max:20']]);
        $this->wizard->addDepartment($this->school(), $data['name'], $data['code'] ?? null);

        return back()->with('success', 'Department added.');
    }

    public function destroyDepartment(int $department): RedirectResponse
    {
        $this->wizard->removeDepartment($this->school(), $department);

        return back()->with('success', 'Department removed.');
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'stream' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $this->wizard->addClass($this->school(), (int) $data['level_id'], $data['name'], $data['stream'] ?? null, isset($data['capacity']) ? (int) $data['capacity'] : null);

        return back()->with('success', 'Class added.');
    }

    public function destroyClass(int $class): RedirectResponse
    {
        $this->wizard->removeClass($this->school(), $class);

        return back()->with('success', 'Class removed.');
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['nullable', 'string', 'max:20']]);
        $this->wizard->addSubject($this->school(), $data['name'], $data['code'] ?? null);

        return back()->with('success', 'Subject added.');
    }

    public function destroySubject(int $subject): RedirectResponse
    {
        $this->wizard->removeSubject($this->school(), $subject);

        return back()->with('success', 'Subject removed.');
    }

    public function invite(Request $request, StaffInvitationService $invitations): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string'],
        ]);

        $invitations->invite($this->school(), $request->user(), $data['name'], $data['email'], $data['role']);

        return back()->with('success', 'ok.acct.invited');
    }

    public function resendInvitation(int $invitation, StaffInvitationService $invitations): RedirectResponse
    {
        $invitations->resend($this->school(), $invitation);

        return back()->with('success', 'ok.acct.resent');
    }

    public function revokeInvitation(int $invitation, StaffInvitationService $invitations): RedirectResponse
    {
        $invitations->revoke($this->school(), $invitation);

        return back()->with('success', 'ok.acct.inviteRevoked');
    }

    public function parents(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $this->wizard->setParentAccess($school, (bool) $data['enabled']);
        $this->wizard->markDone($school, 'parents', $request->user());

        return $this->next($school, 'parents');
    }

    public function website(Request $request, CacheTuner $cache): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate(['publish' => ['required', 'boolean']]);

        $school->update(['website_published' => (bool) $data['publish']]);
        $this->wizard->markDone($school, 'website', $request->user());
        $cache->forgetPublicSite($school->id);

        return $this->next($school, 'website');
    }

    public function done(Request $request, string $step): RedirectResponse
    {
        $school = $this->school();
        abort_unless($this->wizard->isStep($step), 404);

        if (isset(self::NEEDS_DATA[$step])) {
            $statuses = $this->wizard->statuses($school);
            $count = $step === 'structure'
                ? Level::query()->where('school_id', $school->id)->count()
                : ($statuses[$step]['count'] ?? 0);

            if ($count < 1) {
                throw ValidationException::withMessages(['step' => 'Add at least one item before continuing.']);
            }
        }

        $this->wizard->markDone($school, $step, $request->user());

        return $this->next($school, $step);
    }

    public function skip(Request $request, string $step): RedirectResponse
    {
        $school = $this->school();
        abort_unless($this->wizard->isStep($step), 404);

        $this->wizard->skip($school, $step, $request->user());

        return $this->next($school, $step);
    }

    public function reopen(string $step): RedirectResponse
    {
        $school = $this->school();
        abort_unless($this->wizard->isStep($step), 404);

        $this->wizard->reopen($school, $step);

        return redirect()->route('setup.index', ['step' => $step]);
    }

    public function complete(): RedirectResponse
    {
        $school = $this->school();

        if (! $this->wizard->complete($school)) {
            throw ValidationException::withMessages(['step' => 'Finish or skip every step before completing setup.']);
        }

        return redirect()->route('admin.dashboard')->with('success', 'School setup is complete.');
    }

    protected function school(): School
    {
        $user = auth()->user();
        abort_unless($user?->school_id, 403);

        if (! $this->tenant->has()) {
            $this->tenant->set($user->school);
        }

        $school = $this->tenant->school() ?? $user->school;
        abort_unless($school && $school->id === $user->school_id, 403);

        return $school;
    }

    /** After saving a step, move to the following unfinished step (or stay put when everything is done). */
    private function next(School $school, string $from): RedirectResponse
    {
        $progress = $this->wizard->progress($school);
        $order = SetupWizard::STEPS;
        $after = array_slice($order, array_search($from, $order, true) + 1);
        $statuses = $this->wizard->statuses($school);

        $target = collect($after)->first(fn ($s) => $statuses[$s]['status'] === 'todo')
            ?? ($progress['next'] ?? $from);

        return redirect()->route('setup.index', ['step' => $target])->with('success', 'Progress saved.');
    }
}
