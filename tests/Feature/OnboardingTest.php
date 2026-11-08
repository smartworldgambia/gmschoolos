<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Level;
use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolRegistrationReview;
use App\Models\SchoolSubscription;
use App\Models\Staff;
use App\Models\StaffInvitation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Notifications\SchoolRegisteredWelcome;
use App\Notifications\StaffInvitationNotification;
use App\Services\Onboarding\SetupWizard;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 4 acceptance: self-service school registration, duplicate detection, tenant provisioning,
 * subdomain allocation, initial administrator, 30-day trial, setup wizard, staff invitations
 * and the platform registration review queue.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        Storage::fake('public');
        Notification::fake();
    }

    // ------------------------------------------------------------------ helpers

    /** Run a block as the platform operator and leave no tenant bound afterwards. */
    protected function platform(callable $fn): mixed
    {
        app(TenantContext::class)->clear();

        return app(TenantContext::class)->runAsPlatform($fn);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'school_name' => 'Riverside Academy',
            'category' => 'Primary',
            'registration_number' => 'MOBSE-2024-0017',
            'school_email' => 'office@riverside.test',
            'school_phone' => '+220 4410101',
            'address' => '12 River Road, Bakau',
            'country' => 'The Gambia',
            'region' => 'Kanifing',
            'district' => 'Kanifing Municipal',
            'subdomain' => 'riverside',
            'academic_year_name' => '2026/2027',
            'term_name' => 'Term 1',
            'admin_name' => 'Awa Jallow',
            'admin_email' => 'awa@riverside.test',
            'admin_phone' => '+220 7771122',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'terms' => '1',
        ], $overrides);
    }

    protected function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        app(TenantContext::class)->clear();
        auth()->logout();
        // the route is throttled (10/min per client); this helper is called many times per test
        \Illuminate\Support\Facades\Cache::flush();

        return $this->post(route('register-school.store'), $this->payload($overrides));
    }

    /** Register a school and return [School, verified admin]. */
    protected function registeredSchool(array $overrides = []): array
    {
        $this->register($overrides)->assertSessionHasNoErrors();
        $slug = $overrides['subdomain'] ?? 'riverside';

        return $this->platform(function () use ($slug) {
            $school = School::query()->where('slug', $slug)->firstOrFail();
            $admin = User::query()->where('school_id', $school->id)->firstOrFail();
            $admin->forceFill(['email_verified_at' => now()])->save();

            return [$school, $admin];
        });
    }

    protected function as(User $user): static
    {
        app(TenantContext::class)->clear();
        auth()->logout();

        return $this->actingAs($user);
    }

    protected function otherSchool(string $slug = 'other-school', string $name = 'Other School'): array
    {
        $school = $this->platform(fn () => School::query()->create(['name' => $name, 'slug' => $slug, 'status' => 'active', 'category' => 'primary']));
        $admin = User::factory()->create(['school_id' => $school->id, 'email_verified_at' => now()]);
        $admin->assignRole('school_admin');

        return [$school, $admin];
    }

    // ------------------------------------------------------------------ registration + provisioning

    public function test_registration_provisions_a_complete_school_without_fabricated_records(): void
    {
        $this->register(['logo' => UploadedFile::fake()->image('logo.png', 120, 120)])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();

        $this->platform(function () {
            $school = School::query()->where('slug', 'riverside')->firstOrFail();

            // school record: official details, type, terms agreement
            $this->assertSame('Riverside Academy', $school->name);
            $this->assertSame('primary', $school->category);
            $this->assertSame('MOBSE-2024-0017', $school->registration_number);
            $this->assertSame('Kanifing', $school->region);
            $this->assertSame('Kanifing Municipal', $school->district);
            $this->assertSame('12 River Road, Bakau', $school->address);
            $this->assertSame('office@riverside.test', $school->email);
            $this->assertSame('+220 4410101', $school->phone);
            $this->assertNotNull($school->terms_accepted_at);
            $this->assertSame(config('schoolos.onboarding.terms_version'), $school->terms_version);
            $this->assertNull($school->setup_completed_at);
            $this->assertNotNull($school->logo_path);
            Storage::disk('public')->assertExists($school->logo_path);

            // subdomain -> verified primary platform domain
            $domain = $school->domains()->firstOrFail();
            $this->assertTrue($domain->is_primary);
            $this->assertNotNull($domain->verified_at);
            $this->assertStringStartsWith('riverside.', $domain->host);

            // 30-day trial with the school-type price snapshot, no payment
            $sub = SchoolSubscription::query()->where('school_id', $school->id)->firstOrFail();
            $this->assertSame('trial', $sub->status);
            $this->assertSame('primary', $sub->school_type);
            $this->assertSame('annual', $sub->billing_cycle);
            $this->assertEqualsWithDelta(30, now()->diffInDays($sub->trial_ends_at, false), 1);

            // academic structure initialised: current year + term, level scaffold, defaults
            $year = AcademicYear::query()->where('school_id', $school->id)->where('is_current', true)->firstOrFail();
            $this->assertSame('2026/2027', $year->name);
            $this->assertSame('Term 1', Term::query()->where('school_id', $school->id)->where('is_current', true)->firstOrFail()->name);
            $this->assertGreaterThan(0, Level::query()->where('school_id', $school->id)->count());
            $this->assertSame('Africa/Banjul', $school->settings['defaults']['timezone']);

            // public website provisioned
            $this->assertNotEmpty($school->settings['landing'] ?? null);
            $this->assertNotNull($school->tagline);

            // NOTHING fabricated: no students, classes, staff, subjects or departments
            $this->assertSame(0, Student::query()->where('school_id', $school->id)->count());
            $this->assertSame(0, SchoolClass::query()->where('school_id', $school->id)->count());
            $this->assertSame(0, Staff::query()->where('school_id', $school->id)->count());
            $this->assertSame(0, Subject::query()->where('school_id', $school->id)->count());
            $this->assertSame(0, Department::query()->where('school_id', $school->id)->count());

            // initial administrator: unverified, school_admin, school-scoped, can manage settings
            $admin = User::query()->where('email', 'awa@riverside.test')->firstOrFail();
            $this->assertSame($school->id, $admin->school_id);
            $this->assertNull($admin->email_verified_at);
            $this->assertTrue($admin->hasRole('school_admin'));
            $this->assertTrue($admin->can('school.settings.manage'));
            $this->assertFalse($admin->can('platform.schools.manage'));
            $this->assertSame('+220 7771122', $admin->phone);
            $this->assertNotSame('Str0ng-Passw0rd!', $admin->password);

            // clean registration => no review needed
            $this->assertSame(0, SchoolRegistrationReview::query()->count());
        });

        // welcome + trial confirmation e-mail, and the verification link
        $admin = $this->platform(fn () => User::query()->where('email', 'awa@riverside.test')->firstOrFail());
        Notification::assertSentTo($admin, SchoolRegisteredWelcome::class, fn (SchoolRegisteredWelcome $n) => $n->trialEndsOn !== null && $n->trialDays === 30);
        Notification::assertSentTo($admin, VerifyEmail::class);
    }

    public function test_agreement_to_terms_and_every_required_field_is_enforced(): void
    {
        $this->register(['terms' => null])->assertSessionHasErrors('terms');
        $this->register(['terms' => '0'])->assertSessionHasErrors('terms');

        foreach (['school_email', 'school_phone', 'address', 'district', 'admin_phone', 'region', 'admin_name'] as $field) {
            $this->register([$field => ''])->assertSessionHasErrors($field);
        }

        $this->register(['school_email' => 'not-an-email'])->assertSessionHasErrors('school_email');
        $this->register(['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->register(['password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->register(['category' => 'Not A Type'])->assertSessionHasErrors('category');

        $this->assertSame(0, $this->platform(fn () => School::query()->count()));
    }

    public function test_subdomain_allocation_rejects_reserved_taken_and_invalid_names(): void
    {
        $this->registeredSchool();

        $this->register(['subdomain' => 'riverside', 'school_name' => 'Second', 'admin_email' => 'b@x.test', 'school_email' => 'b@x.test', 'school_phone' => '+220 5550000', 'registration_number' => null, 'address' => '99 Other Street', 'region' => 'Brikama'])
            ->assertSessionHasErrors('subdomain');

        foreach (['admin', 'www', 'api', 'platform'] as $reserved) {
            $this->register(['subdomain' => $reserved])->assertSessionHasErrors('subdomain');
        }
        foreach (['-bad', 'bad-', 'no spaces', 'ab'] as $invalid) {
            $this->register(['subdomain' => $invalid])->assertSessionHasErrors('subdomain');
        }

        // availability endpoint agrees
        $this->getJson(route('register-school.subdomain-check', ['subdomain' => 'riverside']))->assertJsonPath('available', false);
        $this->getJson(route('register-school.subdomain-check', ['subdomain' => 'fresh-name']))->assertJsonPath('available', true);
        $this->getJson(route('register-school.subdomain-check', ['subdomain' => 'admin']))->assertJsonPath('available', false);
    }

    public function test_registration_is_rate_limited(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        $last = null;
        for ($i = 0; $i < 12; $i++) {
            $last = $this->post(route('register-school.store'), []);
        }

        $last->assertStatus(429);
    }

    public function test_the_two_schools_get_separate_tenants_and_slugs(): void
    {
        [$a] = $this->registeredSchool();
        $this->register([
            'school_name' => 'Hillcrest College', 'subdomain' => 'hillcrest', 'registration_number' => 'MOBSE-2024-0099',
            'school_email' => 'info@hillcrest.test', 'school_phone' => '+220 3330000', 'address' => '4 Hill Street, Brikama',
            'region' => 'Brikama', 'district' => 'West Coast', 'admin_email' => 'head@hillcrest.test',
        ])->assertSessionHasNoErrors();

        $this->platform(function () use ($a) {
            $b = School::query()->where('slug', 'hillcrest')->firstOrFail();
            $this->assertNotSame($a->id, $b->id);
            $this->assertSame(1, User::query()->where('school_id', $a->id)->count());
            $this->assertSame(1, User::query()->where('school_id', $b->id)->count());
            $this->assertSame(2, SchoolSubscription::query()->count());
        });
    }

    // ------------------------------------------------------------------ duplicate detection

    public function test_a_reused_registration_number_is_blocked_without_revealing_the_existing_school(): void
    {
        $this->registeredSchool();

        $response = $this->register([
            'school_name' => 'Totally Different Name', 'subdomain' => 'different', 'registration_number' => 'mobse 2024 0017',
            'school_email' => 'x@different.test', 'school_phone' => '+220 9990000', 'address' => '1 Elsewhere Lane, Basse',
            'region' => 'Basse', 'admin_email' => 'x@different.test',
        ]);

        $response->assertSessionHasErrors('school_name');
        $this->assertStringNotContainsString('Riverside', session('errors')->first('school_name'));

        $this->platform(function () {
            $this->assertNull(School::query()->where('slug', 'different')->first());
            $this->assertNull(User::query()->where('email', 'x@different.test')->first());
        });
    }

    public function test_the_same_name_in_the_same_region_is_blocked(): void
    {
        $this->registeredSchool();

        $this->register([
            'school_name' => '  riverside   ACADEMY ', 'subdomain' => 'riverside-two', 'registration_number' => null,
            'school_email' => 'two@elsewhere.test', 'school_phone' => '+220 1234567', 'address' => '7 Somewhere Close',
            'admin_email' => 'two@elsewhere.test',
        ])->assertSessionHasErrors('school_name');

        $this->assertSame(1, $this->platform(fn () => School::query()->count()));
    }

    public function test_the_same_name_in_another_region_is_allowed_as_a_separate_campus_but_not_free_of_scrutiny(): void
    {
        $this->registeredSchool();

        $this->register([
            'school_name' => 'Riverside Academy', 'subdomain' => 'riverside-basse', 'registration_number' => 'MOBSE-2024-0500',
            'region' => 'Basse', 'district' => 'Basse', 'school_email' => 'basse@riverside.test', 'school_phone' => '+220 6660000',
            'address' => '5 Market Road, Basse', 'admin_email' => 'basse-head@riverside.test',
        ])->assertSessionHasNoErrors();

        $this->platform(function () {
            $review = SchoolRegistrationReview::query()->firstOrFail();
            $this->assertSame('pending', $review->status);
            $this->assertContains('similar_name', $review->reasons);
        });
    }

    public function test_suspicious_but_not_certain_matches_register_and_are_queued_for_platform_review(): void
    {
        [$first] = $this->registeredSchool();

        // new name + new number, but the same telephone, address and an e-mail alias of the first administrator
        $this->register([
            'school_name' => 'Crescent Learning Centre', 'subdomain' => 'crescent', 'registration_number' => null,
            'school_phone' => '00220 4410101', 'address' => '12 River Road, Bakau', 'school_email' => 'office@crescent.test',
            'region' => 'Brikama', 'admin_email' => 'awa+second@riverside.test',
        ])->assertSessionHasNoErrors();

        $this->platform(function () use ($first) {
            $school = School::query()->where('slug', 'crescent')->firstOrFail();
            $review = SchoolRegistrationReview::query()->where('school_id', $school->id)->firstOrFail();

            $this->assertSame('pending', $review->status);
            $this->assertEqualsCanonicalizing(['same_phone', 'same_address', 'admin_email_alias'], $review->reasons);
            $this->assertSame([$first->id], $review->matched_school_ids);

            $alert = PlatformAlert::query()->where('source', 'registration')->where('school_id', $school->id)->firstOrFail();
            $this->assertSame('warning', $alert->severity);

            // the school is still fully provisioned (it is not punished before review)
            $this->assertSame('trial', SchoolSubscription::query()->where('school_id', $school->id)->firstOrFail()->status);
            $this->assertNotNull(User::query()->where('school_id', $school->id)->first());
        });
    }

    public function test_a_duplicate_administrator_email_is_rejected(): void
    {
        $this->registeredSchool();

        $this->register([
            'school_name' => 'Another School', 'subdomain' => 'another', 'registration_number' => null,
            'school_email' => 'office@another.test', 'school_phone' => '+220 8880000', 'address' => '3 Third Street',
            'region' => 'Basse', 'admin_email' => 'awa@riverside.test',
        ])->assertSessionHasErrors('admin_email');
    }

    // ------------------------------------------------------------------ setup wizard

    public function test_a_new_administrator_is_sent_to_the_wizard_until_setup_is_complete(): void
    {
        [$school, $admin] = $this->registeredSchool();

        $this->as($admin)->get(route('dashboard'))->assertRedirect(route('setup.index'));

        $this->as($admin)->get(route('setup.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Setup/Index')
                ->has('steps', 12)
                ->where('progress.total', 12)
                ->where('progress.complete', false)
                ->where('current', 'profile')
                ->where('profile.name', 'Riverside Academy')
                ->where('schoolType.category', 'primary')
                ->has('invitableRoles'));

        // schools that were not created through the wizard are never forced into it
        [, $legacyAdmin] = $this->otherSchool();
        $this->as($legacyAdmin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_wizard_progress_is_saved_and_survives_a_new_session(): void
    {
        [$school, $admin] = $this->registeredSchool();

        $this->as($admin)->post(route('setup.profile'), [
            'registration_number' => 'MOBSE-2024-0017', 'school_email' => 'office@riverside.test', 'phone' => '+220 4410101',
            'address' => '12 River Road, Bakau', 'region' => 'Kanifing', 'district' => 'Kanifing Municipal', 'tagline' => 'Learning by the river',
        ])->assertSessionHasNoErrors()->assertRedirect(route('setup.index', ['step' => 'branding']));

        $this->as($admin)->post(route('setup.type'))->assertSessionHasNoErrors();
        $this->as($admin)->post(route('setup.steps.skip', 'branding'))->assertSessionHasNoErrors();

        // "log out and come back later"
        auth()->logout();
        $this->flushSession();

        $this->as($admin)->get(route('setup.index'))->assertInertia(fn ($page) => $page
            ->where('progress.done', 2)
            ->where('progress.skipped', 1)
            ->where('steps.0.status', 'done')
            ->where('steps.1.status', 'skipped')
            ->where('steps.2.status', 'done')
            ->where('steps.3.status', 'todo')
            ->where('current', 'structure')
            ->where('profile.tagline', 'Learning by the river'));

        $this->assertSame('Learning by the river', $this->platform(fn () => School::query()->find($school->id)->tagline));
    }

    public function test_required_steps_cannot_be_skipped_and_empty_steps_cannot_be_confirmed(): void
    {
        [, $admin] = $this->registeredSchool();

        $this->as($admin)->post(route('setup.steps.skip', 'academic_year'))->assertSessionHasErrors('step');
        $this->as($admin)->post(route('setup.steps.skip', 'classes'))->assertSessionHasErrors('step');
        $this->as($admin)->post(route('setup.steps.done', 'classes'))->assertSessionHasErrors('step');
        $this->as($admin)->post(route('setup.steps.done', 'subjects'))->assertSessionHasErrors('step');
        $this->as($admin)->post(route('setup.steps.done', 'nonsense'))->assertNotFound();
    }

    public function test_academic_year_and_terms_are_validated(): void
    {
        [$school, $admin] = $this->registeredSchool();

        $good = [
            'name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31',
            'terms' => [
                ['name' => 'Term 1', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-18'],
                ['name' => 'Term 2', 'starts_on' => '2027-01-05', 'ends_on' => '2027-04-02'],
                ['name' => 'Term 3', 'starts_on' => '2027-04-19', 'ends_on' => '2027-07-30'],
            ],
        ];

        // term outside the year
        $bad = $good;
        $bad['terms'][2]['ends_on'] = '2027-08-15';
        $this->as($admin)->post(route('setup.year'), $bad)->assertSessionHasErrors('terms.2.starts_on');

        // overlapping terms
        $bad = $good;
        $bad['terms'][1]['starts_on'] = '2026-12-01';
        $this->as($admin)->post(route('setup.year'), $bad)->assertSessionHasErrors('terms.1.starts_on');

        // year ends before it starts
        $bad = $good;
        $bad['ends_on'] = '2026-08-01';
        $this->as($admin)->post(route('setup.year'), $bad)->assertSessionHasErrors('ends_on');

        $this->as($admin)->post(route('setup.year'), $good)->assertSessionHasNoErrors();

        $this->platform(function () use ($school) {
            $years = AcademicYear::query()->where('school_id', $school->id)->get();
            $this->assertCount(1, $years, 'The registration year is updated, not duplicated.');
            $this->assertSame('2026-09-01', $years[0]->starts_on->toDateString());
            $this->assertSame(3, Term::query()->where('school_id', $school->id)->count());
            $this->assertSame(1, Term::query()->where('school_id', $school->id)->where('is_current', true)->count());
        });
    }

    public function test_levels_departments_classes_and_subjects_are_managed_inside_the_school_only(): void
    {
        [$school, $admin] = $this->registeredSchool();
        [$other, $otherAdmin] = $this->otherSchool();

        $this->as($admin)->post(route('setup.levels.store'), ['name' => 'Reception', 'code' => 'rec'])->assertSessionHasNoErrors();
        $this->as($admin)->post(route('setup.levels.store'), ['name' => 'reception'])->assertSessionHasErrors('name');

        $level = $this->platform(fn () => Level::query()->where('school_id', $school->id)->where('name', 'Reception')->firstOrFail());

        $this->as($admin)->post(route('setup.departments.store'), ['name' => 'Sciences'])->assertSessionHasNoErrors();
        $this->as($admin)->post(route('setup.departments.store'), ['name' => 'sciences'])->assertSessionHasErrors('name');

        $this->as($admin)->post(route('setup.classes.store'), ['level_id' => $level->id, 'name' => 'Reception', 'stream' => 'A', 'capacity' => 30])->assertSessionHasNoErrors();
        $this->as($admin)->post(route('setup.classes.store'), ['level_id' => $level->id, 'name' => 'Reception', 'stream' => 'A'])->assertSessionHasErrors('name');

        // cannot attach a class to another school's level
        $foreign = $this->platform(fn () => Level::query()->create(['school_id' => $other->id, 'name' => 'Foreign', 'code' => 'f', 'sort_order' => 1]));
        $this->as($admin)->post(route('setup.classes.store'), ['level_id' => $foreign->id, 'name' => 'Sneaky'])->assertSessionHasErrors('level_id');

        // a level that still has classes cannot be removed
        $this->as($admin)->delete(route('setup.levels.destroy', $level->id))->assertSessionHasErrors('level');

        $this->as($admin)->post(route('setup.subjects.store'), ['name' => 'Mathematics', 'code' => 'math'])->assertSessionHasNoErrors();
        $this->as($admin)->post(route('setup.subjects.store'), ['name' => 'Maths 2', 'code' => 'MATH'])->assertSessionHasErrors('code');

        // another school's admin cannot delete this school's records
        $subject = $this->platform(fn () => Subject::query()->where('school_id', $school->id)->firstOrFail());
        $this->as($otherAdmin)->delete(route('setup.subjects.destroy', $subject->id))->assertNotFound();
        $this->as($otherAdmin)->delete(route('setup.levels.destroy', $level->id))->assertNotFound();

        // confirming steps now works
        foreach (['structure', 'classes', 'subjects'] as $step) {
            $this->as($admin)->post(route('setup.steps.done', $step))->assertSessionHasNoErrors();
        }

        $statuses = app(SetupWizard::class)->statuses($school->fresh());
        $this->assertSame('done', $statuses['structure']['status']);
        $this->assertSame('done', $statuses['classes']['status']);
        $this->assertSame('done', $statuses['subjects']['status']);
    }

    public function test_setup_is_restricted_to_school_administrators_with_settings_permission(): void
    {
        [$school] = $this->registeredSchool();

        $teacher = User::factory()->create(['school_id' => $school->id, 'email_verified_at' => now()]);
        $teacher->assignRole('teacher');

        $this->as($teacher)->get(route('setup.index'))->assertForbidden();
        $this->as($teacher)->post(route('setup.departments.store'), ['name' => 'Hack'])->assertForbidden();

        auth()->logout();
        app(TenantContext::class)->clear();
        $this->get(route('setup.index'))->assertRedirect(route('login'));
    }

    public function test_branding_and_website_choices_are_saved(): void
    {
        [$school, $admin] = $this->registeredSchool();

        $this->as($admin)->post(route('setup.branding'), ['primary_color' => 'red', 'accent_color' => '#112233'])->assertSessionHasErrors('primary_color');
        $this->as($admin)->post(route('setup.branding'), [
            'primary_color' => '#0A2540', 'accent_color' => '#10B9A5', 'logo' => UploadedFile::fake()->image('new.png', 100, 100),
        ])->assertSessionHasNoErrors();

        $fresh = $this->platform(fn () => School::query()->find($school->id));
        $this->assertSame('#0A2540', $fresh->primary_color);
        Storage::disk('public')->assertExists($fresh->logo_path);

        $this->as($admin)->post(route('setup.website'), ['publish' => false])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $this->platform(fn () => School::query()->find($school->id)->website_published));

        $this->as($admin)->post(route('setup.parents'), ['enabled' => false])->assertSessionHasNoErrors();
        $this->assertFalse($this->platform(fn () => School::query()->find($school->id))->settings['parent_access']['enabled']);
    }

    public function test_setup_completes_only_when_every_step_is_done_or_skipped(): void
    {
        [$school, $admin] = $this->registeredSchool();

        $this->as($admin)->post(route('setup.complete'))->assertSessionHasErrors('step');
        $this->assertNull($this->platform(fn () => School::query()->find($school->id))->setup_completed_at);

        $wizard = app(SetupWizard::class);
        $level = $this->platform(fn () => Level::query()->firstOrCreate(['school_id' => $school->id, 'name' => 'Grade 1'], ['code' => 'g1', 'sort_order' => 1]));

        $this->as($admin)->post(route('setup.profile'), [
            'school_email' => 'office@riverside.test', 'phone' => '+220 4410101', 'address' => '12 River Road, Bakau',
            'region' => 'Kanifing', 'district' => 'Kanifing Municipal',
        ]);
        $this->as($admin)->post(route('setup.type'));
        $this->as($admin)->post(route('setup.levels.store'), ['name' => 'Reception']);
        $this->as($admin)->post(route('setup.steps.done', 'structure'));
        $this->as($admin)->post(route('setup.year'), [
            'name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31',
            'terms' => [['name' => 'Term 1', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-18']],
        ]);
        $this->as($admin)->post(route('setup.classes.store'), ['level_id' => $level->id, 'name' => 'Grade 1']);
        $this->as($admin)->post(route('setup.steps.done', 'classes'));
        $this->as($admin)->post(route('setup.subjects.store'), ['name' => 'English']);
        $this->as($admin)->post(route('setup.steps.done', 'subjects'));

        $this->assertFalse($wizard->progress($school->fresh())['complete']);

        foreach (['branding', 'departments', 'staff', 'students', 'parents', 'website'] as $optional) {
            $this->as($admin)->post(route('setup.steps.skip', $optional))->assertSessionHasNoErrors();
        }

        $progress = $wizard->progress($school->fresh());
        $this->assertTrue($progress['complete']);
        $this->assertSame(100, $progress['percent']);

        $this->as($admin)->post(route('setup.complete'))->assertRedirect(route('admin.dashboard'));
        $this->assertNotNull($this->platform(fn () => School::query()->find($school->id))->setup_completed_at);

        // finished: the dashboard no longer detours through the wizard
        $this->as($admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));

        // reopening a step makes setup incomplete again
        $this->as($admin)->post(route('setup.steps.reopen', 'subjects'))->assertRedirect();
        $this->assertNull($this->platform(fn () => School::query()->find($school->id))->setup_completed_at);
    }

    // ------------------------------------------------------------------ staff invitations

    protected function invite(User $admin, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->as($admin)->post(route('setup.invitations.store'), array_merge([
            'name' => 'Musa Ceesay', 'email' => 'musa@riverside.test', 'role' => 'teacher',
        ], $overrides));
    }

    protected function lastInviteUrl(): string
    {
        $url = null;
        Notification::assertSentOnDemand(StaffInvitationNotification::class, function (StaffInvitationNotification $n) use (&$url) {
            $url = $n->acceptUrl;

            return true;
        });

        return $url;
    }

    public function test_invitations_cannot_grant_administrator_or_platform_roles(): void
    {
        [, $admin] = $this->registeredSchool();
        Notification::fake(); // forget the registration e-mails

        foreach (['school_admin', 'school_owner', 'platform_admin', 'platform_owner', 'super_admin', 'nonexistent'] as $role) {
            $this->invite($admin, ['role' => $role, 'email' => $role.'@riverside.test'])->assertSessionHasErrors('role');
        }

        Notification::assertNothingSent();
        $this->assertSame(0, $this->platform(fn () => StaffInvitation::query()->count()));
    }

    public function test_an_invitation_is_hashed_single_use_and_creates_a_school_scoped_account(): void
    {
        [$school, $admin] = $this->registeredSchool();

        $this->invite($admin)->assertSessionHasNoErrors();
        $url = $this->lastInviteUrl();
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertSame(64, strlen($token));

        $invitation = $this->platform(fn () => StaffInvitation::query()->firstOrFail());
        $this->assertNotSame($token, $invitation->token_hash, 'The raw token must never be stored.');
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertSame($school->id, $invitation->school_id);

        // a second pending invitation for the same address is refused
        $this->invite($admin)->assertSessionHasErrors('email');

        // guest opens the link
        auth()->logout();
        app(TenantContext::class)->clear();
        $this->get(route('invitations.show', $token))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Auth/AcceptInvitation')->where('invalid', false)->where('invitation.school', 'Riverside Academy')->where('invitation.role', 'teacher'));

        // weak password rejected, link still valid
        $this->post(route('invitations.accept', $token), ['password' => 'abc', 'password_confirmation' => 'abc'])->assertSessionHasErrors('password');

        $this->post(route('invitations.accept', $token), ['password' => 'Another-Str0ng-Pass!', 'password_confirmation' => 'Another-Str0ng-Pass!'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $this->platform(function () use ($school) {
            $user = User::query()->where('email', 'musa@riverside.test')->firstOrFail();
            $this->assertSame($school->id, $user->school_id);
            $this->assertTrue($user->hasRole('teacher'));
            $this->assertFalse($user->hasRole('school_admin'));
            $this->assertNotNull($user->email_verified_at);
            $this->assertSame(1, Staff::query()->where('school_id', $school->id)->where('user_id', $user->id)->count());
            $this->assertNotNull(StaffInvitation::query()->firstOrFail()->accepted_at);
        });

        // the token cannot be used again
        auth()->logout();
        $this->flushSession();
        $this->get(route('invitations.show', $token))->assertStatus(410);
        $this->post(route('invitations.accept', $token), ['password' => 'Another-Str0ng-Pass!', 'password_confirmation' => 'Another-Str0ng-Pass!'])
            ->assertRedirect(route('invitations.show', $token));
        $this->assertSame(1, $this->platform(fn () => User::query()->where('email', 'musa@riverside.test')->count()));
    }

    public function test_expired_revoked_forged_and_resent_links_behave_correctly(): void
    {
        [$school, $admin] = $this->registeredSchool();

        $this->invite($admin)->assertSessionHasNoErrors();
        $oldToken = basename(parse_url($this->lastInviteUrl(), PHP_URL_PATH));
        $invitation = $this->platform(fn () => StaffInvitation::query()->firstOrFail());

        // resending rotates the token
        Notification::fake();
        $this->as($admin)->post(route('setup.invitations.resend', $invitation->id))->assertSessionHasNoErrors();
        $newToken = basename(parse_url($this->lastInviteUrl(), PHP_URL_PATH));
        $this->assertNotSame($oldToken, $newToken);

        auth()->logout();
        app(TenantContext::class)->clear();
        $this->get(route('invitations.show', $oldToken))->assertStatus(410);
        $this->get(route('invitations.show', $newToken))->assertOk();

        // forged / malformed tokens
        $this->get(route('invitations.show', str_repeat('a', 64)))->assertStatus(410);
        $this->get('/invitations/short')->assertNotFound();

        // expired
        $this->platform(fn () => StaffInvitation::query()->update(['expires_at' => now()->subMinute()]));
        $this->get(route('invitations.show', $newToken))->assertStatus(410);

        // resend revives an expired invitation, revoke kills it
        $this->as($admin)->post(route('setup.invitations.resend', $invitation->id))->assertSessionHasNoErrors();
        $this->as($admin)->delete(route('setup.invitations.destroy', $invitation->id))->assertSessionHasNoErrors();

        auth()->logout();
        app(TenantContext::class)->clear();
        $this->get(route('invitations.show', $newToken))->assertStatus(410);
        $this->assertNull($this->platform(fn () => User::query()->where('email', 'musa@riverside.test')->first()));
    }

    public function test_invitations_are_isolated_between_schools(): void
    {
        [$school, $admin] = $this->registeredSchool();
        [$other, $otherAdmin] = $this->otherSchool();

        $this->invite($admin)->assertSessionHasNoErrors();
        $invitation = $this->platform(fn () => StaffInvitation::query()->firstOrFail());

        // another school cannot resend or revoke it
        $this->as($otherAdmin)->post(route('setup.invitations.resend', $invitation->id))->assertNotFound();
        $this->as($otherAdmin)->delete(route('setup.invitations.destroy', $invitation->id))->assertNotFound();

        // and does not see it in its own wizard
        $this->as($otherAdmin)->get(route('setup.index'))->assertInertia(fn ($page) => $page->has('invitations', 0));
        $this->as($admin)->get(route('setup.index'))->assertInertia(fn ($page) => $page->has('invitations', 1));

        // an e-mail that already has an account anywhere cannot be invited
        $this->invite($admin, ['email' => $otherAdmin->email, 'name' => 'Taken'])->assertSessionHasErrors('email');
    }

    // ------------------------------------------------------------------ platform review queue

    protected function flaggedSchool(): School
    {
        $this->registeredSchool();
        $this->register([
            'school_name' => 'Crescent Learning Centre', 'subdomain' => 'crescent', 'registration_number' => null,
            'school_phone' => '+220 4410101', 'school_email' => 'office@crescent.test', 'address' => '88 Different Road',
            'region' => 'Brikama', 'admin_email' => 'head@crescent.test',
        ])->assertSessionHasNoErrors();

        return $this->platform(fn () => School::query()->where('slug', 'crescent')->firstOrFail());
    }

    protected function platformAdmin(): User
    {
        $user = User::factory()->create(['school_id' => null, 'email_verified_at' => now()]);
        $user->assignRole('platform_admin');

        return $user;
    }

    public function test_the_platform_review_queue_lists_flagged_registrations_for_platform_admins_only(): void
    {
        $flagged = $this->flaggedSchool();
        [, $schoolAdmin] = $this->otherSchool();

        $this->as($this->platformAdmin())->get(route('platform.registrations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Registrations/Index')
                ->where('pending', 1)
                ->has('reviews.data', 1)
                ->where('reviews.data.0.school.slug', 'crescent')
                ->where('reviews.data.0.reasons.0', 'same_phone')
                ->has('reviews.data.0.matched', 1));

        $this->as($schoolAdmin)->get(route('platform.registrations.index'))->assertStatus(403);
        auth()->logout();
        $this->get(route('platform.registrations.index'))->assertRedirect(route('login'));
    }

    public function test_clearing_a_review_records_the_reviewer_and_resolves_the_alert(): void
    {
        $flagged = $this->flaggedSchool();
        $reviewer = $this->platformAdmin();
        $review = $this->platform(fn () => SchoolRegistrationReview::query()->firstOrFail());

        $this->as($reviewer)->post(route('platform.registrations.clear', $review->id), ['notes' => 'Second campus confirmed by phone.'])->assertSessionHasNoErrors();

        $this->platform(function () use ($review, $reviewer, $flagged) {
            $fresh = $review->fresh();
            $this->assertSame('cleared', $fresh->status);
            $this->assertSame($reviewer->id, $fresh->reviewed_by);
            $this->assertNotNull($fresh->reviewed_at);
            $this->assertNull($flagged->fresh()->suspended_at);
            $this->assertNotNull(PlatformAlert::query()->where('source', 'registration')->first()->resolved_at);
        });

        // a decided review cannot be decided again
        $this->as($reviewer)->post(route('platform.registrations.confirm', $review->id), ['notes' => 'Changed my mind'])->assertSessionHasErrors('review');
    }

    public function test_confirming_a_duplicate_suspends_the_school_and_ends_the_trial_without_deleting_anything(): void
    {
        $flagged = $this->flaggedSchool();
        $reviewer = $this->platformAdmin();
        $review = $this->platform(fn () => SchoolRegistrationReview::query()->firstOrFail());

        // a note is mandatory
        $this->as($reviewer)->post(route('platform.registrations.confirm', $review->id), [])->assertSessionHasErrors('notes');

        $this->as($reviewer)->post(route('platform.registrations.confirm', $review->id), ['notes' => 'Same institution as Riverside; trial already used.'])
            ->assertSessionHasNoErrors();

        $this->platform(function () use ($flagged, $review) {
            $school = $flagged->fresh();
            $this->assertNotNull($school->suspended_at);
            $this->assertSame('inactive', $school->status);
            $this->assertSame('suspended', SchoolSubscription::query()->where('school_id', $school->id)->firstOrFail()->status);
            $this->assertSame('confirmed_duplicate', $review->fresh()->status);

            // nothing is deleted
            $this->assertNotNull(School::query()->find($school->id));
            $this->assertSame(1, User::query()->where('school_id', $school->id)->count());
            $this->assertGreaterThan(0, Level::query()->where('school_id', $school->id)->count());
            $this->assertDatabaseHas('billing_events', ['school_id' => $school->id, 'type' => 'trial_revoked_duplicate']);
        });
    }

    public function test_a_school_that_already_paid_cannot_be_suspended_through_the_review_queue(): void
    {
        $flagged = $this->flaggedSchool();
        $review = $this->platform(fn () => SchoolRegistrationReview::query()->firstOrFail());
        $this->platform(fn () => SchoolSubscription::query()->where('school_id', $flagged->id)->update(['status' => 'active']));

        $this->as($this->platformAdmin())->post(route('platform.registrations.confirm', $review->id), ['notes' => 'Attempt on a paying school'])
            ->assertSessionHasErrors('review');

        $this->assertNull($this->platform(fn () => $flagged->fresh()->suspended_at));
        $this->assertSame('pending', $this->platform(fn () => $review->fresh()->status));
    }

    // ------------------------------------------------------------------ guard rails

    public function test_no_monthly_billing_surface_is_introduced_by_onboarding(): void
    {
        [$school] = $this->registeredSchool();

        $sub = $this->platform(fn () => SchoolSubscription::query()->where('school_id', $school->id)->firstOrFail());
        $this->assertSame('annual', $sub->billing_cycle);

        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->implode(' ');
        $this->assertStringNotContainsString('monthly', $routes);
    }
}
