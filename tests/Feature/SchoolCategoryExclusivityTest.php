<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolCategoryUpgradeRequest;
use App\Models\SchoolTypePrice;
use App\Models\User;
use App\Services\Billing\PricingService;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\Onboarding\SetupWizard;
use App\Services\School\CategoryUpgradeService;
use App\Services\School\SchoolLandingProvisioner;
use App\Support\SchoolCategoryCatalog;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchoolCategoryExclusivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
        app(TenantContext::class)->enterPlatformMode();
    }

    protected function nurserySchool(): School
    {
        $school = School::query()->create([
            'name' => 'Exclusive Nursery',
            'slug' => 'exclusive-nursery',
            'category' => 'nursery',
            'enabled_categories' => ['nursery'],
            'status' => 'active',
            'website_published' => false,
        ]);

        app(SchoolLandingProvisioner::class)->provision($school);
        SchoolTypePrice::query()->where('school_type', 'nursery')->delete();
        SchoolTypePrice::query()->where('school_type', 'combined')->delete();
        app(PricingService::class)->set('nursery', 12000, 'GMD', '2000-01-01', 0.0, 'test');
        app(PricingService::class)->set('combined', 36000, 'GMD', '2000-01-01', 0.0, 'test');
        app(SubscriptionLifecycle::class)->startTrial($school->fresh(), 30, 'nursery');

        return $school->fresh();
    }

    protected function userFor(School $school, string $role): User
    {
        $user = User::factory()->create([
            'school_id' => $school->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_nursery_registration_entitlements_match_pack(): void
    {
        $school = $this->nurserySchool();

        $this->assertSame(['nursery'], SchoolCategoryCatalog::enabledCategories($school));
        $this->assertTrue(SchoolCategoryCatalog::isPackLocked($school));
        $this->assertSame(['baby', 'toddler', 'reception'], SchoolCategoryCatalog::allowedLevelCodes($school));
        $this->assertSame(3, Level::query()->where('school_id', $school->id)->count());
        $this->assertFalse(SchoolCategoryCatalog::isLevelAllowed($school, 'Grade 1', 'g1'));
        $this->assertTrue(SchoolCategoryCatalog::isLevelAllowed($school, 'Reception', 'reception'));
    }

    public function test_setup_rejects_out_of_pack_level(): void
    {
        $school = $this->nurserySchool();
        $wizard = app(SetupWizard::class);

        $this->expectException(ValidationException::class);
        $wizard->addLevel($school, 'Grade 1', 'g1');
    }

    public function test_setup_allows_missing_pack_level(): void
    {
        $school = $this->nurserySchool();
        Level::query()->where('school_id', $school->id)->where('code', 'reception')->delete();

        $level = app(SetupWizard::class)->addLevel($school, 'Reception', 'reception');

        $this->assertSame('reception', $level->code);
    }

    public function test_category_upgrade_request_and_approval_expands_levels_and_billing(): void
    {
        $school = $this->nurserySchool();
        $admin = $this->userFor($school, 'school_admin');
        $operator = $this->userFor($school, 'platform_admin');
        // Platform admins are typically school_id null; keep role for permission checks in service cancel path.
        $operator->forceFill(['school_id' => null])->save();

        $upgrades = app(CategoryUpgradeService::class);
        $request = $upgrades->request($school, $admin, ['primary'], 'Expanding into primary');

        $this->assertTrue($request->isPending());
        $this->assertSame(['primary'], $request->requested_categories);
        $this->assertSame('combined', $request->target_billing_type);
        $this->assertTrue(
            PlatformAlert::query()->where('school_id', $school->id)->where('source', 'billing')->exists()
        );

        $approved = $upgrades->approve($request, $operator, 'Approved for growth');
        $school->refresh();

        $this->assertSame(SchoolCategoryUpgradeRequest::APPROVED, $approved->status);
        $this->assertSame(['nursery', 'primary'], $school->enabled_categories);
        $this->assertSame('combined', $school->category);
        $this->assertDatabaseHas('levels', ['school_id' => $school->id, 'code' => 'g1']);
        $this->assertDatabaseHas('levels', ['school_id' => $school->id, 'code' => 'baby']);
        $this->assertTrue(SchoolCategoryCatalog::isLevelAllowed($school, 'Grade 2', 'g2'));
        $this->assertFalse(SchoolCategoryCatalog::isLevelAllowed($school, 'Form 1', 'f1'));
    }

    public function test_school_admin_can_submit_upgrade_via_http(): void
    {
        $school = $this->nurserySchool();
        $admin = $this->userFor($school, 'school_admin');

        app(TenantContext::class)->clear();

        $this->actingAs($admin)
            ->post(route('billing.category-upgrade.store'), [
                'categories' => ['primary'],
                'reason' => 'Need Grade 1–6',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_category_upgrade_requests', [
            'school_id' => $school->id,
            'status' => SchoolCategoryUpgradeRequest::PENDING,
            'target_billing_type' => 'combined',
        ]);
    }
}
