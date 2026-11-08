<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\Scale\CacheTuner;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicSiteController extends Controller
{
    public function __invoke(TenantContext $tenant, CacheTuner $cache, ?string $school_slug = null): Response
    {
        $school = $tenant->school();

        if (! $school && $school_slug) {
            $school = School::query()
                ->where('slug', $school_slug)
                ->where('status', 'active')
                ->first();
        }

        if (! $school || ! $school->isActive()) {
            throw new NotFoundHttpException('School not found.');
        }

        if (! $school->website_published) {
            throw new NotFoundHttpException('School website is not available.');
        }

        $tenant->set($school);

        $payload = $cache->publicSitePayload($school);
        // Carry the school into the shared login page so it can show this school's brand.
        $loginUrl = route('login', ['school' => $school->slug]);

        return Inertia::render('School/PublicSite', [
            ...$payload,
            'loginUrl' => $loginUrl,
            'admissionsUrl' => route('school.apply', ['school_slug' => $school->slug]),
            'contactUrl' => route('school.contact', ['school_slug' => $school->slug]),
        ]);
    }
}
