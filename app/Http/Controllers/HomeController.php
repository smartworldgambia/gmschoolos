<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Marketing\LandingController;
use App\Http\Controllers\School\PublicSiteController;
use App\Services\Marketing\MarketingContent;
use App\Services\Scale\CacheTuner;
use App\Services\School\SchoolDomainService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HomeController extends Controller
{
    /**
     * Central hosts → SaaS marketing landing.
     * School tenant hosts → dedicated school public landing (when published).
     */
    public function __invoke(
        Request $request,
        TenantContext $tenant,
        SchoolDomainService $domains,
        CacheTuner $cache,
        MarketingContent $marketingContent,
        LandingController $landing,
        PublicSiteController $publicSite,
    ): Response {
        $school = $tenant->school()
            ?? $domains->schoolForHost($domains->requestHost($request));

        if ($school) {
            $tenant->set($school);

            if (! $school->isActive() || ! $school->website_published) {
                throw new NotFoundHttpException('School website is not available.');
            }

            return $publicSite($tenant, $cache);
        }

        return $landing($cache, $marketingContent);
    }
}
