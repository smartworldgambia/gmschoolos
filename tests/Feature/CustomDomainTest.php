<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\User;
use App\Services\School\DnsResolver;
use App\Services\School\SchoolDomainService;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FakeDnsResolver extends DnsResolver
{
    /** @var array<string, list<string>> */
    public array $cnames = [];

    /** @var array<string, list<string>> */
    public array $ips = [];

    /** @var array<string, list<string>> */
    public array $txts = [];

    public function cname(string $host): array
    {
        return $this->cnames[$host] ?? [];
    }

    public function a(string $host): array
    {
        return $this->ips[$host] ?? [];
    }

    public function txt(string $host): array
    {
        return $this->txts[$host] ?? [];
    }
}

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    protected FakeDnsResolver $dns;

    protected School $school;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPlansSeeder::class);

        config([
            'schoolos.base_domain' => 'schoolos.test',
            'schoolos.custom_domain_auto_verify' => false,
            'schoolos.custom_domain_cname_target' => null,
            'schoolos.custom_domain_plan_codes' => [],
            'schoolos.custom_domain_limit' => 3,
        ]);

        $this->dns = new FakeDnsResolver;
        $this->app->instance(DnsResolver::class, $this->dns);

        [$this->school, $this->admin] = $this->makeSchool('alpha');
    }

    /** @return array{0: School, 1: User} */
    protected function makeSchool(string $slug): array
    {
        $school = School::query()->create([
            'name' => ucfirst($slug).' Academy',
            'slug' => $slug,
            'status' => 'active',
            'website_published' => true,
        ]);
        SchoolSubscription::query()->create([
            'school_id' => $school->id,
            'school_type' => 'primary',
            'status' => 'active',
            'payment_status' => 'paid',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'activated_at' => now(),
        ]);

        app(SchoolDomainService::class)->assignPlatformDomain($school);

        $admin = User::factory()->create([
            'school_id' => $school->id,
            'email' => "admin@{$slug}.test",
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('school_admin');

        return [$school, $admin];
    }

    protected function service(): SchoolDomainService
    {
        return app(SchoolDomainService::class);
    }

    /* ---------------------------- registration ---------------------------- */

    protected function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'school_name' => 'New Horizon Academy',
            'school_email' => 'info@newhorizon.test',
            'school_phone' => '+220 4400000',
            'address' => '12 Test Road',
            'district' => 'Banjul Central',
            'admin_phone' => '+220 1111111',
            'terms' => '1',
            'category' => 'Primary',
            'region' => 'Banjul',
            'subdomain' => 'newhorizon',
            'academic_year_name' => '2025/2026',
            'term_name' => 'Term 1',
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@newhorizon.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_registration_assigns_a_verified_primary_platform_domain(): void
    {
        Notification::fake();

        $this->post(route('register-school.store'), $this->registrationPayload())->assertRedirect();

        $school = School::query()->where('slug', 'newhorizon')->firstOrFail();
        $domain = $school->domains()->firstOrFail();

        $this->assertSame('newhorizon.schoolos.test', $domain->host);
        $this->assertTrue($domain->is_primary);
        $this->assertFalse($domain->is_custom);
        $this->assertNotNull($domain->verified_at);
    }

    public function test_registration_normalises_case_and_rejects_reserved_names(): void
    {
        Notification::fake();

        $this->post(route('register-school.store'), $this->registrationPayload(['subdomain' => 'MySchool']))
            ->assertRedirect();
        $this->assertDatabaseHas('schools', ['slug' => 'myschool']);
        auth()->logout();

        foreach (['admin', 'www', 'API'] as $reserved) {
            $this->post(route('register-school.store'), $this->registrationPayload([
                'subdomain' => $reserved,
                'admin_email' => "{$reserved}@reserved.test",
            ]))->assertSessionHasErrors('subdomain');
        }

        $this->assertDatabaseMissing('schools', ['slug' => 'admin']);
    }

    public function test_registration_rejects_invalid_hostnames(): void
    {
        foreach (['-bad', 'bad-', 'a--b', 'has_underscore', 'ab'] as $slug) {
            $this->post(route('register-school.store'), $this->registrationPayload(['subdomain' => $slug]))
                ->assertSessionHasErrors('subdomain');
        }
    }

    public function test_subdomain_check_endpoint_reports_status(): void
    {
        $this->getJson(route('register-school.subdomain-check', ['subdomain' => 'fresh-name']))
            ->assertOk()
            ->assertJson(['status' => 'available', 'available' => true, 'host' => 'fresh-name.schoolos.test']);

        $this->getJson(route('register-school.subdomain-check', ['subdomain' => 'alpha']))
            ->assertJson(['status' => 'taken', 'available' => false]);

        $this->getJson(route('register-school.subdomain-check', ['subdomain' => 'admin']))
            ->assertJson(['status' => 'reserved']);

        $this->getJson(route('register-school.subdomain-check', ['subdomain' => 'bad-']))
            ->assertJson(['status' => 'invalid']);
    }

    /* ------------------------------ connecting ----------------------------- */

    public function test_school_admin_connects_domain_which_stays_pending_until_dns_proves_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('school.domains.store'), ['host' => 'https://WWW.Alpha.gm/', 'make_primary' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $domain = Domain::query()->where('host', 'www.alpha.gm')->firstOrFail();
        $this->assertTrue($domain->is_custom);
        $this->assertNull($domain->verified_at);
        $this->assertFalse($domain->is_primary);
        $this->assertTrue($domain->primary_on_verify);
        $this->assertNotEmpty($domain->verification_token);
        $this->assertSame('dns_not_found', $domain->verification_error);
    }

    public function test_cname_to_the_schools_platform_host_verifies_and_becomes_primary(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];

        $this->actingAs($this->admin)
            ->post(route('school.domains.verify', $domain))
            ->assertSessionHas('success');

        $domain->refresh();
        $this->assertNotNull($domain->verified_at);
        $this->assertTrue($domain->is_primary);
        $this->assertSame('cname', $domain->verification_method);
        $this->assertFalse($this->school->domains()->where('host', 'alpha.schoolos.test')->first()->is_primary);
    }

    public function test_verified_domain_without_primary_intent_keeps_platform_url_as_main(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', false);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];

        $this->service()->verifyAndActivate($domain);

        $this->assertNotNull($domain->fresh()->verified_at);
        $this->assertFalse($domain->fresh()->is_primary);
        $this->assertTrue($this->school->domains()->where('host', 'alpha.schoolos.test')->first()->is_primary);
    }

    public function test_shared_cname_target_requires_txt_ownership_proof(): void
    {
        config(['schoolos.custom_domain_cname_target' => 'edge.schoolos.test']);

        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['edge.schoolos.test'];

        // Routing alone is not ownership.
        $this->assertFalse($this->service()->verify($domain));
        $this->assertSame('ownership_missing', $domain->fresh()->verification_error);

        $this->dns->txts['_schoolos-verify.www.alpha.gm'] = ['schoolos-verify='.$domain->verification_token];

        $this->assertTrue($this->service()->verify($domain->fresh()));
        $this->assertNotNull($domain->fresh()->verified_at);
    }

    public function test_ownership_proof_without_routing_is_reported(): void
    {
        config(['schoolos.custom_domain_cname_target' => 'edge.schoolos.test']);

        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->txts['_schoolos-verify.www.alpha.gm'] = ['schoolos-verify='.$domain->verification_token];

        $this->assertFalse($this->service()->verify($domain));
        $this->assertSame('routing_missing', $domain->fresh()->verification_error);
    }

    public function test_apex_domain_needs_a_record_plus_txt(): void
    {
        config(['schoolos.custom_domain_ip_target' => '203.0.113.10']);

        $domain = $this->service()->connectCustomDomain($this->school, 'alpha.gm', true);
        $records = collect($this->service()->dnsRecords($domain->load('school')));

        $routing = $records->firstWhere('purpose', 'routing');
        $txt = $records->firstWhere('purpose', 'ownership');
        $this->assertSame(['A', '@', '203.0.113.10'], [$routing['type'], $routing['name'], $routing['value']]);
        $this->assertTrue($txt['required']);
        $this->assertSame('_schoolos-verify', $txt['name']);

        $this->dns->ips['alpha.gm'] = ['203.0.113.10'];
        $this->assertFalse($this->service()->verify($domain));

        $this->dns->txts['_schoolos-verify.alpha.gm'] = ['schoolos-verify='.$domain->verification_token];
        $this->assertTrue($this->service()->verify($domain->fresh()));
    }

    public function test_multipart_suffix_and_subdomain_record_names(): void
    {
        $service = $this->service();

        $this->assertTrue($service->isApex('myschool.edu.gm'));
        $this->assertSame('@', $service->relativeName('myschool.edu.gm'));
        $this->assertSame('www', $service->relativeName('www.myschool.edu.gm'));
        $this->assertSame('portal.learn', $service->relativeName('portal.learn.myschool.gm'));
        $this->assertFalse($service->isApex('www.myschool.gm'));
    }

    /* ------------------------------- security ------------------------------ */

    public function test_platform_and_central_hosts_cannot_be_claimed(): void
    {
        foreach (['schoolos.test', 'foo.schoolos.test', 'localhost', 'not a host', 'nodots'] as $bad) {
            $this->actingAs($this->admin)
                ->post(route('school.domains.store'), ['host' => $bad])
                ->assertSessionHasErrors('host');
        }
    }

    public function test_unverified_claim_cannot_squat_a_domain_another_school_owns(): void
    {
        [$beta] = $this->makeSchool('beta');

        // Alpha pre-claims a domain it does not control.
        $squat = $this->service()->connectCustomDomain($this->school, 'www.beta.gm', true);

        // Beta (the real owner) claims it — the unproven squat is released.
        $this->service()->connectCustomDomain($beta, 'www.beta.gm', true);

        $this->assertDatabaseMissing('domains', ['id' => $squat->id]);
        $this->assertSame($beta->id, Domain::query()->where('host', 'www.beta.gm')->value('school_id'));
    }

    public function test_verified_domain_cannot_be_claimed_by_another_school(): void
    {
        [$beta] = $this->makeSchool('beta');
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];
        $this->service()->verifyAndActivate($domain);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->connectCustomDomain($beta, 'www.alpha.gm', true);
    }

    public function test_admin_cannot_manage_another_schools_domain(): void
    {
        [$beta, $betaAdmin] = $this->makeSchool('beta');
        $domain = $this->service()->connectCustomDomain($beta, 'www.beta.gm', true);

        $this->actingAs($this->admin)->post(route('school.domains.verify', $domain))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('school.domains.destroy', $domain))->assertNotFound();
        $this->actingAs($this->admin)->post(route('school.domains.primary', $domain))->assertNotFound();
    }

    public function test_non_admin_roles_cannot_open_domain_settings(): void
    {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
            'email_verified_at' => now(),
        ]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher)->get(route('school.domains.edit'))->assertForbidden();
    }

    public function test_domain_limit_applies_to_every_school(): void
    {
        config(['schoolos.custom_domain_limit' => 1]);
        $this->service()->connectCustomDomain($this->school, 'one.alpha.gm', true);
        $this->actingAs($this->admin)
            ->post(route('school.domains.store'), ['host' => 'two.alpha.gm'])
            ->assertSessionHasErrors('host');
    }

    /* ---------------------------- lifecycle & UI --------------------------- */

    public function test_domain_settings_page_exposes_records_and_state(): void
    {
        $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);

        $this->actingAs($this->admin)
            ->get(route('school.domains.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/Domain/Edit')
                ->where('customAllowed', true)
                ->where('customCount', 1)
                ->has('domains', 2)
                // Primary (platform) first, pending custom domain after it.
                ->where('domains.0.is_primary', true)
                ->where('domains.0.is_custom', false)
                ->where('domains.1.is_custom', true)
                ->where('domains.1.verified', false)
                ->has('domains.1.records', 2)
                ->where('domains.1.records.0.type', 'CNAME')
                ->where('domains.1.records.0.name', 'www')
                ->where('domains.1.records.0.value', 'alpha.schoolos.test')
                ->where('domains.1.records.1.type', 'TXT'));
    }

    public function test_polling_request_rechecks_pending_dns_and_flips_to_live(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];

        $this->actingAs($this->admin)
            ->get(route('school.domains.edit', ['check' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('urls.primary_is_custom', true));

        $this->assertTrue($domain->fresh()->is_primary);
    }

    public function test_removing_primary_custom_domain_restores_platform_url(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];
        $this->service()->verifyAndActivate($domain);

        $this->actingAs($this->admin)->delete(route('school.domains.destroy', $domain))->assertRedirect();

        $this->assertDatabaseMissing('domains', ['host' => 'www.alpha.gm']);
        $this->assertTrue($this->school->domains()->where('host', 'alpha.schoolos.test')->first()->is_primary);
    }

    public function test_platform_subdomain_cannot_be_removed(): void
    {
        $platform = $this->school->domains()->where('is_custom', false)->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('school.domains.destroy', $platform))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('domains', ['id' => $platform->id]);
    }

    /* ---------------------------- tenant routing --------------------------- */

    public function test_platform_subdomain_serves_the_school_site(): void
    {
        $this->get('http://alpha.schoolos.test/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'alpha'));
    }

    public function test_stale_platform_domain_row_still_serves_the_current_subdomain(): void
    {
        Domain::query()->where('school_id', $this->school->id)->update([
            'host' => 'alpha.gmschoolos.edu.gm',
        ]);

        $this->get('http://alpha.schoolos.test/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'alpha'));
    }

    public function test_live_host_subdomain_serves_the_school_even_when_base_domain_env_is_stale(): void
    {
        config([
            'app.url' => 'https://gambiaschoolos.com',
            'schoolos.central_domains' => ['gambiaschoolos.com', 'www.gambiaschoolos.com'],
            'schoolos.base_domain' => 'gmschoolos.edu.gm',
        ]);
        Domain::query()->where('school_id', $this->school->id)->update([
            'host' => 'alpha.gmschoolos.edu.gm',
        ]);

        $this->get('https://alpha.gambiaschoolos.com/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'alpha'));

        $this->get('https://gambiaschoolos.com/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Marketing/Landing'));
    }

    public function test_school_slug_subdomain_resolves_when_all_platform_roots_are_stale(): void
    {
        config([
            'app.url' => 'https://gmschoolos.edu.gm',
            'schoolos.central_domains' => ['gmschoolos.edu.gm'],
            'schoolos.base_domain' => 'gmschoolos.edu.gm',
        ]);
        Domain::query()->where('school_id', $this->school->id)->update([
            'host' => 'alpha.gmschoolos.edu.gm',
        ]);

        $this->get('https://alpha.gambiaschoolos.com/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'alpha'));
    }

    public function test_unknown_platform_subdomain_does_not_bind_a_tenant(): void
    {
        $this->get('http://missing.schoolos.test/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Marketing/Landing'));
    }

    public function test_forwarded_apex_does_not_hide_the_school_subdomain_host(): void
    {
        config([
            'app.url' => 'https://gambiaschoolos.com',
            'schoolos.central_domains' => ['gambiaschoolos.com', 'www.gambiaschoolos.com'],
            'schoolos.base_domain' => 'gambiaschoolos.com',
        ]);

        $this->get('https://alpha.gambiaschoolos.com/', [
            'X-Forwarded-Host' => 'gambiaschoolos.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'alpha'));

        $this->get('https://gambiaschoolos.com/', [
            'X-Forwarded-Host' => 'gambiaschoolos.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Marketing/Landing'));
    }

    public function test_rewritten_apex_host_still_serves_school_from_forwarded_subdomain(): void
    {
        config([
            'app.url' => 'https://gambiaschoolos.com',
            'schoolos.central_domains' => ['gambiaschoolos.com', 'www.gambiaschoolos.com'],
            'schoolos.base_domain' => 'gambiaschoolos.com',
        ]);

        $this->call('GET', 'https://gambiaschoolos.com/', [], [], [], [
            'HTTP_HOST' => 'gambiaschoolos.com',
            'HTTP_X_FORWARDED_HOST' => 'alpha.gambiaschoolos.com',
            'HTTP_X_ORIGINAL_HOST' => 'alpha.gambiaschoolos.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'on',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'alpha'));
    }

    public function test_unverified_custom_domain_does_not_resolve_a_tenant(): void
    {
        $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);

        $response = $this->get('http://www.alpha.gm/');

        // Falls through to the central landing (no tenant bound), never to the school site.
        $response->assertOk()->assertInertia(fn ($page) => $page->missing('school.slug'));
    }

    public function test_platform_host_redirects_public_pages_to_live_primary_custom_domain(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];
        $this->service()->verifyAndActivate($domain);
        $domain->update(['ssl_active' => true]);

        $this->get('http://alpha.schoolos.test/?ref=email')
            ->assertRedirect('https://www.alpha.gm/?ref=email');

        $this->get('http://alpha.schoolos.test/privacy')
            ->assertRedirect('https://www.alpha.gm/privacy');

        // Sign-in must never be redirected away (admins keep a guaranteed entry point).
        $login = $this->get('http://alpha.schoolos.test/login');
        $this->assertStringNotContainsString('alpha.gm', (string) $login->headers->get('Location'));
    }

    public function test_no_redirect_until_tls_is_active(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];
        $this->service()->verifyAndActivate($domain);
        $domain->update(['ssl_active' => false]);

        $response = $this->get('http://alpha.schoolos.test/');

        $this->assertNotSame('https://www.alpha.gm/', $response->headers->get('Location'));
    }

    public function test_verified_custom_domain_serves_the_school_site(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];
        $this->service()->verifyAndActivate($domain);

        $this->get('http://www.alpha.gm/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('School/PublicSite')
                ->where('school.slug', 'alpha'));
    }

    /* ----------------------------- background job -------------------------- */

    public function test_verify_command_activates_passing_domains_and_expires_stale_claims(): void
    {
        $good = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);
        $stale = $this->service()->connectCustomDomain($this->school, 'old.alpha.gm', true);
        Domain::query()->whereKey($stale->id)->update(['created_at' => now()->subDays(30)]);
        $waiting = $this->service()->connectCustomDomain($this->school, 'wait.alpha.gm', true);

        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];

        $this->artisan('schoolos:verify-domains')->assertSuccessful();

        $this->assertNotNull($good->fresh()->verified_at);
        $this->assertTrue($good->fresh()->is_primary);
        $this->assertDatabaseMissing('domains', ['id' => $stale->id]);
        $this->assertNull($waiting->fresh()->verified_at);
        $this->assertSame('dns_not_found', $waiting->fresh()->verification_error);
        $this->assertNotNull($waiting->fresh()->last_checked_at);
    }

    /* ---------------------------- TLS ask endpoint ------------------------- */

    public function test_tls_ask_endpoint_only_approves_verified_hosts(): void
    {
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true);

        $this->get('/_internal/domains/allow?domain=www.alpha.gm')->assertNotFound();

        $this->dns->cnames['www.alpha.gm'] = ['alpha.schoolos.test'];
        $this->service()->verifyAndActivate($domain);

        $this->get('/_internal/domains/allow?domain=www.alpha.gm')->assertOk();
        $this->get('/_internal/domains/allow?domain=evil.example')->assertNotFound();
        $this->get('/_internal/domains/allow')->assertNotFound();
    }

    public function test_tls_ask_endpoint_enforces_shared_token_and_skips_suspended_schools(): void
    {
        config(['schoolos.tls_ask_token' => 's3cret']);
        $domain = $this->service()->connectCustomDomain($this->school, 'www.alpha.gm', true, trusted: true);

        $this->get('/_internal/domains/allow?domain=www.alpha.gm')->assertForbidden();
        $this->get('/_internal/domains/allow?domain=www.alpha.gm&token=wrong')->assertForbidden();
        $this->get('/_internal/domains/allow?domain=www.alpha.gm&token=s3cret')->assertOk();

        $this->school->update(['suspended_at' => now(), 'status' => 'inactive']);
        $this->get('/_internal/domains/allow?domain=www.alpha.gm&token=s3cret')->assertNotFound();
    }

    /* ------------------------------ platform admin ------------------------- */

    public function test_platform_admin_adds_pre_verified_domain_through_the_service(): void
    {
        $operator = User::factory()->create(['school_id' => null, 'email_verified_at' => now()]);
        $operator->assignRole('platform_admin');

        $this->actingAs($operator)
            ->post(route('platform.schools.domains.store', $this->school), [
                'host' => 'Portal.Alpha.GM',
                'is_primary' => true,
            ])
            ->assertSessionHasNoErrors();

        $domain = Domain::query()->where('host', 'portal.alpha.gm')->firstOrFail();
        $this->assertNotNull($domain->verified_at);
        $this->assertTrue($domain->is_primary);

        $this->actingAs($operator)
            ->post(route('platform.schools.domains.store', $this->school), ['host' => 'schoolos.test'])
            ->assertSessionHasErrors('host');
    }
}
