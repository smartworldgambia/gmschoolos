<?php

namespace App\Services\Onboarding;

use App\Models\AcademicYear;
use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\SchoolRegistrationReview;
use App\Models\Term;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\Platform\QuotaService;
use App\Services\School\SchoolDomainService;
use App\Services\School\SchoolLandingProvisioner;
use App\Support\SchoolTypes;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The single, controlled way a school comes into existence (self-service registration).
 *
 * Order matters and every step is inside one transaction, so a failure leaves nothing half-provisioned:
 *   duplicate check -> school record (+ unique slug, official fields, terms acceptance) -> tenant binding ->
 *   public website -> platform subdomain -> annual price + 30-day trial -> quota -> academic year/term ->
 *   initial administrator (unverified) + permissions -> default settings -> review flag.
 *
 * It deliberately does NOT invent classes, students, staff, fees or results. The academic structure is the
 * school-type level scaffold seeded with the website; everything else is entered by the school in the setup wizard.
 */
class SchoolProvisioner
{
    public function __construct(
        protected TenantContext $tenant,
        protected DuplicateDetector $duplicates,
        protected SchoolDomainService $domains,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated registration input
     * @return array{school: School, admin: User, review: ?SchoolRegistrationReview, flags: list<string>, trial_ends_at: ?\Illuminate\Support\Carbon}
     *
     * @throws DuplicateInstitutionException when the institution is certainly already registered
     */
    public function register(array $data): array
    {
        $check = $this->duplicates->check([
            'school_name' => $data['school_name'],
            'region' => $data['region'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'school_email' => $data['school_email'] ?? null,
            'school_phone' => $data['school_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'admin_email' => $data['admin_email'] ?? null,
        ]);

        if ($check['blocked']) {
            throw new DuplicateInstitutionException($check['block']);
        }

        $result = DB::transaction(fn () => $this->provision($data, $check['flags'], $check['matched']));

        return $result;
    }

    /**
     * @param  list<string>  $flags
     * @param  list<int>  $matched
     * @return array{school: School, admin: User, review: ?SchoolRegistrationReview, flags: list<string>, trial_ends_at: ?\Illuminate\Support\Carbon}
     */
    protected function provision(array $data, array $flags, array $matched): array
    {
        $category = SchoolTypes::normalise((string) $data['category']);
        $trialDays = (int) config('schoolos.trial_days', 30);

        $logoPath = ($data['logo'] ?? null) instanceof UploadedFile ? $data['logo']->store('schools/logos', 'public') : null;

        $school = School::query()->create([
            'name' => $data['school_name'],
            'slug' => $data['subdomain'],
            'category' => $category,
            'registration_number' => $data['registration_number'] ?? null,
            'country' => 'GM',
            'region' => $data['region'] ?? null,
            'district' => $data['district'] ?? null,
            'address' => $data['address'] ?? null,
            'email' => $data['school_email'] ?? $data['admin_email'],
            'billing_email' => $data['admin_email'],
            'phone' => $data['school_phone'] ?? $data['admin_phone'] ?? null,
            'logo_path' => $logoPath,
            'status' => 'active',
            'website_published' => false,
            'terms_accepted_at' => now(),
            'terms_version' => (string) config('schoolos.onboarding.terms_version'),
        ]);

        // From here on every row belongs to this school: bind it explicitly (tenancy fails closed).
        $trialEndsAt = null;
        $admin = $this->tenant->runForSchool($school, function () use ($school, $data, $category, $trialDays, &$trialEndsAt) {
            app(SchoolLandingProvisioner::class)->provision($school);
            $this->domains->assignPlatformDomain($school);

            // 30-day trial: starts now, no payment details; price snapshot comes from the school-type price table.
            $trialEndsAt = app(SubscriptionLifecycle::class)->startTrial($school, $trialDays, $category)->trial_ends_at;
            app(QuotaService::class)->forSchool($school->fresh('subscription'));

            $year = AcademicYear::query()->create([
                'school_id' => $school->id,
                'name' => $data['academic_year_name'],
                'starts_on' => now()->startOfYear()->toDateString(),
                'ends_on' => now()->endOfYear()->toDateString(),
                'is_current' => true,
            ]);

            Term::query()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'name' => $data['term_name'],
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addMonths(3)->toDateString(),
                'is_current' => true,
            ]);

            // Neutral operating defaults only (no fabricated business rules); merged, never replacing the landing settings.
            $school->forceFill(['settings' => array_merge((array) $school->settings, [
                'defaults' => config('schoolos.onboarding.defaults'),
                'parent_access' => ['enabled' => true],
            ])])->save();

            $admin = User::query()->create([
                'school_id' => $school->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'phone' => $data['admin_phone'] ?? null,
                'password' => Hash::make($data['password']),
                // email_verified_at stays NULL: the administrator must verify the address before using the app.
            ]);
            $admin->assignRole('school_admin');

            return $admin->load('school.domains');
        });

        $review = null;
        if ($flags !== []) {
            $review = $this->tenant->runAsPlatform(function () use ($school, $flags, $matched) {
                $review = SchoolRegistrationReview::query()->create([
                    'school_id' => $school->id,
                    'reasons' => $flags,
                    'matched_school_ids' => array_values(array_diff($matched, [$school->id])),
                    'status' => SchoolRegistrationReview::PENDING,
                ]);

                PlatformAlert::query()->create([
                    'severity' => 'warning',
                    'source' => 'registration',
                    'title' => 'Possible duplicate school registration',
                    'message' => $school->name.' looks similar to an existing institution ('.implode(', ', $flags).'). Review before the free trial is relied on.',
                    'school_id' => $school->id,
                    'context' => ['reasons' => $flags, 'review_id' => $review->id],
                ]);

                return $review;
            });
        }

        return ['school' => $school, 'admin' => $admin, 'review' => $review, 'flags' => $flags, 'trial_ends_at' => $trialEndsAt];
    }
}