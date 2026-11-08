<?php

namespace App\Services\Reporting;

use App\Models\AcademicReport;
use App\Services\Records\RecordsSettings;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class AcademicReportPdf
{
    public const DISCLAIMER = 'This is a school-issued academic report. It is not an official WAEC result or certificate.';

    public function __construct(
        protected AcademicReportService $reports,
        protected RecordsSettings $records,
    ) {}

    public function render(AcademicReport $report): string
    {
        $report->loadMissing('events');
        $settings = $this->records->all($report->school_id ? \App\Models\School::query()->find($report->school_id) : null);
        $payload = (array) $report->payload;
        $school = (array) ($payload['school'] ?? []);

        $html = view('reporting.document', [
            'report' => $report,
            'payload' => $payload,
            'school' => $school,
            'logo' => $this->image($school['logo_path'] ?? null, 'public'),
            'photo' => $this->image($payload['student']['photo_path'] ?? null, config('schoolos.students.document_disk', 'local')),
            'stamp' => $this->image($settings['stamp_path'], config('schoolos.records.document_disk', 'local')),
            'signatories' => $settings['signatories'],
            'qr' => $this->qr($this->reports->verificationUrl($report)),
            'verifyUrl' => $this->reports->verificationUrl($report),
            'disclaimer' => self::DISCLAIMER,
            'watermark' => match ($report->status) {
                'published' => null,
                'superseded' => 'SUPERSEDED',
                default => 'DRAFT - NOT PUBLISHED',
            },
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

    public function qr(string $url): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class, 'scale' => 5, 'outputBase64' => true, 'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($url);
    }

    protected function image(?string $path, string $disk): ?string
    {
        if (! $path) {
            return null;
        }
        try {
            $store = Storage::disk($disk);
            if (! $store->exists($path)) {
                return null;
            }
            $bytes = $store->get($path);
            $info = @getimagesizefromstring($bytes);
            if (! $info || ! in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
                return null;
            }

            return 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
        } catch (\Throwable) {
            return null;
        }
    }
}
