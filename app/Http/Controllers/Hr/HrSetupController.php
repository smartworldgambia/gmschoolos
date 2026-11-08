<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobPosition;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Job positions and the school's HR / payroll rules. hr.manage only. */
class HrSetupController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(OpsSettings $settings): Response
    {
        return Inertia::render('Hr/Setup', array_merge($this->shellProps(), [
            'positions' => JobPosition::query()->with('department:id,name')->orderBy('title')->get()->map(fn (JobPosition $p) => [
                'id' => $p->id, 'title' => $p->title, 'description' => $p->description, 'department_id' => $p->department_id, 'department' => $p->department?->name, 'is_active' => (bool) $p->is_active,
            ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'settings' => ['hr' => $settings->group('hr'), 'payroll' => $settings->group('payroll')],
            'canPayroll' => request()->user()->can('payroll.manage'),
        ]));
    }

    public function storePosition(Request $request): RedirectResponse
    {
        $v = $this->positionRules($request);

        return $this->attempt(function () use ($v) {
            if (JobPosition::query()->where('title', $v['title'])->exists()) {
                throw AcademicRuleException::because('err.hr.positionExists');
            }
            $p = JobPosition::query()->create($v + ['is_active' => true]);
            Ops::audit('hr.position.created', 'Job position created', ['position_id' => $p->id]);
        }, 'ok.hr.positionSaved');
    }

    public function updatePosition(Request $request, JobPosition $position): RedirectResponse
    {
        $v = $this->positionRules($request) + $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->attempt(function () use ($v, $position) {
            if (JobPosition::query()->where('title', $v['title'])->where('id', '!=', $position->id)->exists()) {
                throw AcademicRuleException::because('err.hr.positionExists');
            }
            $position->update($v);
            Ops::audit('hr.position.updated', 'Job position updated', ['position_id' => $position->id]);
        }, 'ok.hr.positionSaved');
    }

    public function saveSettings(Request $request, OpsSettings $settings): RedirectResponse
    {
        $v = $request->validate([
            'leave_annual_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'leave_sick_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'leave_self_approval' => ['required', 'boolean'],
            'payroll_self_approval' => ['required', 'boolean'],
        ]);

        return $this->attempt(function () use ($v, $settings, $request) {
            $settings->save('hr', ['leave_annual_days' => $v['leave_annual_days'] ?? null, 'leave_sick_days' => $v['leave_sick_days'] ?? null, 'self_approval' => $v['leave_self_approval']]);
            // payroll approval rules belong to payroll people; HR officers without payroll rights cannot change them
            if ($request->user()->can('payroll.manage')) {
                $settings->save('payroll', ['self_approval' => $v['payroll_self_approval']]);
            }
            Ops::audit('hr.settings.saved', 'HR rules saved', ['leave_self_approval' => (bool) $v['leave_self_approval']]);
        }, 'ok.hr.settingsSaved');
    }

    private function positionRules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'department_id' => ['nullable', $this->inSchool('departments')],
        ]);
    }
}
