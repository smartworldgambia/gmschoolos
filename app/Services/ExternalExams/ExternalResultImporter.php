<?php

namespace App\Services\ExternalExams;

use App\Exceptions\AcademicRuleException;
use App\Models\ExternalCandidate;
use App\Models\ExternalExamSession;
use App\Models\ExternalExamSubject;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CSV result import. Each row must identify the candidate by `official_index_number`, `internal_reference` or
 * `student_number` (checked in that order), plus `subject_code` and `grade`. A row is imported only when it matches
 * exactly one candidate of THIS session, a registered subject and an allowed grade; everything else is reported with
 * its line number. Nothing is guessed, nothing is skipped silently, and imported rows are NOT verified.
 */
class ExternalResultImporter
{
    public const HEADERS = ['official_index_number', 'internal_reference', 'student_number', 'subject_code', 'grade'];

    public function __construct(protected ExternalExamService $service) {}

    public function import(User $user, ExternalExamSession $session, UploadedFile $file): ImportBatch
    {
        $maxRows = (int) config('schoolos.external_exams.import_max_rows');
        $path = $file->storeAs('external-imports/'.$session->school_id, now()->format('YmdHis').'-'.bin2hex(random_bytes(6)).'.csv', config('schoolos.students.document_disk', 'local'));

        $batch = ImportBatch::query()->create([
            'uploaded_by' => $user->id, 'type' => 'external_results', 'original_filename' => mb_substr($file->getClientOriginalName(), 0, 190),
            'storage_path' => $path, 'status' => 'processing', 'started_at' => now(),
        ]);

        $handle = fopen($file->getRealPath(), 'r');
        $header = $handle ? fgetcsv($handle) : false;
        if (! $header) {
            $this->finish($batch, 0, 0, 0, [], 'failed');
            throw AcademicRuleException::because('err.ext.importEmpty');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]); // BOM from spreadsheet exports
        $cols = array_flip(array_map(fn ($h) => strtolower(trim((string) $h)), $header));
        if (! isset($cols['subject_code'], $cols['grade'])
            || ! (isset($cols['official_index_number']) || isset($cols['internal_reference']) || isset($cols['student_number']))) {
            fclose($handle);
            $this->finish($batch, 0, 0, 0, [], 'failed');
            throw AcademicRuleException::because('err.ext.importHeaders');
        }

        $candidates = ExternalCandidate::query()->with('student')->where('session_id', $session->id)->where('status', '!=', 'withdrawn')->get();
        $byIndex = $candidates->filter(fn ($c) => $c->official_index_number !== null)->groupBy(fn ($c) => $this->service->normalise($c->official_index_number));
        $byRef = $candidates->keyBy(fn ($c) => strtoupper($c->internal_reference));
        $byNumber = $candidates->groupBy(fn ($c) => strtoupper((string) $c->student->student_number));
        $subjects = ExternalExamSubject::query()->where('exam_type', $session->type)->where('is_active', true)->get()->keyBy(fn ($s) => strtoupper($s->code));

        $total = 0;
        $ok = 0;
        $errors = [];
        $fail = function (int $line, string $key) use (&$errors) {
            if (count($errors) < 200) {
                $errors[] = ['line' => $line, 'error' => $key];
            }
        };

        DB::transaction(function () use ($handle, $cols, $maxRows, $user, $batch, $byIndex, $byRef, $byNumber, $subjects, &$total, &$ok, $fail) {
            $line = 1;
            $get = fn (array $row, string $k) => isset($cols[$k]) ? (string) ($row[$cols[$k]] ?? '') : '';
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                if (++$total > $maxRows) {
                    $fail($line, 'err.ext.importTooManyRows');
                    break;
                }

                $candidate = null;
                $index = $get($row, 'official_index_number');
                $ref = trim($get($row, 'internal_reference'));
                $number = trim($get($row, 'student_number'));
                if (trim($index) !== '') {
                    $hits = $byIndex->get($this->service->normalise($index));
                    $candidate = $hits && $hits->count() === 1 ? $hits->first() : null;
                    if (! $candidate) {
                        $fail($line, $hits && $hits->count() > 1 ? 'err.ext.importAmbiguous' : 'err.ext.importUnmatched');

                        continue;
                    }
                } elseif ($ref !== '') {
                    $candidate = $byRef->get(strtoupper($ref));
                } elseif ($number !== '') {
                    $hits = $byNumber->get(strtoupper($number));
                    $candidate = $hits && $hits->count() === 1 ? $hits->first() : null;
                }
                if (! $candidate) {
                    $fail($line, 'err.ext.importUnmatched');

                    continue;
                }

                $subject = $subjects->get(strtoupper(trim($get($row, 'subject_code'))));
                if (! $subject) {
                    $fail($line, 'err.ext.importSubject');

                    continue;
                }
                $grade = strtoupper(trim($get($row, 'grade')));

                try {
                    $this->service->enterResult($user, $candidate, $subject->id, $grade, 'import', $batch->id);
                    $ok++;
                } catch (AcademicRuleException $e) {
                    $fail($line, $e->getMessage());
                }
            }
        });
        fclose($handle);

        $this->finish($batch, $total, $ok, $total - $ok, $errors, $total - $ok > 0 ? 'completed_with_errors' : 'completed');

        return $batch->refresh();
    }

    protected function finish(ImportBatch $batch, int $total, int $ok, int $failed, array $errors, string $status): void
    {
        $batch->update([
            'status' => $status, 'total_rows' => $total, 'processed_rows' => $total, 'success_rows' => $ok,
            'failed_rows' => $failed, 'errors' => $errors, 'finished_at' => now(),
        ]);
    }
}
