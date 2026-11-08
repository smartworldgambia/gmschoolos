<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeAdjustment;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\Term;
use App\Services\Finance\AdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Discounts, scholarships and waivers. Requesting needs fees.manage; deciding needs fees.approve (and a second person). */
class FeeAdjustmentController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');

        return Inertia::render('Fees/Adjustments', array_merge($this->shellProps(), [
            'adjustments' => FeeAdjustment::query()->with(['student:id,first_name,last_name,student_number', 'category:id,name', 'requester:id,name', 'decider:id,name'])
                ->when(in_array($status, ['pending', 'approved', 'rejected', 'revoked'], true), fn ($q) => $q->where('status', $status))
                ->latest('id')->limit(200)->get()
                ->map(fn (FeeAdjustment $a) => [
                    'id' => $a->id, 'student' => $a->student?->full_name, 'student_number' => $a->student?->student_number, 'kind' => $a->kind,
                    'basis' => $a->basis, 'value' => (int) $a->value, 'category' => $a->category?->name, 'reason' => $a->reason,
                    'status' => $a->status, 'requester' => $a->requester?->name, 'decider' => $a->decider?->name,
                    'decision_note' => $a->decision_note, 'created_at' => $a->created_at?->toDateString(),
                    'own' => (int) $a->requested_by === (int) $request->user()->id,
                ]),
            'filter' => $status,
            'students' => Student::query()->where('status', 'active')->orderBy('first_name')->limit(1000)->get(['id', 'first_name', 'last_name', 'student_number'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name, 'student_number' => $s->student_number]),
            'categories' => FeeCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'terms' => Term::query()->orderBy('starts_on')->get(['id', 'academic_year_id', 'name']),
            'can' => ['manage' => $request->user()->can('fees.manage'), 'approve' => $request->user()->can('fees.approve')],
        ]));
    }

    public function store(Request $request, AdjustmentService $service): RedirectResponse
    {
        $v = $request->validate([
            'student_id' => ['required', $this->inSchool('students')],
            'kind' => ['required', 'in:discount,scholarship,waiver'],
            'basis' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', 'max:100000000'],
            'fee_category_id' => ['nullable', $this->inSchool('fee_categories')],
            'academic_year_id' => ['nullable', $this->inSchool('academic_years')],
            'term_id' => ['nullable', $this->inSchool('terms')],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->attempt(fn () => $service->request(
            $request->user(), (int) $v['student_id'], $v['kind'], $v['basis'], (int) $v['value'],
            $v['fee_category_id'] ?? null, $v['academic_year_id'] ?? null, $v['term_id'] ?? null, $v['reason'],
        ), 'ok.fin.adjustmentRequested');
    }

    public function approve(Request $request, FeeAdjustment $adjustment, AdjustmentService $service): RedirectResponse
    {
        $v = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->approve($adjustment, $request->user(), $v['note'] ?? null), 'ok.fin.adjustmentApproved');
    }

    public function reject(Request $request, FeeAdjustment $adjustment, AdjustmentService $service): RedirectResponse
    {
        $v = $request->validate(['note' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->reject($adjustment, $request->user(), $v['note']), 'ok.fin.adjustmentRejected');
    }

    public function revoke(Request $request, FeeAdjustment $adjustment, AdjustmentService $service): RedirectResponse
    {
        $v = $request->validate(['note' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->revoke($adjustment, $request->user(), $v['note']), 'ok.fin.adjustmentRevoked');
    }
}
