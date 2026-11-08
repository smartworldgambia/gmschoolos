<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessStudentImportBatch;
use App\Models\ImportBatch;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    use SharesSchoolShell;

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('students.import'), 403);

        $batches = ImportBatch::query()
            ->where('type', 'students')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ImportBatch $batch) => [
                'id' => $batch->id,
                'original_filename' => $batch->original_filename,
                'status' => $batch->status,
                'total_rows' => $batch->total_rows,
                'success_rows' => $batch->success_rows,
                'failed_rows' => $batch->failed_rows,
                'errors' => $batch->errors ?? [],
                'created_at' => $batch->created_at?->toDateTimeString(),
                'finished_at' => $batch->finished_at?->toDateTimeString(),
            ]);

        return Inertia::render('Students/Import', array_merge($this->shellProps(), [
            'batches' => $batches,
            'templateUrl' => route('students.import.template'),
            'storeUrl' => route('students.import.store'),
        ]));
    }

    public function template(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('students.import'), 403);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            // student_number is optional (leave it empty to receive a generated permanent number).
            // gender: male|female|other. Dates: YYYY-MM-DD. class: the exact class name already set up.
            fputcsv($out, [
                'student_number', 'first_name', 'last_name', 'gender', 'date_of_birth', 'class', 'status', 'admitted_on',
                'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email',
            ]);
            fputcsv($out, [
                '', 'Example', 'Student', 'female', '2010-05-12', 'Grade 10A', 'active', '2024-09-02',
                'Example Guardian', 'mother', '+2207000000', 'guardian@example.test',
            ]);
            fclose($out);
        }, 'student-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('students.import'), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $schoolId = app(TenantContext::class)->id() ?? auth()->user()->school_id;
        $file = $validated['file'];
        $path = $file->store("imports/{$schoolId}/students", 'local');

        $batch = ImportBatch::query()->create([
            'school_id' => $schoolId,
            'uploaded_by' => auth()->id(),
            'type' => 'students',
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'status' => 'pending',
        ]);

        ProcessStudentImportBatch::dispatch($batch->id, (int) $schoolId);

        return back()->with('success', 'ok.importQueued');
    }

    public function status(int $batch): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()?->can('students.import'), 403);

        $record = ImportBatch::query()->findOrFail($batch);

        return response()->json([
            'id' => $record->id,
            'status' => $record->status,
            'total_rows' => $record->total_rows,
            'processed_rows' => $record->processed_rows,
            'success_rows' => $record->success_rows,
            'failed_rows' => $record->failed_rows,
            'errors' => $record->errors ?? [],
            'finished_at' => $record->finished_at?->toDateTimeString(),
        ]);
    }
}
