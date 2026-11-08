<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Marketing\LandingController;
use App\Http\Controllers\School\PublicSiteController;
use App\Services\Marketing\MarketingContent;
use App\Services\Scale\CacheTuner;
use App\Support\TenantContext;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HomeController extends Controller
{
    /**
     * Central hosts → SaaS marketing landing.
     * School tenant hosts → dedicated school public landing (when published).
     */
    public function __invoke(
        TenantContext $tenant,
        CacheTuner $cache,
        MarketingContent $marketingContent,
        LandingController $landing,
        PublicSiteController $publicSite,
    ): Response {
        $school = $tenant->school();

        if ($school) {
            if (! $school->isActive() || ! $school->website_published) {
                throw new NotFoundHttpException('School website is not available.');
            }

            return $publicSite($tenant, $cache);
        }

        return $landing($cache, $marketingContent);
    }
}
