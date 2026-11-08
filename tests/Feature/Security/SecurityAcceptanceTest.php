<?php

namespace Tests\Feature\Security;

use App\Models\Course;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\Student;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 security acceptance tests (master spec section 3.7).
 *
 * Every test here is a negative or positive access-control check. They were first run against the
 * unmodified code base to reproduce the audited vulnerabilities (see docs/implementation-progress.md).
 */
class SecurityAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;

    protected School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        $this->schoolA = $this->makeSchool('school-a', 'School A');
        $this->schoolB = $this->makeSchool('school-b', 'School B');
    }

    protected function makeSchool(string $slug, string $name): School
    {
        return School::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'primary_color' => '#082B55',
            'accent_color' => '#1683F8',
        ]);
    }

    protected function userFor(?School $school, ?string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'school_id' => $school?->id,
            'email_verified_at' => now(),
        ], $attributes));

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /** Make the next request look like a fresh HTTP request (the container is shared inside a test). */
    protected function as(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user);
    }

    /** Create tenant data for a school the way the application would while serving that school. */
    protected function inSchool(School $school, callable $callback): mixed
    {
        $tenant = app(TenantContext::class);
        $tenant->set($school);

        try {
            return $callback();
        } finally {
            $tenant->clear();
        }
    }

    protected function studentIn(School $school, string $number): Student
    {
        return $this->inSchool($school, fn () => Student::query()->create([
            'school_id' => $school->id,
            'student_number' => $number,
            'first_name' => 'First'.$number,
            'last_name' => 'Last'.$number,
            'status' => 'active',
        ]));
    }

    // ------------------------------------------------------------------ 3.1 registration

    public function test_guest_registration_cannot_create_unrestricted_users(): void
    {
        $before = User::query()->count();

        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Str0ng!Passw0rd#1',
            'password_confirmation' => 'Str0ng!Passw0rd#1',
        ]);

        $this->assertGuest();
        $this->assertSame($before, User::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    public function test_generic_register_page_redirects_to_the_official_school_registration(): void
    {
        $this->get('/register')->assertRedirect(route('register-school.create'));
    }

    // ------------------------------------------------------------------ 3.2 fail-closed tenancy

    public function test_tenant_scoped_queries_fail_closed_without_a_tenant_context(): void
    {
        $this->studentIn($this->schoolA, 'A-1');
        $this->studentIn($this->schoolB, 'B-1');

        app(TenantContext::class)->clear();

        $this->assertSame(0, Student::query()->count(), 'A tenant query without a tenant context must return nothing.');
    }

    public function test_platform_mode_is_the_only_way_to_read_across_tenants(): void
    {
        $this->studentIn($this->schoolA, 'A-1');
        $this->studentIn($this->schoolB, 'B-1');

        $tenant = app(TenantContext::class);
        $tenant->clear();

        $this->assertSame(2, $tenant->runAsPlatform(fn () => Student::query()->count()));
        $this->assertSame(0, Student::query()->count(), 'Platform mode must not leak out of the callback.');
    }

    public function test_user_without_school_context_cannot_access_tenant_data(): void
    {
        $this->studentIn($this->schoolA, 'A-1');
        $orphan = $this->userFor(null, null);

        foreach (['/students', '/admin', '/fees', '/academics', '/lms/courses', '/admissions', '/reports'] as $path) {
            $this->as($orphan)->get($path)->assertForbidden();
        }
    }

    public function test_school_a_cannot_access_school_b_students(): void
    {
        $foreign = $this->studentIn($this->schoolB, 'B-1');
        $adminA = $this->userFor($this->schoolA, 'school_admin');

        $this->as($adminA)->get(route('students.show', $foreign->id))->assertNotFound();
    }

    public function test_school_a_cannot_access_school_b_invoices(): void
    {
        $studentB = $this->studentIn($this->schoolB, 'B-1');
        $this->inSchool($this->schoolB, fn () => Invoice::query()->create([
            'school_id' => $this->schoolB->id,
            'student_id' => $studentB->id,
            'invoice_number' => 'INV-B-SECRET',
            'status' => 'issued',
            'total_amount' => 5000,
            'amount_paid' => 0,
            'issued_at' => now(),
        ]));

        $adminA = $this->userFor($this->schoolA, 'school_admin');

        $response = $this->as($adminA)->get(route('fees.index'));
        $response->assertOk();
        $this->assertStringNotContainsString('INV-B-SECRET', $response->getContent());
    }

    public function test_school_a_cannot_access_school_b_lms_content(): void
    {
        [$course, $lesson] = $this->inSchool($this->schoolB, function () {
            $course = Course::query()->create([
                'school_id' => $this->schoolB->id,
                'title' => 'Secret B course',
                'code' => 'SB1',
                'status' => 'published',
            ]);
            $lesson = Lesson::query()->create([
                'school_id' => $this->schoolB->id,
                'course_id' => $course->id,
                'title' => 'Secret B lesson',
                'content_type' => 'text',
                'content' => 'B only',
                'sort_order' => 1,
            ]);

            return [$course, $lesson];
        });

        $adminA = $this->userFor($this->schoolA, 'school_admin');

        $this->as($adminA)->get(route('lms.courses.show', $course->id))->assertNotFound();
        $this->as($adminA)->get(route('lms.lessons.show', $lesson->id))->assertNotFound();

        $list = $this->as($adminA)->get(route('lms.courses.index'));
        $list->assertOk();
        $this->assertStringNotContainsString('Secret B course', $list->getContent());
    }

    // ------------------------------------------------------------------ 3.3 / 3.4 RBAC + route protection

    public function test_teachers_cannot_access_platform_administration(): void
    {
        $teacher = $this->userFor($this->schoolA, 'teacher');

        foreach (['platform.dashboard', 'platform.schools.index', 'platform.pricing.index', 'platform.ops.index'] as $route) {
            $this->as($teacher)->get(route($route))->assertForbidden();
        }
    }

    public function test_students_cannot_access_teacher_or_admin_areas(): void
    {
        $student = $this->userFor($this->schoolA, 'student');

        $this->as($student)->get(route('teacher.dashboard'))->assertForbidden();
        $this->as($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->as($student)->get(route('examinations.index'))->assertForbidden();
        $this->as($student)->get(route('hr.index'))->assertForbidden();
        $this->as($student)->get(route('school.domains.edit'))->assertForbidden();
    }

    public function test_parents_cannot_access_admin_or_other_students_private_records(): void
    {
        $parent = $this->userFor($this->schoolA, 'parent');
        $guardian = $this->inSchool($this->schoolA, fn () => Guardian::query()->create([
            'school_id' => $this->schoolA->id,
            'user_id' => $parent->id,
            'first_name' => 'Pat',
            'last_name' => 'Parent',
        ]));

        $own = $this->studentIn($this->schoolA, 'A-OWN');
        $other = $this->studentIn($this->schoolA, 'A-OTHER');
        $this->inSchool($this->schoolA, fn () => $own->guardians()->attach($guardian->id, [
            'school_id' => $this->schoolA->id,
            'is_primary' => true,
        ]));

        $this->as($parent)->get(route('students.show', $own))->assertOk();
        $this->as($parent)->get(route('students.show', $other))->assertForbidden();
        $this->as($parent)->get(route('admin.dashboard'))->assertForbidden();
        $this->as($parent)->get(route('teacher.dashboard'))->assertForbidden();
    }

    public function test_unauthorised_staff_cannot_record_or_verify_payments(): void
    {
        $student = $this->studentIn($this->schoolA, 'A-1');

        foreach (['teacher', 'student', 'parent'] as $role) {
            $user = $this->userFor($this->schoolA, $role);

            $this->as($user)->post(route('fees.payments.store'), [
                'student_id' => $student->id,
                'amount' => 100,
                'method' => 'cash',
            ])->assertForbidden();

            $this->as($user)->post(route('fees.reconciliation.store'), [
                'period_starts_on' => now()->subDay()->toDateString(),
                'period_ends_on' => now()->toDateString(),
            ])->assertForbidden();
        }
    }

    public function test_permission_matrix_is_granular_and_role_scoped(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $teacher = $this->userFor($this->schoolA, 'teacher');
        $finance = $this->userFor($this->schoolA, 'finance_officer');

        $this->assertTrue($admin->can('students.create'));
        $this->assertTrue($admin->can('payments.verify'));
        $this->assertTrue($teacher->can('students.view'));
        $this->assertFalse($teacher->can('students.delete'));
        $this->assertFalse($teacher->can('payments.verify'));
        $this->assertTrue($finance->can('payments.verify'));
        $this->assertFalse($finance->can('exams.manage'));
    }

    public function test_finance_officer_can_reach_fees_but_not_other_school_admin_areas(): void
    {
        $finance = $this->userFor($this->schoolA, 'finance_officer');

        $this->as($finance)->get(route('fees.index'))->assertOk();
        $this->as($finance)->get(route('hr.index'))->assertForbidden();
        $this->as($finance)->get(route('school.domains.edit'))->assertForbidden();
    }

    // ------------------------------------------------------------------ 3.5 suspension and trial

    public function test_suspended_schools_cannot_access_protected_tenant_services(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $this->schoolA->forceFill(['status' => 'inactive', 'suspended_at' => now()])->save();

        $this->as($admin)->get(route('admin.dashboard'))->assertForbidden();
        $this->as($admin)->get(route('students.index'))->assertForbidden();
    }

    public function test_expired_trials_cannot_continue_using_protected_services(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $this->subscribe($this->schoolA, 'trial', now()->subDay());

        $this->as($admin)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_active_trials_and_paid_subscriptions_keep_access(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $this->subscribe($this->schoolA, 'trial', now()->addDays(10));
        $this->as($admin)->get(route('admin.dashboard'))->assertOk();

        $this->schoolA->subscription()->update(['status' => 'active', 'ends_at' => now()->addYear()]);
        $this->as($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_suspension_never_deletes_school_records(): void
    {
        $this->studentIn($this->schoolA, 'A-1');
        $this->schoolA->forceFill(['status' => 'inactive', 'suspended_at' => now()])->save();

        $count = app(TenantContext::class)->runAsPlatform(fn () => Student::query()->count());
        $this->assertSame(1, $count);
    }

    // ------------------------------------------------------------------ platform operations

    public function test_platform_administrators_can_perform_authorised_cross_tenant_operations(): void
    {
        $platform = $this->userFor(null, 'platform_admin');

        $this->as($platform)->get(route('platform.dashboard'))->assertOk();
        $this->as($platform)->get(route('platform.schools.show', $this->schoolA))->assertOk();
        $this->as($platform)->get(route('platform.schools.show', $this->schoolB))->assertOk();
    }

    public function test_platform_administrators_keep_support_access_to_suspended_schools(): void
    {
        $platform = $this->userFor(null, 'platform_admin');
        $this->schoolA->forceFill(['status' => 'inactive', 'suspended_at' => now()])->save();

        $this->as($platform)->get(route('platform.schools.show', $this->schoolA))->assertOk();
    }

    public function test_platform_administrators_do_not_browse_tenant_data_through_school_routes(): void
    {
        $this->studentIn($this->schoolA, 'A-1');
        $platform = $this->userFor(null, 'platform_admin');

        $this->as($platform)->get(route('students.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------ 3.6 additional security

    public function test_unverified_users_are_sent_to_email_verification(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin', ['email_verified_at' => null]);

        $this->as($admin)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_security_headers_include_a_content_security_policy(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-[A-Za-z0-9+\\/=_-]+'/", $csp);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_blocked_schools_see_a_clear_access_page_with_the_reason(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $this->subscribe($this->schoolA, 'trial', now()->subDay());

        $this->as($admin)->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page
                ->component('Errors/AccessBlocked')
                ->where('reason', 'trial_expired')
                ->where('school.name', $this->schoolA->name));
    }

    public function test_blocked_access_is_written_to_the_school_audit_log(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $this->schoolA->forceFill(['status' => 'inactive', 'suspended_at' => now()])->save();

        $this->as($admin)->get(route('admin.dashboard'))->assertForbidden();

        $logged = app(TenantContext::class)->runAsPlatform(fn () => \App\Models\ActivityLog::query()
            ->where('school_id', $this->schoolA->id)
            ->where('event', 'security.access_denied')
            ->exists());
        $this->assertTrue($logged);
    }

    public function test_expired_subscription_blocks_access_but_keeps_all_records(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $this->studentIn($this->schoolA, 'A-1');
        $this->subscribe($this->schoolA, 'active', now()->subDay());

        $this->as($admin)->get(route('students.index'))->assertForbidden();
        $this->assertSame(1, app(TenantContext::class)->runAsPlatform(fn () => Student::query()->count()));
    }

    public function test_an_explicit_grace_period_is_the_only_way_access_outlives_expiry(): void
    {
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $this->subscribe($this->schoolA, 'active', now()->subDays(2));

        config(['schoolos.access.grace_days' => 0]);
        $this->as($admin)->get(route('admin.dashboard'))->assertForbidden();

        config(['schoolos.access.grace_days' => 5]);
        $this->as($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_privileged_roles_must_enrol_in_two_factor_when_enforced(): void
    {
        config(['schoolos.security.require_two_factor_privileged' => true]);
        $admin = $this->userFor($this->schoolA, 'school_admin');
        $teacher = $this->userFor($this->schoolA, 'teacher');

        $this->as($admin)->get(route('admin.dashboard'))->assertRedirect(route('two-factor.settings'));
        $this->as($teacher)->get(route('teacher.dashboard'))->assertOk();
    }

    public function test_csp_nonce_in_the_header_matches_the_nonce_on_inline_scripts(): void
    {
        $response = $this->get('/');
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $header);

        $this->assertNotEmpty($header[1] ?? null);
        $this->assertStringContainsString('nonce="'.$header[1].'"', $response->getContent());
    }

    public function test_cross_school_user_on_a_foreign_host_is_denied(): void
    {
        $adminA = $this->userFor($this->schoolA, 'school_admin');
        $tenant = app(TenantContext::class);
        $tenant->clear();
        $tenant->set($this->schoolB);

        $this->actingAs($adminA)->get(route('admin.dashboard'))->assertForbidden();
    }

    protected function subscribe(School $school, string $status, $endsAt): SchoolSubscription
    {
        return $this->inSchool($school, fn () => SchoolSubscription::query()->create([
            'school_id' => $school->id,
            'school_type' => 'primary',
            'status' => $status,
            'starts_at' => now()->subDays(30),
            'ends_at' => $endsAt,
            'payment_status' => $status === 'active' ? 'paid' : 'not_required_during_trial',
            'activated_at' => $status === 'active' ? now()->subDays(30) : null,
        ]));
    }
}
