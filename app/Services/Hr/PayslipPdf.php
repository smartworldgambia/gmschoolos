<?php

namespace App\Services\Hr;

use App\Models\PayrollItem;
use App\Models\School;
use App\Support\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;

/** A payslip as a PDF (English layout). Bank details are shown masked: only the last four characters. */
class PayslipPdf
{
    public function render(PayrollItem $item): string
    {
        $item->loadMissing(['run', 'staff.position', 'staff.department']);
        $school = School::query()->find($item->school_id) ?? app(TenantContext::class)->school();
        $account = (string) ($item->staff?->bank_account ?? '');

        $html = view('hr.payslip', [
            'item' => $item,
            'run' => $item->run,
            'staff' => $item->staff,
            'school' => $school,
            'maskedAccount' => $account === '' ? null : str_repeat('•', max(0, mb_strlen($account) - 4)).mb_substr($account, -4),
            'watermark' => $item->run?->status === 'draft' ? 'DRAFT - NOT APPROVED' : null,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [storage_path(), resource_path()]);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
