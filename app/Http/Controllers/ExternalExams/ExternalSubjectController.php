<?php

namespace App\Http\Controllers\ExternalExams;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ExternalCandidateSubject;
use App\Models\ExternalExamSubject;
use App\Services\ExternalExams\ExternalExamService;
use App\Services\Records\RecordsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExternalSubjectController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected ExternalExamService $service, protected RecordsSettings $settings) {}

    public function index(): Response
    {
        return Inertia::render('ExternalExams/Subjects', $this->shellProps() + [
            'subjects' => ExternalExamSubject::query()->orderBy('exam_type')->orderBy('name')->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'exam_type' => $s->exam_type, 'code' => $s->code, 'name' => $s->name, 'is_active' => $s->is_active,
                    'in_use' => ExternalCandidateSubject::query()->where('subject_id', $s->id)->exists(),
                ]),
            'grades' => ['gabece' => $this->service->gradeList('gabece'), 'wassce' => $this->service->gradeList('wassce')],
            'can' => ['manage' => auth()->user()->can('external_exams.manage'), 'verify' => auth()->user()->can('external_exams.verify')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'exam_type' => ['required', 'in:gabece,wassce'], 'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:150'], 'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(fn () => $this->service->saveSubject($v), 'ok.saved');
    }

    public function update(Request $request, ExternalExamSubject $subject): RedirectResponse
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'name' => ['required', 'string', 'max:150'], 'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->attempt(fn () => $this->service->saveSubject($v + ['exam_type' => $subject->exam_type], $subject), 'ok.saved');
    }

    public function destroy(ExternalExamSubject $subject): RedirectResponse
    {
        if (ExternalCandidateSubject::query()->where('subject_id', $subject->id)->exists()) {
            return back()->with('error', 'err.ext.subjectInUse');
        }
        $subject->delete();

        return back()->with('success', 'ok.saved');
    }

    /** Per-school override of the accepted grade symbols (the shipped GABECE list is only a starting point). */
    public function saveGrades(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'exam_type' => ['required', 'in:gabece,wassce'],
            'grades' => ['required', 'string', 'max:300'],
        ]);
        $list = array_values(array_unique(array_filter(array_map(fn ($g) => strtoupper(trim($g)), preg_split('/[,\s]+/', $v['grades'])))));
        if ($list === [] || count($list) > 20 || collect($list)->contains(fn ($g) => ! preg_match('/^[A-Z0-9+\-]{1,6}$/', $g))) {
            return back()->with('error', 'err.ext.gradesInvalid');
        }
        $current = (array) ($this->settings->all()['grades'] ?? []);
        $current[$v['exam_type']] = $list;
        $this->settings->save(['grades' => $current]);

        return back()->with('success', 'ok.saved');
    }
}
