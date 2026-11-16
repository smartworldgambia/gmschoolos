<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff only see navigation and dashboard controls they are allowed to use.
 * Domain settings stay with school_admin / school_owner (domains.manage), not Accounts, HR, or Teachers.
 */
class PrivilegeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        $this->school = School::query()->create([
            'name' => 'Alpha School',
            'slug' => 'alpha',
            'status' => 'active',
            'website_published' => true,
        ]);
        app(TenantContext::class)->set($this->school);
    }

    public function test_school_admin_sees_domain_settings_on_the_dashboard_and_in_nav(): void
    {
        $admin = $this->user('school_admin');
        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(route('school.domains.edit'), $this->prop($response, 'domainSettingsUrl'));
        $this->assertNotNull($this->prop($response, 'schoolUrls'));
        $this->assertContains('domain', $this->navKeys($response));
        $this->assertSame(['students', 'attendance', 'fees', 'lms'], $this->quickActionKeys($response));
        $this->assertTrue($this->prop($response, 'can.students'));
        $this->assertTrue($this->prop($response, 'can.fees'));
    }

    public function test_accountant_does_not_see_domain_settings_or_admin_only_shortcuts(): void
    {
        $accountant = $this->user('accountant');

        $this->actingAs($accountant)->get(route('school.domains.edit'))->assertForbidden();

        $response = $this->actingAs($accountant)->get(route('admin.dashboard'))->assertOk();

        $this->assertNull($this->prop($response, 'domainSettingsUrl'));
        $this->assertNull($this->prop($response, 'schoolUrls'));
        $this->assertNotContains('domain', $this->navKeys($response));
        $this->assertNotContains('website', $this->navKeys($response));
        $this->assertNotContains('students', $this->navKeys($response));
        $this->assertNotContains('hr', $this->navKeys($response));
        $this->assertContains('fees', $this->navKeys($response));
        $this->assertSame(['fees'], $this->quickActionKeys($response));
        $this->assertFalse($this->prop($response, 'can.students'));
        $this->assertTrue($this->prop($response, 'can.fees'));
    }

    public function test_hr_officer_does_not_see_domain_settings_or_finance_shortcuts(): void
    {
        $hr = $this->user('hr_officer');

        $this->actingAs($hr)->get(route('school.domains.edit'))->assertForbidden();

        $response = $this->actingAs($hr)->get(route('admin.dashboard'))->assertOk();

        $this->assertNull($this->prop($response, 'domainSettingsUrl'));
        $this->assertNull($this->prop($response, 'schoolUrls'));
        $this->assertNotContains('domain', $this->navKeys($response));
        $this->assertNotContains('fees', $this->navKeys($response));
        $this->assertContains('hr', $this->navKeys($response));
        $this->assertSame(['attendance'], $this->quickActionKeys($response));
        $this->assertFalse($this->prop($response, 'can.fees'));
        $this->assertTrue($this->prop($response, 'can.staff'));
    }

    public function test_teacher_cannot_open_admin_domain_settings_and_has_no_domain_nav(): void
    {
        $teacher = $this->user('teacher');

        $this->actingAs($teacher)->get(route('school.domains.edit'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.dashboard'))->assertForbidden();

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();

        $this->assertNotContains('domain', $this->navKeys($response));
        $this->assertNotContains('website', $this->navKeys($response));
        $this->assertNotContains('accounts', $this->navKeys($response));
        $this->assertContains('students', $this->navKeys($response));
        $this->assertContains('attendance', $this->navKeys($response));
    }

    public function test_headteacher_can_see_the_school_url_but_not_domain_settings(): void
    {
        $head = $this->user('headteacher');

        $this->actingAs($head)->get(route('school.domains.edit'))->assertForbidden();

        $response = $this->actingAs($head)->get(route('admin.dashboard'))->assertOk();

        $this->assertNull($this->prop($response, 'domainSettingsUrl'));
        $this->assertNotNull($this->prop($response, 'schoolUrls'));
        $this->assertNotContains('domain', $this->navKeys($response));
        $this->assertContains('website', $this->navKeys($response));
    }

    protected function user(string $role): User
    {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function prop($response, string $key): mixed
    {
        return data_get($response->viewData('page')['props'], $key);
    }

    /** @return list<string> */
    protected function navKeys($response): array
    {
        return collect($this->prop($response, 'nav'))->pluck('key')->all();
    }

    /** @return list<string> */
    protected function quickActionKeys($response): array
    {
        return collect($this->prop($response, 'quickActions'))->pluck('key')->all();
    }
}
