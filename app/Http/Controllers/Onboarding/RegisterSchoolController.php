<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Notifications\SchoolRegisteredWelcome;
use App\Services\Billing\PricingService;
use App\Services\Onboarding\DuplicateInstitutionException;
use App\Services\Onboarding\SchoolProvisioner;
use App\Services\School\SchoolDomainService;
use App\Support\Money;
use App\Support\SchoolTypes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterSchoolController extends Controller
{
    /** Registration form values; each maps to exactly one annual price (see SchoolTypes). */
    public const CATEGORIES = ['Nursery', 'Primary', 'Basic Secondary', 'Senior Secondary', 'Combined', 'International', 'Other'];

    public function create(Request $request): Response
    {
        // One annual price per school type, keyed by the form value so the wizard can show the fee for the chosen category.
        $pricing = app(PricingService::class);
        $prices = [];
        foreach (self::CATEGORIES as $form) {
            $price = $pricing->current(SchoolTypes::normalise($form));
            $prices[$form] = $price ? [
                'amount' => $price->totalAmount(),
                'currency' => $price->currency,
                'label' => Money::formatCurrency($price->totalAmount(), $price->currency),
                'taxed' => $price->tax_rate !== null,
            ] : null;
        }

        $preselected = $request->query('type');
        if (! in_array($preselected, self::CATEGORIES, true)) {
            $preselected = null;
        }
        return Inertia::render('Onboarding/RegisterSchool', [
            'prices' => $prices,
            'preselectedCategory' => $preselected,
            'submitUrl' => route('register-school.store'),
            'subdomainCheckUrl' => route('register-school.subdomain-check'),
            'baseDomain' => app(SchoolDomainService::class)->baseDomain(),
            'trialDays' => (int) config('schoolos.trial_days', 30),
            'legal' => ['terms' => route('terms'), 'privacy' => route('privacy')],
            'categories' => self::CATEGORIES,
            'regions' => [
                'Banjul',
                'Kanifing',
                'Brikama',
                'Kerewan',
                'Mansakonko',
                'Kuntaur',
                'Janjanbureh',
                'Basse',
            ],
        ]);
    }

    public function store(Request $request, SchoolProvisioner $provisioner): RedirectResponse
    {
        $domains = app(SchoolDomainService::class);
        $request->merge(['subdomain' => $domains->normalizeSlug((string) $request->input('subdomain', ''))]);

        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(SchoolTypes::FORM_VALUES))],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'school_email' => ['required', 'email', 'max:255'],
            'school_phone' => ['required', 'string', 'min:7', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'subdomain' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($domains) {
                $check = $domains->slugStatus((string) $value);
                if (! $check['available']) {
                    $fail($domains->slugErrorMessage($check['status']));
                }
            }],
            'academic_year_name' => ['required', 'string', 'max:50'],
            'term_name' => ['required', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_phone' => ['required', 'string', 'min:7', 'max:50'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'logo' => ['nullable', 'image', 'max:2048'],
            'terms' => ['accepted'],
        ]);

        $trialDays = (int) config('schoolos.trial_days', 30);

        try {
            $result = $provisioner->register($validated + ['logo' => $request->file('logo')]);
        } catch (DuplicateInstitutionException) {
            // Deliberately generic: never reveal which school matched.
            return back()
                ->withErrors(['school_name' => 'A school with these details is already registered on Gambia SchoolOS. If it is yours, sign in or contact support.'])
                ->withInput();
        } catch (UniqueConstraintViolationException $e) {
            // Another registration claimed the subdomain / email between validation and insert.
            $field = str_contains(strtolower($e->getMessage()), 'email') ? 'admin_email' : 'subdomain';

            return back()
                ->withErrors([$field => $field === 'subdomain'
                    ? $domains->slugErrorMessage('taken')
                    : 'This email is already registered.'])
                ->withInput();
        }

        $user = $result['admin'];

        auth()->login($user);
        $request->session()->regenerate();

        $urls = $domains->urlsFor($user->school);
        $host = $urls['platform_host'];
        $dashboardUrl = $urls['admin_url'];
        $publicSiteUrl = $urls['primary_url'];

        // One message: welcome + 30-day trial confirmation (dates). Delivered on the notifications queue.
        $user->notify(new SchoolRegisteredWelcome(
            school: $user->school,
            dashboardUrl: $dashboardUrl,
            schoolHost: $host,
            trialDays: $trialDays,
            publicSiteUrl: $publicSiteUrl,
            trialEndsOn: $result['trial_ends_at']?->toFormattedDateString(),
        ));

        // The admin address is unverified: send the signed verification link and hold the user on
        // the verification screen until they confirm. (Mail driver `log` writes the link to the log.)
        $user->sendEmailVerificationNotification();

        // After verification the dashboard route sends a new administrator straight into the setup wizard.
        return redirect()
            ->route('verification.notice')
            ->with('success', 'Your school has been registered. Verify your email address to open your dashboard and continue with school setup.')
            ->with('school_url', $urls['primary_url'])
            ->with('school_host', $urls['primary_host']);
    }

    /**
     * Live availability for the registration form: GET /register-school/subdomain-check?subdomain=abc
     */
    public function checkSubdomain(Request $request, SchoolDomainService $domains): JsonResponse
    {
        $check = $domains->slugStatus((string) $request->query('subdomain', ''));

        return response()->json([
            'status' => $check['status'],
            'available' => $check['available'],
            'slug' => $check['slug'],
            'host' => $check['host'],
        ]);
    }

    /**
     * @param  array{primary_host: ?string, platform_host: string, primary_url: string, platform_url: string, path_url: string, admin_url: string, uses_path_preview: bool}  $urls
     */
    protected function postRegistrationRedirect(array $urls, string $dashboardUrl): RedirectResponse
    {
        $message = 'Your school has been registered successfully. A welcome message was sent to your admin email.';

        if ($this->shouldRedirectToTenantHost()) {
            return redirect()->away($dashboardUrl)
                ->with('success', $message)
                ->with('school_url', $urls['primary_url'])
                ->with('school_host', $urls['primary_host']);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $message)
            ->with('school_url', $urls['primary_url'])
            ->with('school_host', $urls['primary_host']);
    }

    protected function shouldRedirectToTenantHost(): bool
    {
        if (! config('schoolos.redirect_to_tenant_after_register')) {
            return false;
        }

        $baseDomain = app(SchoolDomainService::class)->baseDomain();
        if (in_array($baseDomain, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        // Cookie must be shareable across subdomains or the session is lost on hop.
        $sessionDomain = (string) config('session.domain');

        return $sessionDomain !== '' && str_starts_with($sessionDomain, '.');
    }
}
