<?php

namespace App\Http\Controllers\School;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SsApplication;
use App\Models\SsRound;
use App\Models\User;
use App\Notifications\SeniorApplicationReceived;
use App\Services\SeniorSecondary\SeniorSecondaryService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public, unauthenticated Senior Secondary application. It can only create an application in status `submitted`;
 * it never creates a student, a user or an offer. Grades are DECLARED by the applicant and are verified by staff.
 */
class PublicSeniorApplicationController extends Controller
{
    public const DOCUMENT_TYPES = ['gabece_slip', 'birth_certificate', 'previous_report', 'other'];

    public function create(TenantContext $tenant, SeniorSecondaryService $service, string $school_slug, ?string $round_slug = null): Response
    {
        $school = $this->school($tenant, $school_slug);
        $base = ['school' => ['name' => $school->name, 'slug' => $school->slug], 'siteUrl' => route('school.site', ['school_slug' => $school->slug]), 'legal' => ['privacy' => route('privacy')]];

        if ($round_slug === null) {
            $rounds = SsRound::query()->where('status', 'open')->orderBy('closes_on')->get()
                ->filter(fn (SsRound $r) => $service->isAcceptingApplications($r))
                ->map(fn (SsRound $r) => ['name' => $r->name, 'closes_on' => $r->closes_on?->toDateString(), 'url' => route('school.senior.apply', ['school_slug' => $school->slug, 'round_slug' => $r->slug])])
                ->values();

            return Inertia::render('School/SeniorApply', $base + ['round' => null, 'rounds' => $rounds, 'applied' => null]);
        }

        $round = $this->round($round_slug);
        $rules = $round->rules;

        return Inertia::render('School/SeniorApply', $base + [
            'rounds' => [],
            'round' => [
                'name' => $round->name, 'instructions' => $round->instructions, 'closes_on' => $round->closes_on?->toDateString(),
                'open' => $service->isAcceptingApplications($round),
                'subjects' => $rules['subjects'], 'grades' => array_keys($rules['grade_points']),
                'programmes' => array_column($rules['combinations'], 'name'),
            ],
            'documentTypes' => self::DOCUMENT_TYPES,
            'submitUrl' => route('school.senior.apply.store', ['school_slug' => $school->slug, 'round_slug' => $round->slug]),
            'applied' => session('applied'),
        ]);
    }

    public function store(Request $request, TenantContext $tenant, SeniorSecondaryService $service, string $school_slug, string $round_slug): RedirectResponse
    {
        $school = $this->school($tenant, $school_slug);
        $round = $this->round($round_slug);

        // Honeypot: real visitors never fill this. Pretend success so bots learn nothing.
        if (filled($request->input('company_website'))) {
            return back()->with('applied', 'SSA-RECEIVED');
        }

        $v = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1990-01-01'],
            'nationality' => ['nullable', 'string', 'max:100'], 'previous_school' => ['required', 'string', 'max:200'],
            'gabece_index_number' => ['nullable', 'string', 'max:60'], 'gabece_year' => ['nullable', 'integer', 'between:2000,2100'],
            'guardian_name' => ['required', 'string', 'max:150'], 'guardian_phone' => ['required', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email:rfc', 'max:150'], 'address' => ['nullable', 'string', 'max:255'],
            'programme_choice' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'grades' => ['required', 'array'], 'grades.*' => ['nullable', 'string', 'max:12'],
            'documents' => ['nullable', 'array', 'max:4'],
            'documents.*.type' => ['required_with:documents.*.file', 'in:'.implode(',', self::DOCUMENT_TYPES)],
            'documents.*.file' => ['required_with:documents.*.type', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('schoolos.students.document_max_kb')],
            'consent' => ['accepted'],
        ]);

        // The same child + guardian phone within 30 days is not stored twice (and the visitor is not told).
        $dup = SsApplication::query()->where('round_id', $round->id)->where('first_name', $v['first_name'])->where('last_name', $v['last_name'])
            ->whereDate('date_of_birth', $v['date_of_birth'])->where('guardian_phone', $v['guardian_phone'])->where('created_at', '>=', now()->subDays(30))->exists();
        if ($dup) {
            return back()->with('applied', 'SSA-RECEIVED');
        }

        $uploads = [];
        foreach ((array) $request->file('documents', []) as $i => $row) {
            if (isset($row['file'])) {
                $uploads[] = ['type' => (string) $request->input("documents.$i.type", 'other'), 'file' => $row['file']];
            }
        }

        try {
            $application = $service->submit($school, $round, collect($v)->except(['grades', 'documents', 'consent'])->all(), $v['grades'], 'online', $uploads, $request->ip());
        } catch (AcademicRuleException $e) {
            return back()->withErrors(['grades' => $e->getMessage()])->withInput();
        }

        $staff = User::query()->withoutGlobalScopes()->where('school_id', $school->id)->permission('senior_admissions.manage')->get();
        if ($staff->isNotEmpty()) {
            Notification::send($staff, new SeniorApplicationReceived($application, $school->name, 'staff'));
        }
        if (filled($application->guardian_email)) {
            Notification::route('mail', $application->guardian_email)->notify(new SeniorApplicationReceived($application, $school->name, 'applicant'));
        }

        return back()->with('applied', $application->reference);
    }

    protected function school(TenantContext $tenant, string $slug): School
    {
        $school = $tenant->school() ?? School::query()->where('slug', $slug)->first();
        if (! $school || $school->slug !== $slug || ! $school->isActive() || ! $school->website_published) {
            throw new NotFoundHttpException;
        }
        $tenant->set($school);

        return $school;
    }

    protected function round(string $slug): SsRound
    {
        $round = SsRound::query()->where('slug', $slug)->whereIn('status', ['open', 'closed'])->first();
        if (! $round) {
            throw new NotFoundHttpException;
        }

        return $round;
    }
}
