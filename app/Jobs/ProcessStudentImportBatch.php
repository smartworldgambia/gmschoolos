<?php

namespace App\Jobs;

use App\Exceptions\AcademicRuleException;
use App\Models\ImportBatch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ImportCompletedNotification;
use App\Services\Students\StudentRegistrar;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Bulk student import. Rows are validated one by one; a bad or duplicate row is REPORTED and skipped - it is never
 * imported silently and an existing student is never overwritten. Valid rows are registered through
 * StudentRegistrar (permanent number, quota, class capacity, duplicate detection).
 *
 * Row errors are stable `err.import.*` keys with the CSV row number, translated by the front end.
 */
class ProcessStudentImportBatch implements ShouldQueue
{
    use Queueable;

    public const MAX_ROWS = 5000;

    public const MAX_ERRORS = 500;

    public int $tries = 1; // a retry must not re-process (and re-report) rows that already succeeded

    public function __construct(public int $importBatchId, public int $schoolId)
    {
        $this->onQueue(config('schoolos.queues.imports', 'imports'));
    }

    public function handle(TenantContext $tenant, StudentRegistrar $registrar): void
    {
        $batch = ImportBatch::withoutGlobalScopes()->find($this->importBatchId);
        if (! $batch || (int) $batch->school_id !== $this->schoolId) {
            return;
        }

        $school = $batch->school;
        $previous = $tenant->school(); // a synchronous queue runs inside a request: put its tenant back afterwards
        $tenant->set($school);

        $batch->update(['status' => 'processing', 'started_at' => now(), 'errors' => []]);

        try {
            $path = $batch->storage_path;
            if (! Storage::disk('local')->exists($path)) {
                throw AcademicRuleException::because('err.import.fileMissing');
            }
            $handle = fopen(Storage::disk('local')->path($path), 'r');
            if ($handle === false) {
                throw AcademicRuleException::because('err.import.fileMissing');
            }

            $header = null;
            $rowNumber = 0;
            $errors = [];
            $success = 0;
            $failed = 0;
            $processed = 0;
            $seenNumbers = [];
            $seenIdentities = [];

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($rowNumber === 1) {
                    $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $row);

                    continue;
                }
                if ($this->rowEmpty($row)) {
                    continue;
                }
                if (++$processed > self::MAX_ROWS) {
                    throw AcademicRuleException::because('err.import.tooLarge');
                }

                $data = $this->mapRow($header, $row);

                try {
                    $clean = $this->validateRow($data, $seenNumbers, $seenIdentities);
                    $registrar->register($school, $clean['student'], $clean['guardian']);
                    $success++;
                } catch (AcademicRuleException $e) {
                    $failed++;
                    $this->remember($errors, $rowNumber, $e->getMessage());
                } catch (Throwable $e) {
                    $failed++;
                    report($e);
                    $this->remember($errors, $rowNumber, 'err.import.unexpected');
                }
            }
            fclose($handle);

