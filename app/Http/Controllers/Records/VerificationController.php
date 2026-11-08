<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Models\AcademicReport;
use App\Models\RecordsDocument;
use App\Models\School;
use App\Services\Records\DocumentPdf;
use App\Services\Reporting\AcademicReportPdf;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public verification page behind the QR code. MINIMAL DISCLOSURE by design: it confirms that the document exists,
 * its type, reference, issuing school, issue date and whether it is still valid - plus the holder's initials so a
 * person holding the paper can see it is not obviously for someone else. No full name, student number, marks,
 * date of birth, photo, guardian or contact details are ever returned. Unknown / draft tokens look identical.
 */
class VerificationController extends Controller
{
    public function show(string $token): Response
    {
        $doc = RecordsDocument::withoutGlobalScopes()
            ->where('verification_token', $token)->whereIn('status', ['issued', 'reprinted', 'revoked'])->first();

        $base = ['disclaimer' => DocumentPdf::DISCLAIMER];

        if ($doc) {
            $school = School::query()->find($doc->school_id);
            $name = (string) (($doc->payload['student']['full_name'] ?? ''));

            return Inertia::render('Verify/Show', $base + ['result' => [
                'state' => $doc->status === 'revoked' ? 'revoked' : 'valid',
                'type' => $doc->type, 'reference' => $doc->reference, 'school' => $school?->name,
                'issue_date' => $doc->issue_date?->toDateString(),
                'initials' => $this->initials($name),
                'revoked_on' => $doc->status === 'revoked' ? $doc->revoked_at?->toDateString() : null,
            ]]);
        }

        $report = AcademicReport::withoutGlobalScopes()
            ->where('verification_token', $token)->whereIn('status', ['published', 'superseded'])->first();

        if (! $report) {
            return Inertia::render('Verify/Show', $base + ['result' => ['state' => 'not_found']]);
        }

        $school = School::query()->find($report->school_id);
        $name = (string) (($report->payload['student']['full_name'] ?? ''));

        return Inertia::render('Verify/Show', ['disclaimer' => AcademicReportPdf::DISCLAIMER] + ['result' => [
            'state' => $report->status === 'superseded' ? 'superseded' : 'valid',
            'type' => $report->type, 'reference' => $report->reference, 'school' => $school?->name,
            'issue_date' => $report->issue_date?->toDateString(),
            'initials' => $this->initials($name),
            'revoked_on' => null,
        ]]);
    }

    protected function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)).'.', $parts));
    }
}
