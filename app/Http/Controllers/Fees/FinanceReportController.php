<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Services\Finance\FinanceReports;
use App\Services\Ops\Ops;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Financial reports for staff holding reports.view and fees.view. CSV exports additionally need reports.export. */
class FinanceReportController extends Controller
{
    use SharesSchoolShell;

    public function index(Request $request, FinanceReports $reports): Response
    {
        abort_unless($request->user()->can('fees.view'), 403);
        $period = $reports->period($request->query('from'), $request->query('to'));
        $classId = $request->integer('class') ?: null;

        return Inertia::render('Fees/Reports', array_merge($this->shellProps(), [
            'period' => $period,
            'classId' => $classId,
            'classes' => SchoolClass::query()->orderBy('name')->get(['id', 'name']),
            'summary' => $reports->summary($period['from'], $period['to']),
            'byMethod' => $reports->byMethod($period['from'], $period['to']),
            'byCategory' => $reports->byCategory($period['from'], $period['to']),
            'outstanding' => $reports->outstanding($classId, 100),
            'canExport' => $request->user()->can('reports.export'),
        ]));
    }

    public function export(Request $request, FinanceReports $reports)
    {
        abort_unless($request->user()->can('reports.export') && $request->user()->can('fees.view'), 403);
        $type = (string) $request->query('type', 'outstanding');
        abort_unless(in_array($type, ['outstanding', 'payments'], true), 404);
        $period = $reports->period($request->query('from'), $request->query('to'));

        Ops::audit('fin.report.exported', 'Finance report exported: '.$type, ['type' => $type, 'from' => $period['from'], 'to' => $period['to']]);

        if ($type === 'outstanding') {
            $rows = $reports->outstanding($request->integer('class') ?: null, 5000)['rows'];

            return Ops::csv('outstanding-balances-'.now()->format('Ymd').'.csv',
                ['Invoice', 'Student', 'Student number', 'Class', 'Total', 'Paid', 'Balance', 'Due on', 'Days overdue'],
                collect($rows)->map(fn ($r) => [$r['invoice_number'], $r['student'], $r['student_number'], $r['class'], $r['total'], $r['paid'], $r['balance'], $r['due_on'], $r['days_overdue']]));
        }

        $rows = Payment::query()->confirmed()->with(['student:id,first_name,last_name,student_number', 'invoice:id,invoice_number'])
            ->whereBetween('paid_at', [$period['from'].' 00:00:00', $period['to'].' 23:59:59'])->orderBy('paid_at')->cursor();

        return Ops::csv('payments-'.$period['from'].'-'.$period['to'].'.csv',
            ['Receipt', 'Date', 'Student', 'Student number', 'Invoice', 'Method', 'Reference', 'Amount', 'Reconciled'],
            (function () use ($rows) {
                foreach ($rows as $p) {
                    yield [$p->receipt_number, $p->paid_at?->toDateString(), $p->student?->full_name, $p->student?->student_number, $p->invoice?->invoice_number, $p->method, $p->external_reference, $p->amount, $p->is_reconciled ? 'yes' : 'no'];
                }
            })());
    }
}
