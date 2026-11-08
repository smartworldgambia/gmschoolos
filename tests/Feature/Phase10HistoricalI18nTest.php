<?php

namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** Phase 10 leftover historical UI: dashboards, reports, exports, profile and platform chrome send keys, not English labels. */
class Phase10HistoricalI18nTest extends Phase8TestCase
{
    public function test_admin_dashboard_sends_stable_quick_action_keys(): void
    {
        $this->actAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('quickActions', 4)
                ->where('quickActions.0.key', 'students')
                ->where('quickActions.1.key', 'attendance')
                ->where('quickActions.2.key', 'fees')
                ->where('quickActions.3.key', 'lms')
                ->missing('quickActions.0.label')
            );
    }

    public function test_users_without_a_portal_role_see_the_generic_home_keys(): void
    {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);

        $this->actAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('home', 4)
                ->where('home.0.key', 'admin')
                ->where('home.1.key', 'teacher')
                ->where('home.2.key', 'student')
                ->where('home.3.key', 'parent')
                ->missing('home.0.label')
            );
    }

    public function test_reports_exports_and_profile_pages_render(): void
    {
        $this->actAs($this->admin)->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Reports/Index'));

        $this->actAs($this->admin)->get(route('exports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Exports/Index'));

        $this->actAs($this->admin)->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Profile/Edit'));

        $this->actAs($this->admin)->get(route('two-factor.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Profile/TwoFactor'));
    }

    public function test_platform_console_pages_render(): void
    {
        $platform = User::factory()->create([
            'school_id' => null,
            'email_verified_at' => now(),
        ]);
        $platform->assignRole('platform_admin');

        $pages = [
            'platform.dashboard' => 'Platform/Dashboard',
            'platform.schools.index' => 'Platform/Schools/Index',
            'platform.backups.index' => 'Platform/Backups/Index',
            'platform.ops.index' => 'Platform/Ops/Index',
            'platform.scale.index' => 'Platform/Scale/Index',
            'platform.monitoring.index' => 'Platform/Monitoring/Index',
            'platform.capacity.index' => 'Platform/Capacity/Index',
            'platform.marketing.edit' => 'Platform/Marketing/Edit',
        ];

        foreach ($pages as $name => $component) {
            $this->actingAs($platform)
                ->get(route($name))
                ->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component($component));
        }

        $this->actingAs($platform)
            ->get(route('platform.schools.show', $this->school))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Platform/Schools/Show'));
    }
}
