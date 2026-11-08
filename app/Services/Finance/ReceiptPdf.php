<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\School;
use App\Services\Ops\OpsSettings;
use App\Support\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Fee receipt as a PDF (English layout; localised document templates belong to the reporting phase). */
class ReceiptPdf
{
    public function __construct(private readonly OpsSettings $settings) {}

    public function render(Payment $payment): string
    {
        $payment->loadMissing(['invoice', 'student', 'recorder']);
        $school = School::query()->find($payment->school_id) ?? app(TenantContext::class)->school();

        $html = view('finance.receipt', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'student' => $payment->student,
            'school' => $school,
            'footer' => (string) $this->settings->get('finance', 'receipt_footer', '', $school),
            'watermark' => match ($payment->status) {
                'pending' => 'NOT YET CONFIRMED',
                'rejected' => 'REJECTED',
                'voided' => 'VOID',
                default => null,
            },
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [storage_path(), resource_path()]);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A5', 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
