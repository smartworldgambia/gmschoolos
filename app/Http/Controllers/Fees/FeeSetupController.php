<?php

namespace App\Http\Controllers\Fees;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\SchoolPaymentMethod;
use App\Models\Term;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Fee categories, the fee schedule (structures), the school's own collection methods and finance rules. fees.manage only. */
class FeeSetupController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request, OpsSettings $settings): Response
    {
        $currentYear = AcademicYear::query()->where('is_current', true)->first();
        $yearId = (int) ($request->integer('year') ?: $currentYear?->id);

        return Inertia::render('Fees/Setup', array_merge($this->shellProps(), [
            'categories' => FeeCategory::query()->withCount('structures')->orderBy('name')->get(),
            'structures' => FeeStructure::query()->with(['category:id,name', 'term:id,name', 'schoolClass:id,name'])
                ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->orderBy('term_id')->orderBy('school_class_id')->orderBy('id')->get()
                ->map(fn (FeeStructure $s) => [
                    'id' => $s->id, 'name' => $s->name, 'category_id' => $s->fee_category_id, 'category' => $s->category?->name,
                    'term_id' => $s->term_id, 'term' => $s->term?->name, 'school_class_id' => $s->school_class_id, 'class' => $s->schoolClass?->name,
                    'amount' => (int) $s->amount, 'is_optional' => (bool) $s->is_optional, 'is_active' => (bool) $s->is_active,
                ]),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
            'terms' => Term::query()->orderBy('starts_on')->get(['id', 'academic_year_id', 'name']),
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name']),
            'yearId' => $yearId,
            'methods' => SchoolPaymentMethod::query()->orderBy('sort_order')->orderBy('label')->get(),
            'settings' => $settings->group('finance'),
        ]));
    }

    // ------------------------------------------------------------------ categories

    public function storeCategory(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:250'],
        ]);

        return $this->attempt(function () use ($v) {
            if (FeeCategory::query()->where('name', $v['name'])->exists()) {
                throw AcademicRuleException::because('err.fin.categoryExists');
            }
            $c = FeeCategory::query()->create($v + ['is_active' => true]);
            Ops::audit('fin.category.created', 'Fee category created', ['category_id' => $c->id]);
        }, 'ok.fin.categorySaved');
    }

    public function updateCategory(Request $request, FeeCategory $category): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('fee_categories', 'name')->where('school_id', $this->schoolId())->ignore($category->id)],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:250'],
            'is_active' => ['required', 'boolean'],
        ]);

        return $this->attempt(function () use ($category, $v) {
            $category->update($v);
            Ops::audit('fin.category.updated', 'Fee category updated', ['category_id' => $category->id, 'active' => $v['is_active']]);
        }, 'ok.fin.categorySaved');
    }

    // ------------------------------------------------------------------ structures

    public function storeStructure(Request $request): RedirectResponse
    {
        $v = $this->structureRules($request);

        return $this->attempt(function () use ($v) {
            $this->assertTermInYear($v);
            $s = FeeStructure::query()->create($v + ['is_active' => true]);
            Ops::audit('fin.structure.created', 'Fee structure line created', ['structure_id' => $s->id, 'amount' => $s->amount]);
        }, 'ok.fin.structureSaved');
    }

    public function updateStructure(Request $request, FeeStructure $structure): RedirectResponse
    {
        $v = $this->structureRules($request) + $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->attempt(function () use ($structure, $v) {
            $this->assertTermInYear($v);
            $structure->update($v);
            Ops::audit('fin.structure.updated', 'Fee structure line updated', ['structure_id' => $structure->id, 'amount' => $structure->amount]);
        }, 'ok.fin.structureSaved');
    }

    /** A line already billed on an invoice is switched off, not deleted, so history stays explainable. */
    public function destroyStructure(FeeStructure $structure): RedirectResponse
    {
        return $this->attempt(function () use ($structure) {
            $used = \App\Models\InvoiceItem::query()->where('fee_structure_id', $structure->id)->exists();
            if ($used) {
                $structure->update(['is_active' => false]);
            } else {
                $structure->delete();
            }
            Ops::audit('fin.structure.removed', 'Fee structure line removed', ['structure_id' => $structure->id, 'archived' => $used]);
        }, 'ok.fin.structureRemoved');
    }

    private function structureRules(Request $request): array
    {
        return $request->validate([
            'fee_category_id' => ['required', $this->inSchool('fee_categories')],
            'academic_year_id' => ['required', $this->inSchool('academic_years')],
            'term_id' => ['nullable', $this->inSchool('terms')],
            'school_class_id' => ['nullable', $this->inSchool('school_classes')],
            'name' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'is_optional' => ['required', 'boolean'],
        ]);
    }

    private function assertTermInYear(array $v): void
    {
        if (! empty($v['term_id']) && ! Term::query()->where('id', $v['term_id'])->where('academic_year_id', $v['academic_year_id'])->exists()) {
            throw AcademicRuleException::because('err.fin.termYear');
        }
    }

    // ------------------------------------------------------------------ the school's own collection methods

    public function storeMethod(Request $request): RedirectResponse
    {
        $v = $this->methodRules($request);

        return $this->attempt(function () use ($v) {
            if (SchoolPaymentMethod::query()->where('label', $v['label'])->exists()) {
                throw AcademicRuleException::because('err.fin.methodExists');
            }
            $m = SchoolPaymentMethod::query()->create($v + ['is_active' => true]);
            Ops::audit('fin.method.created', 'Collection method created', ['method_id' => $m->id, 'method' => $m->method]);
        }, 'ok.fin.methodSaved');
    }

    public function updateMethod(Request $request, SchoolPaymentMethod $method): RedirectResponse
    {
        $v = $this->methodRules($request) + $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->attempt(function () use ($method, $v) {
            if (SchoolPaymentMethod::query()->where('label', $v['label'])->where('id', '!=', $method->id)->exists()) {
                throw AcademicRuleException::because('err.fin.methodExists');
            }
            $method->update($v);
            Ops::audit('fin.method.updated', 'Collection method updated', ['method_id' => $method->id, 'active' => $v['is_active']]);
        }, 'ok.fin.methodSaved');
    }

    private function methodRules(Request $request): array
    {
        $v = $request->validate([
            'method' => ['required', 'in:cash,wave,bank_transfer,other'],
            'label' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:80'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $v['sort_order'] = (int) ($v['sort_order'] ?? 0);

        return $v;
    }

    // ------------------------------------------------------------------ rules

    public function saveSettings(Request $request, OpsSettings $settings): RedirectResponse
    {
        $request->validate([
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'installments_max' => ['required', 'integer', 'min:2', 'max:12'],
            'reminder_days' => ['required', 'integer', 'min:1', 'max:90'],
            'reminder_max' => ['required', 'integer', 'min:0', 'max:12'],
            'receipt_footer' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->attempt(function () use ($request, $settings) {
            $settings->save('finance', $request->only([
                'invoice_prefix', 'due_days', 'allow_installments', 'installments_max', 'parent_submissions',
                'self_approval', 'reminder_days', 'reminder_max', 'receipt_footer',
            ]));
            Ops::audit('fin.settings.saved', 'Finance rules saved', ['self_approval' => $request->boolean('self_approval')]);
        }, 'ok.fin.settingsSaved');
    }
}
