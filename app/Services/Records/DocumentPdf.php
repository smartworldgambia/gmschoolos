<?php

namespace App\Services\Records;

use App\Models\RecordsDocument;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an issued/approved Records Office document to PDF from its stored snapshot, with a QR code that
 * points at the public verification page (which discloses almost nothing - see RecordsVerificationController).
 */
class DocumentPdf
{
    public const DISCLAIMER = 'This is a school-issued document. It is not an official WAEC result or certificate.';

    public function __construct(protected DocumentService $documents, protected RecordsSettings $settings) {}

    public function render(RecordsDocument $doc): string
    {
        $doc->loadMissing('events');
        $settings = $this->settings->all($doc->school_id ? \App\Models\School::query()->find($doc->school_id) : null);
        $payload = (array) $doc->payload;
        $school = (array) ($payload['school'] ?? []);

        $html = view('records.document', [
            'doc' => $doc,
            'payload' => $payload,
            'school' => $school,
            'logo' => $this->image($school['logo_path'] ?? null, 'public'),
            'photo' => $doc->type === 'admission_letter' ? null : $this->image($payload['student']['photo_path'] ?? null, config('schoolos.students.document_disk', 'local')),
            'stamp' => $this->image($settings['stamp_path'], config('schoolos.records.document_disk', 'local')),
            'signatories' => $settings['signatories'],
            'qr' => $this->qr($this->documents->verificationUrl($doc)),
            'verifyUrl' => $this->documents->verificationUrl($doc),
            'disclaimer' => self::DISCLAIMER,
            'watermark' => match ($doc->status) {
                'draft', 'submitted', 'approved' => 'DRAFT - NOT ISSUED',
                'revoked' => 'REVOKED',
                default => null,
            },
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);   // never fetch remote resources while rendering
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

    /** Embed a stored image as a data URI (png/jpeg only); null when missing or not an image. */
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
