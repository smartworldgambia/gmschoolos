<?php

namespace App\Http\Controllers\Examinations;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Services\Results\ResultsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Per-school assessment categories (weights) and grading scale. */
class AssessmentSettingsController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function index(ResultsService $results): Response
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);

        return Inertia::render('Examinations/Settings', array_merge($this->shellProps(), [
            'categories' => $results->categories(false)->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'type' => $c->type,
                'weight' => (float) $c->weight, 'max_score' => (float) $c->max_score, 'is_active' => (bool) $c->is_active,
            ]),
            'bands' => $results->scale()->bands->map(fn ($b) => ['letter' => $b->letter, 'min_score' => (float) $b->min_score, 'remark' => $b->remark]),
        ]));
    }

    public function saveCategories(Request $request, ResultsService $results): RedirectResponse
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);
        $v = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:12'],
            'rows.*.id' => ['nullable', 'integer', $this->inSchool('assessment_categories')],
            'rows.*.name' => ['required', 'string', 'max:100'],
            'rows.*.code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'rows.*.type' => ['required', 'in:ca,exam'],
            'rows.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'rows.*.max_score' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'rows.*.is_active' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(fn () => $results->saveCategories($v['rows']), 'ok.settingsSaved');
    }

    public function saveScale(Request $request, ResultsService $results): RedirectResponse
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);
        $v = $request->validate([
            'bands' => ['required', 'array', 'min:2', 'max:15'],
            'bands.*.letter' => ['required', 'string', 'max:10'],
            'bands.*.min_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.remark' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->attempt(fn () => $results->saveScale($v['bands']), 'ok.settingsSaved');
    }
}
