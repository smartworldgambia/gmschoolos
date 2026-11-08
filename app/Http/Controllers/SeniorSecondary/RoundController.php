<?php

namespace App\Http\Controllers\SeniorSecondary;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\SsApplication;
use App\Models\SsRound;
use App\Services\SeniorSecondary\SeniorSecondaryService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoundController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected SeniorSecondaryService $service) {}

    public function index(): Response
    {
        $rounds = SsRound::query()->withCount('applications')->orderByDesc('id')->get()->map(fn (SsRound $r) => $this->summary($r));

        return Inertia::render('SeniorSecondary/Rounds', $this->shellProps() + [
            'rounds' => $rounds, 'can' => ['manage' => auth()->user()->can('senior_admissions.manage')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $rules] = $this->payload($request);

        return $this->attempt(function () use ($data, $rules) {
            $round = $this->service->saveRound($data, $rules);

            return redirect()->route('senior-secondary.rounds.show', $round)->with('success', 'ok.ss.roundSaved');
        });
    }

    public function show(Request $request, SsRound $round): Response
    {
        $q = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');
        $apps = SsApplication::query()->where('round_id', $round->id)
            ->when($status !== '', fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('reference', 'like', "%{$q}%")))
            ->orderByRaw('case when rank_position is null then 1 else 0 end')->orderBy('rank_position')->orderBy('id')
            ->paginate(25)->withQueryString()
            ->through(fn (SsApplication $a) => [
                'id' => $a->id, 'reference' => $a->reference, 'name' => trim($a->first_name.' '.$a->last_name), 'status' => $a->status,
                'aggregate' => $a->aggregate, 'eligible' => $a->eligible, 'rank' => $a->rank_position, 'recommendation' => $a->recommendation,
                'verified' => $a->results_verified_at !== null, 'override' => $a->is_override, 'programme_choice' => $a->programme_choice,
            ]);

        $school = app(TenantContext::class)->school();
        $counts = SsApplication::query()->where('round_id', $round->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('SeniorSecondary/Round', $this->shellProps() + [
            'round' => $this->summary($round) + [
                'instructions' => $round->instructions, 'rules' => $this->rulesForForm($round->rules ?? []),
                'public_url' => $round->status === 'open' ? route('school.senior.apply', ['school_slug' => $school->slug, 'round_slug' => $round->slug]) : null,
            ],
            'applications' => $apps, 'counts' => $counts, 'status' => $status, 'q' => $q,
            'can' => ['manage' => auth()->user()->can('senior_admissions.manage')],
        ]);
    }

    public function update(Request $request, SsRound $round): RedirectResponse
    {
        [$data, $rules] = $this->payload($request);

        return $this->attempt(fn () => $this->service->saveRound($data, $rules, $round), 'ok.ss.roundSaved');
    }

    public function status(Request $request, SsRound $round): RedirectResponse
    {
        $v = $request->validate(['status' => ['required', 'in:draft,open,closed,finalised']]);

        return $this->attempt(function () use ($v, $round, $request) {
            $v['status'] === 'finalised' ? $this->service->finalise($request->user(), $round) : $this->service->setRoundStatus($round, $v['status']);
        });
    }

    public function recompute(Request $request, SsRound $round): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->recompute($round, $request->user()), 'ok.ss.recomputed');
    }

    public function apply(Request $request, SsRound $round): RedirectResponse
    {
        return $this->attempt(function () use ($request, $round) {
            $n = $this->service->applyRecommendations($request->user(), $round);

            return back()->with('success', 'ok.ss.applied')->with('count', $n);
        });
    }

    // ------------------------------------------------------------------ helpers

    protected function summary(SsRound $r): array
    {
        return [
            'id' => $r->id, 'name' => $r->name, 'slug' => $r->slug, 'status' => $r->status, 'capacity' => $r->capacity,
            'waitlist_capacity' => $r->waitlist_capacity, 'opens_on' => $r->opens_on?->toDateString(), 'closes_on' => $r->closes_on?->toDateString(),
            'applications_count' => $r->applications_count ?? $r->applications()->count(), 'ranked_at' => $r->ranked_at?->toDateTimeString(),
        ];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    protected function payload(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'instructions' => ['nullable', 'string', 'max:3000'],
            'opens_on' => ['nullable', 'date'], 'closes_on' => ['nullable', 'date'],
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'], 'waitlist_capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rules' => ['required', 'array'],
            'rules.direction' => ['required', 'in:lower_better,higher_better'],
            'rules.subjects' => ['required', 'array', 'min:1', 'max:40'],
            'rules.subjects.*.code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'rules.subjects.*.name' => ['required', 'string', 'max:150'],
            'rules.grade_points' => ['required', 'array', 'min:1', 'max:30'],
            'rules.grade_points.*.grade' => ['required', 'string', 'max:12'], 'rules.grade_points.*.points' => ['required', 'numeric', 'between:-1000,1000'],
            'rules.required_subjects' => ['nullable', 'array'], 'rules.required_subjects.*' => ['string', 'max:30'],
            'rules.weights' => ['nullable', 'array'], 'rules.weights.*.code' => ['nullable', 'string', 'max:30'], 'rules.weights.*.weight' => ['nullable', 'numeric'],
            'rules.combinations' => ['nullable', 'array', 'max:20'], 'rules.combinations.*.name' => ['nullable', 'string', 'max:100'],
            'rules.combinations.*.subjects' => ['nullable', 'array'], 'rules.combinations.*.min' => ['nullable', 'integer', 'min:1'],
            'rules.pass_limit' => ['nullable', 'numeric'], 'rules.elective_count' => ['nullable', 'integer', 'min:0', 'max:40'],
            'rules.min_subjects' => ['nullable', 'integer', 'min:0', 'max:40'], 'rules.aggregate_threshold' => ['nullable', 'numeric'],
        ]);

        $r = $v['rules'];
        $rules = [
            'direction' => $r['direction'], 'subjects' => $r['subjects'],
            'grade_points' => collect($r['grade_points'])->mapWithKeys(fn ($g) => [(string) $g['grade'] => $g['points']])->all(),
            'required_subjects' => $r['required_subjects'] ?? [],
            'weights' => collect($r['weights'] ?? [])->filter(fn ($w) => filled($w['code'] ?? null))->mapWithKeys(fn ($w) => [(string) $w['code'] => $w['weight'] ?? null])->all(),
            'combinations' => $r['combinations'] ?? [],
            'pass_limit' => $r['pass_limit'] ?? null, 'elective_count' => $r['elective_count'] ?? null,
            'min_subjects' => $r['min_subjects'] ?? 0, 'aggregate_threshold' => $r['aggregate_threshold'] ?? null,
        ];

        return [collect($v)->except('rules')->all() + ['waitlist_capacity' => $v['waitlist_capacity'] ?? 0], $rules];
    }

    /** Stored rules -> the list shapes the form edits. */
    protected function rulesForForm(array $rules): array
    {
        if ($rules === []) {
            return [];
        }

        return [
            'direction' => $rules['direction'], 'subjects' => $rules['subjects'],
            'grade_points' => collect($rules['grade_points'])->map(fn ($p, $g) => ['grade' => (string) $g, 'points' => $p])->values()->all(),
            'required_subjects' => $rules['required_subjects'],
            'weights' => collect($rules['weights'])->map(fn ($w, $c) => ['code' => (string) $c, 'weight' => $w])->values()->all(),
            'combinations' => $rules['combinations'], 'pass_limit' => $rules['pass_limit'], 'elective_count' => $rules['elective_count'],
            'min_subjects' => $rules['min_subjects'], 'aggregate_threshold' => $rules['aggregate_threshold'],
        ];
    }
}
