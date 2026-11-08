<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicReportTemplate;
use App\Services\Reporting\AcademicReportSettings;
use App\Services\Reporting\ReportingAccess;
use App\Services\Results\ResultsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportingSettingsController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function templates(AcademicReportSettings $settings): Response
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);
        $settings->ensureTemplates();

        return Inertia::render('Reporting/Templates', $this->page('templates') + [
            'templates' => AcademicReportTemplate::query()->orderBy('program_band')->get()->map(fn (AcademicReportTemplate $t) => [
                'id' => $t->id, 'key' => $t->key, 'name' => $t->name, 'program_band' => $t->program_band,
                'is_default' => $t->is_default, 'config' => $t->config ?? [],
            ]),
            'bands' => AcademicReportTemplate::BANDS,
        ]);
    }

    public function saveTemplate(Request $request, AcademicReportTemplate $template): RedirectResponse
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
            'config' => ['required', 'array'],
            'config.title' => ['required', 'string', 'max:160'],
            'config.footer' => ['nullable', 'string', 'max:400'],
            'config.show_logo' => ['nullable', 'boolean'],
            'config.show_photo' => ['nullable', 'boolean'],
            'config.show_scale' => ['nullable', 'boolean'],
            'config.show_attendance' => ['nullable', 'boolean'],
            'config.show_comments' => ['nullable', 'boolean'],
            'config.show_signatures' => ['nullable', 'boolean'],
            'config.show_stamp' => ['nullable', 'boolean'],
            'config.show_position' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(function () use ($v, $template) {
            if (! empty($v['is_default'])) {
                AcademicReportTemplate::query()->where('school_id', $template->school_id)->update(['is_default' => false]);
            }
            $cfg = $template->config ?? [];
            foreach (['show_logo', 'show_photo', 'show_scale', 'show_attendance', 'show_comments', 'show_signatures', 'show_stamp', 'show_position'] as $flag) {
                $cfg[$flag] = (bool) ($v['config'][$flag] ?? false);
            }
            $cfg['title'] = $v['config']['title'];
            $cfg['footer'] = $v['config']['footer'] ?? ($cfg['footer'] ?? null);
            $template->update(['name' => $v['name'], 'is_default' => (bool) ($v['is_default'] ?? false), 'config' => $cfg]);
        }, 'ok.saved');
    }

    public function grading(AcademicReportSettings $settings, ResultsService $results): Response
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);
        $cfg = $settings->all();

        return Inertia::render('Reporting/Grading', $this->page('grading') + [
            'settings' => $cfg,
            'bands' => $results->scale()->bands->map(fn ($b) => [
                'letter' => $b->letter, 'min_score' => (float) $b->min_score, 'remark' => $b->remark,
            ]),
            'scaleUrl' => route('examinations.settings'),
        ]);
    }

    public function saveGrading(Request $request, AcademicReportSettings $settings): RedirectResponse
    {
        abort_unless(auth()->user()?->can('exams.manage'), 403);
        $v = $request->validate([
            'show_position' => ['nullable', 'boolean'],
            'show_photo' => ['nullable', 'boolean'],
            'show_dob' => ['nullable', 'boolean'],
            'term_weights' => ['nullable', 'array'],
            'term_weights.1' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'term_weights.2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'term_weights.3' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return $this->attempt(function () use ($v, $settings) {
            $weights = [];
            foreach ([1, 2, 3] as $seq) {
                if (isset($v['term_weights'][$seq]) && $v['term_weights'][$seq] !== '' && $v['term_weights'][$seq] !== null) {
                    $weights[(string) $seq] = round((float) $v['term_weights'][$seq], 2);
                }
            }
            if ($weights !== [] && abs(array_sum($weights) - 100) > 0.05) {
                throw \App\Exceptions\AcademicRuleException::because('err.rpt.weights');
            }
            $settings->save([
                'show_position' => (bool) ($v['show_position'] ?? false),
                'show_photo' => (bool) ($v['show_photo'] ?? false),
                'show_dob' => (bool) ($v['show_dob'] ?? false),
                'term_weights' => $weights,
            ]);
        }, 'ok.saved');
    }

    protected function page(string $current): array
    {
        $user = auth()->user();

        return $this->shellProps() + [
            'current' => $current,
            'tabs' => ReportingAccess::tabs($user),
            'can' => ReportingAccess::can($user),
        ];
    }
}