            $batch->update([
                'status' => $failed > 0 && $success === 0 ? 'failed' : 'completed',
                'total_rows' => $processed, 'processed_rows' => $processed,
                'success_rows' => $success, 'failed_rows' => $failed,
                'errors' => $errors, 'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'errors' => [['row' => null, 'message' => $e instanceof AcademicRuleException ? $e->getMessage() : 'err.import.unexpected']],
                'finished_at' => now(),
            ]);
            if (! $e instanceof AcademicRuleException) {
                report($e);
            }
        } finally {
            if ($batch->uploaded_by) {
                User::query()->find($batch->uploaded_by)?->notify(new ImportCompletedNotification($batch->fresh()));
            }
            $previous ? $tenant->set($previous) : $tenant->clear();
        }
    }

    protected function remember(array &$errors, int $row, string $message): void
    {
        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = ['row' => $row, 'message' => $message];
        }
    }

    protected function rowEmpty(array $row): bool
    {
        return collect($row)->every(fn ($v) => trim((string) $v) === '');
    }

    protected function mapRow(array $header, array $row): array
    {
        $assoc = [];
        foreach ($header as $i => $key) {
            $assoc[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
        }

        return [
            'student_number' => $assoc['student_number'] ?? $assoc['id'] ?? '',
            'first_name' => $assoc['first_name'] ?? '',
            'last_name' => $assoc['last_name'] ?? '',
            'gender' => strtolower($assoc['gender'] ?? ''),
            'date_of_birth' => $assoc['date_of_birth'] ?? '',
            'class' => $assoc['class'] ?? $assoc['class_name'] ?? '',
            'status' => strtolower($assoc['status'] ?? ''),
            'admitted_on' => $assoc['admitted_on'] ?? '',
            'guardian_name' => $assoc['guardian_name'] ?? '',
            'guardian_relationship' => $assoc['guardian_relationship'] ?? '',
            'guardian_phone' => $assoc['guardian_phone'] ?? '',
            'guardian_email' => $assoc['guardian_email'] ?? '',
        ];
    }

    /**
     * @param  array<string,bool>  $seenNumbers
     * @param  array<string,bool>  $seenIdentities
     * @return array{student:array<string,mixed>,guardian:?array<string,mixed>}
     *
     * @throws AcademicRuleException
     */
    protected function validateRow(array $d, array &$seenNumbers, array &$seenIdentities): array
    {
        if ($d['first_name'] === '' || $d['last_name'] === '') {
            throw AcademicRuleException::because('err.import.nameRequired');
        }
        if (! in_array($d['gender'], ['male', 'female', 'other'], true)) {
            throw AcademicRuleException::because('err.import.gender');
        }
        if (! in_array($d['status'], ['', 'active'], true)) {
            throw AcademicRuleException::because('err.import.status');
        }

        $dob = $this->date($d['date_of_birth']);
        if (! $dob || $dob->isFuture() || $dob->year < 1950) {
            throw AcademicRuleException::because('err.import.dob');
        }

        $admitted = null;
        if ($d['admitted_on'] !== '') {
            $admitted = $this->date($d['admitted_on']);
            if (! $admitted || $admitted->isFuture()) {
                throw AcademicRuleException::because('err.import.admittedOn');
            }
        }

        if ($d['guardian_email'] !== '' && ! filter_var($d['guardian_email'], FILTER_VALIDATE_EMAIL)) {
            throw AcademicRuleException::because('err.import.guardianEmail');
        }

        $classId = null;
        if ($d['class'] !== '') {
            $matches = SchoolClass::query()->whereRaw('lower(name) = ?', [mb_strtolower($d['class'])])->pluck('id');
            if ($matches->isEmpty()) {
                throw AcademicRuleException::because('err.import.classUnknown');
            }
            if ($matches->count() > 1) {
                throw AcademicRuleException::because('err.import.classAmbiguous');
            }
            $classId = (int) $matches->first();
        }

        // duplicates inside the file
        $identity = mb_strtolower($d['first_name'].'|'.$d['last_name'].'|'.$dob->toDateString());
        if (isset($seenIdentities[$identity])) {
            throw AcademicRuleException::because('err.import.duplicateInFile');
        }
        if ($d['student_number'] !== '') {
            $key = mb_strtolower($d['student_number']);
            if (isset($seenNumbers[$key])) {
                throw AcademicRuleException::because('err.import.duplicateInFile');
            }
            if (Student::query()->where('student_number', $d['student_number'])->exists()) {
                throw AcademicRuleException::because('err.import.numberExists');
            }
        }
        // duplicates already in the school: the registrar raises err.student.duplicate (never overwritten)
        $seenIdentities[$identity] = true;
        if ($d['student_number'] !== '') {
            $seenNumbers[mb_strtolower($d['student_number'])] = true;
        }

        return [
            'student' => [
                'student_number' => $d['student_number'] ?: null,
                'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'gender' => $d['gender'],
                'date_of_birth' => $dob->toDateString(), 'school_class_id' => $classId,
                'admitted_on' => $admitted?->toDateString() ?? now()->toDateString(),
            ],
            'guardian' => $d['guardian_name'] !== '' ? [
                'name' => $d['guardian_name'], 'relationship' => $d['guardian_relationship'] ?: null,
                'phone' => $d['guardian_phone'] ?: null, 'email' => $d['guardian_email'] ?: null,
            ] : null,
        ];
    }

    protected function date(string $value): ?Carbon
    {
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null; // ISO dates only: day/month order is ambiguous and is never guessed
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);

            return $date && $date->format('Y-m-d') === $value ? $date->startOfDay() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
