<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Admissions\ApplicationRules;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentDocument;
use App\Models\User;
use App\Notifications\AdmissionApplicationReceived;
use App\Services\Admissions\AdmissionWorkflow;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public, unauthenticated online application. It can only ever create an AdmissionApplication in status
 * `submitted`; it never creates a student, a user, or anything the school has not reviewed.
 */
class PublicApplicationController extends Controller
{
    public function create(TenantContext $tenant, string $school_slug): Response
    {
        $school = $this->school($tenant, $school_slug);

        return Inertia::render('School/Apply', [
            'school' => ['name' => $school->name, 'slug' => $school->slug],
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name']),
            'documentTypes' => StudentDocument::TYPES,
            'submitUrl' => route('school.apply.store', ['school_slug' => $school->slug]),
            'siteUrl' => route('school.site', ['school_slug' => $school->slug]),
            'applied' => session('applied'),
            'legal' => ['privacy' => route('privacy')],
        ]);
    }

    public function store(Request $request, TenantContext $tenant, AdmissionWorkflow $workflow, string $school_slug): RedirectResponse
    {
        $school = $this->school($tenant, $school_slug);

        // Honeypot: real visitors never fill this. Pretend success so bots learn nothing.
        if (filled($request->input('company_website'))) {
            return back()->with('applied', 'ADM-RECEIVED');
        }

        $validated = $request->validate([
            ...ApplicationRules::fields(Rule::exists('school_classes', 'id')->where('school_id', $school->id)),
            ...ApplicationRules::documents(),
            'consent' => ['accepted'],
        ]);

        // A repeat of the same child + guardian phone within 30 days is not stored twice (and the visitor is not told).
        if ($workflow->recentDuplicate($school, $validated['first_name'], $validated['last_name'], $validated['date_of_birth'], $validated['guardian_phone'])) {
            return back()->with('applied', 'ADM-RECEIVED');
        }

        $application = $workflow->submit(
            $school,
            collect($validated)->except(['documents', 'consent'])->all(),
            'online',
            ApplicationRules::uploads($request),
            $request->ip(),
        );

        $staff = User::query()->withoutGlobalScopes()->where('school_id', $school->id)
            ->permission('admissions.manage')->get();
        if ($staff->isNotEmpty()) {
            Notification::send($staff, new AdmissionApplicationReceived($application, $school->name, 'staff'));
        }
        if (filled($application->guardian_email)) {
            Notification::route('mail', $application->guardian_email)
                ->notify(new AdmissionApplicationReceived($application, $school->name, 'applicant'));
        }

        return back()->with('applied', $application->application_number);
    }

    protected function school(TenantContext $tenant, string $slug): School
    {
        $school = $tenant->school() ?? School::query()->where('slug', $slug)->first();
        if (! $school || ! $school->isActive() || ! $school->website_published) {
            throw new NotFoundHttpException('Applications are not available.');
        }
        $tenant->set($school);

        return $school;
    }
}
