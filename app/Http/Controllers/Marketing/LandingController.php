<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Marketing\MarketingContent;
use App\Services\Scale\CacheTuner;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(CacheTuner $cache, MarketingContent $marketingContent): Response
    {
        $liveStats = Cache::remember('schoolos:landing_stats', 120, fn () => [
            'schools' => School::query()->where('status', 'active')->whereNull('suspended_at')->count(),
            'students' => Student::withoutGlobalScopes()->whereNull('withdrawn_on')->count(),
            'staff' => Staff::withoutGlobalScopes()->where('status', 'active')->count(),
            'parents' => Guardian::withoutGlobalScopes()->count(),
            'classes' => SchoolClass::withoutGlobalScopes()->count(),
        ]);

        $marketing = config('schoolos.marketing', []);
        $statsMode = 'live';
        $demoEmail = $marketing['demo_email'] ?? $marketing['contact_email'] ?? null;
        $demoUrl = $demoEmail
            ? 'mailto:'.$demoEmail.'?subject='.rawurlencode('Gambia SchoolOS demo request')
            : '#contact';

        return Inertia::render('Marketing/Landing', [
            'prices' => $cache->landingPrices(),
            'stats' => $liveStats,
            'statsMode' => $statsMode,
            'illustrativeStats' => $marketing['illustrative_stats'] ?? [],
            'trialDays' => (int) config('schoolos.trial_days', 30),
            'registerUrl' => route('register-school.create'),
            'loginUrl' => route('login'),
            'demoUrl' => $demoUrl,
            'marketing' => [
                'tagline' => $marketing['tagline'] ?? null,
                'contact_email' => $marketing['contact_email'] ?? null,
                'support_email' => $marketing['support_email'] ?? null,
                'phone' => $marketing['phone'] ?? null,
                'phone_secondary' => $marketing['phone_secondary'] ?? null,
                'address' => $marketing['address'] ?? null,
                'website' => $marketing['website'] ?? null,
                'social' => $marketing['social'] ?? [],
                'testimonial' => $marketingContent->testimonial(),
                'hero_image' => '/images/marketing/hero-students.jpg',
                'hero_slides' => [
                    [
                        'src' => '/images/marketing/hero-students.jpg',
                        'alt' => 'Secondary students in Gambian school uniforms with a tablet',
                        'caption' => 'Secondary learners',
                    ],
                    [
                        'src' => '/images/marketing/hero-classroom.jpg',
                        'alt' => 'Teacher supporting students with a laptop in class',
                        'caption' => 'Teaching & LMS',
                    ],
                    [
                        'src' => '/images/marketing/hero-primary.jpg',
                        'alt' => 'Primary school children smiling in a Gambian school courtyard',
                        'caption' => 'Primary learners',
                    ],
                ],
            ],
            'seo' => [
                'title' => 'Gambia SchoolOS — Simplifying School Management and Learning',
                'description' => 'Cloud-based school management and LMS for Gambian schools — administration, teaching, examinations, fees and operations.',
                'image' => url('/images/marketing/hero-students.jpg'),
            ],
        ]);
    }
}
