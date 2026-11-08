<?php

namespace App\Jobs;

use App\Models\ExportBatch;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SchoolAnnouncement;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessStudentExportBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $exportBatchId, public int $schoolId)
    {
        $this->onQueue(config('schoolos.queues.exports', 'exports'));
    }

    public function handle(TenantContext $tenant): void
    {
        $batch = ExportBatch::withoutGlobalScopes()->find($this->exportBatchId);
        if (! $batch || (int) $batch->school_id !== $this->schoolId) {
            return;
        }

        $tenant->set($batch->school);
        $batch->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $path = "exports/{$batch->school_id}/students-{$batch->id}.csv";
            $absolute = Storage::disk('local')->path($path);
            Storage::disk('local')->makeDirectory(dirname($path));

            $handle = fopen($absolute, 'w');
            fputcsv($handle, [
                'student_number', 'first_name', 'last_name', 'gender', 'class', 'status', 'admitted_on',
            ]);

            $count = 0;
            Student::query()
                ->with('schoolClass')
                ->orderBy('student_number')
                ->chunk(200, function ($students) use ($handle, &$count) {
                    foreach ($students as $student) {
                        fputcsv($handle, [
                            $student->student_number,
                            $student->first_name,
                            $student->last_name,
                            $student->gender,
                            $student->schoolClass?->name,
                            $student->status,
                            optional($student->admitted_on)?->toDateString(),
                        ]);
                        $count++;
                    }
                });

            fclose($handle);

            $batch->update([
                'status' => 'completed',
                'storage_path' => $path,
                'download_name' => 'students-export-'.$batch->id.'.csv',
                'total_rows' => $count,
                'finished_at' => now(),
                'expires_at' => now()->addHours(config('schoolos.exports.ttl_hours', 24)),
            ]);

            if ($batch->requested_by) {
                User::query()->find($batch->requested_by)?->notify(new SchoolAnnouncement(
                    'Student export ready',
                    "Your export of {$count} students is ready to download.",
                    route('exports.index'),
                    'success',
                    'account',
                ));
            }
        } catch (Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        } finally {
            $tenant->clear();
        }
    }
}
