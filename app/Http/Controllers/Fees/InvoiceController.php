<?php

namespace App\Http\Controllers\Fees;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\SchoolPaymentMethod;
use App\Models\Student;
use App\Models\Term;
use App\Services\Finance\FinanceAccess;
use App\Services\Finance\FinanceReports;
use App\Services\Finance\InvoiceService;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    /** Staff list with filters and invoice generation tools. */
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $classId = $request->integer('class') ?: null;

        $invoices = Invoice::query()->with(['student:id,first_name,last_name,student_number,school_class_id', 'student.schoolClass:id,name'])
            ->when($status === 'overdue', fn ($b) => $b->late())
            ->when(in_array($status, ['issued', 'partial', 'paid', 'cancelled'], true), fn ($b) => $b->where('status', $status))
            ->when($classId, fn ($b) => $b->whereHas('student', fn ($s) => $s->where('school_class_id', $classId)))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('invoice_number', 'like', "%$q%")
                ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', "%$q%")->orWhere('last_name', 'like', "%$q%")->orWhere('student_number', 'like', "%$q%"))))
            ->latest('id')->paginate(25)->withQueryString();

        $currentYear = AcademicYear::query()->where('is_current', true)->first();

        return Inertia::render('Fees/Invoices', array_merge($this->shellProps(), [
            'invoices' => $invoices->through(fn (Invoice $i) => [
                'id' => $i->id, 'invoice_number' => $i->invoice_number, 'student' => $i->student?->full_name, 'class' => $i->student?->schoolClass?->name,
                'total_amount' => (int) $i->total_amount, 'amount_paid' => (int) $i->amount_paid, 'balance' => $i->balance(),
                'status' => $i->displayStatus(), 'due_on' => $i->due_on?->toDateString(),
            ]),
            'filters' => ['status' => $status, 'q' => $q, 'class' => $classId],
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name']),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
            'terms' => Term::query()->orderBy('starts_on')->get(['id', 'academic_year_id', 'name']),
            'currentYearId' => $currentYear?->id,
            'students' => Student::query()->where('status', 'active')->orderBy('first_name')->limit(1000)->get(['id', 'first_name', 'last_name', 'student_number'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name, 'student_number' => $s->student_number]),
            'categories' => FeeCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'optionalLines' => FeeStructure::query()->where('is_optional', true)->where('is_active', true)->with('category:id,name')->get()
                ->map(fn ($s) => ['id' => $s->id, 'academic_year_id' => $s->academic_year_id, 'term_id' => $s->term_id, 'name' => $s->name ?: $s->category?->name, 'amount' => (int) $s->amount]),
            'can' => ['manage' => $request->user()->can('fees.manage'), 'approve' => $request->user()->can('fees.approve')],
        ]));
    }

    public function show(Request $request, Invoice $invoice, OpsSettings $settings): Response
    {
        $this->authorize('view', $invoice);

        $user = $request->user();
        $invoice->load(['student:id,first_name,last_name,student_number,school_class_id', 'student.schoolClass:id,name', 'items.category:id,name', 'installments', 'academicYear:id,name', 'term:id,name']);
        $staff = FinanceAccess::isStaff($user);
        $payments = $invoice->payments()->latest('id')->get();
        $pending = (int) $invoice->payments()->where('status', 'pending')->sum('amount');
        $cfg = $settings->group('finance');

        return Inertia::render('Fees/InvoiceShow', array_merge($this->shellProps(), [
            'invoice' => [
                'id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'status' => $invoice->displayStatus(), 'raw_status' => $invoice->status,
                'student' => $invoice->student?->full_name, 'student_id' => $invoice->student_id, 'student_number' => $invoice->student?->student_number,
                'class' => $invoice->student?->schoolClass?->name, 'year' => $invoice->academicYear?->name, 'term' => $invoice->term?->name,
                'subtotal' => (int) $invoice->subtotal, 'adjustments_total' => (int) $invoice->adjustments_total, 'total_amount' => (int) $invoice->total_amount,
                'amount_paid' => (int) $invoice->amount_paid, 'balance' => $invoice->balance(), 'pending' => $pending,
                'issued_at' => $invoice->issued_at?->toDateString(), 'due_on' => $invoice->due_on?->toDateString(), 'notes' => $invoice->notes,
                'cancel_reason' => $staff ? $invoice->cancel_reason : null,
            ],
            'items' => $invoice->items->map(fn ($i) => ['id' => $i->id, 'kind' => $i->kind, 'description' => $i->description, 'category' => $i->category?->name, 'amount' => (int) $i->amount]),
            'installments' => $invoice->installments->map(fn ($n) => ['id' => $n->id, 'number' => $n->number, 'due_on' => $n->due_on?->toDateString(), 'amount' => (int) $n->amount, 'amount_paid' => (int) $n->amount_paid]),
            'payments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id, 'receipt_number' => $p->receipt_number, 'amount' => (int) $p->amount, 'method' => $p->method, 'reference' => $staff ? $p->external_reference : null,
                'status' => $p->status, 'paid_at' => $p->paid_at?->toDateString(), 'decision_note' => $p->decision_note, 'void_reason' => $staff ? $p->void_reason : null,
                'has_evidence' => $p->evidence_path !== null, 'is_reconciled' => (bool) $p->is_reconciled,
            ]),
            'methods' => SchoolPaymentMethod::query()->where('is_active', true)->where('method', '!=', 'cash')->orderBy('sort_order')->orderBy('label')
                ->get(['id', 'method', 'label', 'instructions', 'account_name', 'account_number', 'bank_name']),
            'rules' => ['installments' => (bool) $cfg['allow_installments'], 'installments_max' => (int) $cfg['installments_max'], 'submissions' => (bool) $cfg['parent_submissions']],
            'can' => [
                'record' => $user->can('payments.record') && $staff, 'verify' => $user->can('payments.verify') && $staff,
                'approve' => $user->can('fees.approve') && $staff, 'manage' => $user->can('fees.manage') && $staff, 'staff' => $staff,
            ],
        ]));
    }

    /** Generate fee-schedule invoices for one learner or a whole class. */
    public function generate(Request $request, InvoiceService $service): RedirectResponse
    {
        $v = $request->validate([
            'academic_year_id' => ['required', $this->inSchool('academic_years')],
            'term_id' => ['nullable', $this->inSchool('terms')],
            'student_id' => ['nullable', $this->inSchool('students')],
            'school_class_id' => ['nullable', $this->inSchool('school_classes')],
            'optional_ids' => ['nullable', 'array'],
            'optional_ids.*' => ['integer', $this->inSchool('fee_structures')],
        ]);

        return $this->attempt(function () use ($v, $request, $service) {
            if (empty($v['student_id']) === empty($v['school_class_id'])) {
                throw AcademicRuleException::because('err.fin.target');
            }
            $year = AcademicYear::query()->findOrFail($v['academic_year_id']);
            $term = ! empty($v['term_id']) ? Term::query()->findOrFail($v['term_id']) : null;

            if (! empty($v['student_id'])) {
                $invoice = $service->generate(Student::query()->findOrFail($v['student_id']), $year, $term, $request->user(), $v['optional_ids'] ?? []);
                if (! $invoice) {
                    throw AcademicRuleException::because('err.fin.nothingToBill');
                }

                return redirect()->route('fees.invoices.show', $invoice)->with('success', 'ok.fin.invoiceCreated');
            }

            $r = $service->generateForClass(SchoolClass::query()->findOrFail($v['school_class_id']), $year, $term, $request->user());

            return back()->with('success', 'ok.fin.generated')->with('count', $r['created'])->with('failedCount', $r['skipped'] + $r['empty']);
        });
    }

    public function store(Request $request, InvoiceService $service): RedirectResponse
    {
        $v = $request->validate([
            'student_id' => ['required', $this->inSchool('students')],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.description' => ['required', 'string', 'max:190'],
            'lines.*.amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'lines.*.fee_category_id' => ['nullable', $this->inSchool('fee_categories')],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->attempt(function () use ($v, $request, $service) {
            $invoice = $service->manual(Student::query()->findOrFail($v['student_id']), $v['lines'], $v['due_on'] ?? null, $v['notes'] ?? null, $request->user());

            return redirect()->route('fees.invoices.show', $invoice)->with('success', 'ok.fin.invoiceCreated');
        });
    }

    public function cancel(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $v = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $service->cancel($invoice, $v['reason'], $request->user()), 'ok.fin.invoiceCancelled');
    }

    public function installments(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $v = $request->validate(['count' => ['required', 'integer', 'min:2', 'max:12'], 'first_due_on' => ['required', 'date']]);

        return $this->attempt(fn () => $service->setInstallments($invoice, (int) $v['count'], $v['first_due_on'], $request->user()), 'ok.fin.installmentsSet');
    }

    public function clearInstallments(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($invoice, $service) {
            if ((int) $invoice->amount_paid > 0) {
                throw AcademicRuleException::because('err.fin.installmentLocked');
            }
            $service->clearInstallments($invoice);
        }, 'ok.fin.installmentsCleared');
    }

    /** A learner's full statement (staff; parents and learners reach their own through the fees page). */
    public function statement(Request $request, Student $student, FinanceReports $reports): Response
    {
        abort_unless(FinanceAccess::canSee($request->user(), $student->id), 403);

        return Inertia::render('Fees/Statement', array_merge($this->shellProps(), ['statement' => $reports->statement($student)]));
    }
}
