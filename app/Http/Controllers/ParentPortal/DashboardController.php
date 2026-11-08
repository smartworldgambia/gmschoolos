<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use SharesSchoolShell;

    public function __invoke(\App\Services\Communications\AnnouncementService $announcements, \App\Services\Messaging\MessagingService $messages): Response
    {
        abort_unless(auth()->user()?->can('portal.parent'), 403);

        $guardian = auth()->user()->guardianProfile;
        abort_unless($guardian, 403, 'No guardian profile linked to this account.');

        $children = $guardian->students()
            ->with('schoolClass')
            ->get()
            ->map(function (Student $student) {
                $attendanceTotal = AttendanceRecord::query()->where('student_id', $student->id)->count();
                $present = AttendanceRecord::query()
                    ->where('student_id', $student->id)
                    ->where('status', 'present')
                    ->count();

                $grades = Grade::query()
                    ->with('subject')
                    ->where('student_id', $student->id)
                    ->where(function ($q) {
                        $q->where('is_published', true)
                            ->orWhereNull('examination_id');
                    })
                    ->get()
                    ->map(fn (Grade $g) => [
                        'subject' => $g->subject?->name,
                        'total' => $g->total,
                        'letter_grade' => $g->letter_grade,
                    ]);

                $invoices = Invoice::query()
                    ->where('student_id', $student->id)
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (Invoice $invoice) => [
                        'invoice_number' => $invoice->invoice_number,
                        'total_amount' => $invoice->total_amount,
                        'amount_paid' => $invoice->amount_paid,
                        'balance' => $invoice->balance(),
                        'status' => $invoice->status,
                        'total_label' => Money::format($invoice->total_amount),
                        'balance_label' => Money::format($invoice->balance()),
                    ]);

                $outstanding = (int) Invoice::query()
                    ->where('student_id', $student->id)
                    ->get()
                    ->sum(fn (Invoice $i) => $i->balance());

                return [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'student_number' => $student->student_number,
                    'class' => $student->schoolClass?->name,
                    'attendance_rate' => $attendanceTotal > 0 ? round(($present / $attendanceTotal) * 100, 1) : null,
                    'grades' => $grades,
                    'invoices' => $invoices,
                    'outstanding_label' => Money::format($outstanding),
                    'profile_url' => route('students.show', $student),
                ];
            });

        $recentPayments = Payment::query()
            ->whereIn('student_id', $guardian->students()->pluck('students.id'))
            ->latest('paid_at')
            ->limit(10)
            ->with('student')
            ->get()
            ->map(fn (Payment $payment) => [
                'student_name' => $payment->student?->full_name,
                'amount' => $payment->amount,
                'amount_label' => Money::format($payment->amount),
                'method' => $payment->method,
                'paid_at' => $payment->paid_at?->toDateString(),
            ]);

        return Inertia::render('Parent/Dashboard', array_merge($this->shellProps(), [
            'children' => $children,
            'recentPayments' => $recentPayments,
            'unreadMessages' => $messages->unreadCount(auth()->user()),
            'announcements' => $announcements->feed(auth()->user(), 3)->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => mb_substr($a->body, 0, 160), 'unread' => $a->my_read_at === null,
            ])->values(),
            'guardian' => [
                'full_name' => $guardian->full_name,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
            ],
        ]));
    }
}
