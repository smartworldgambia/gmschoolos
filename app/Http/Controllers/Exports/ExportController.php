<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessStudentExportBatch;
use App\Models\ExportBatch;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    use SharesSchoolShell;

    public function index(): Response
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $batches = ExportBatch::query()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ExportBatch $batch) => [
                'id' => $batch->id,
                'type' => $batch->type,
                'status' => $batch->status,
                'total_rows' => $batch->total_rows,
                'download_name' => $batch->download_name,
                'created_at' => $batch->created_at?->toDateTimeString(),
                'finished_at' => $batch->finished_at?->toDateTimeString(),
                'expires_at' => $batch->expires_at?->toDateTimeString(),
                'error_message' => $batch->error_message,
                'download_url' => $batch->status === 'completed' ? route('exports.download', $batch) : null,
            ]);

        return Inertia::render('Exports/Index', array_merge($this->shellProps(), [
            'batches' => $batches,
            'storeUrl' => route('exports.store'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:students'],
        ]);

        $schoolId = app(TenantContext::class)->id() ?? auth()->user()->school_id;

        $batch = ExportBatch::query()->create([
            'school_id' => $schoolId,
            'requested_by' => auth()->id(),
            'type' => $validated['type'],
            'status' => 'pending',
        ]);

        ProcessStudentExportBatch::dispatch($batch->id, (int) $schoolId);

        return back()->with('success', 'Export queued. You will be notified when it is ready.');
    }

    public function download(int $export): StreamedResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);

        $batch = ExportBatch::query()->findOrFail($export);
        abort_unless($batch->status === 'completed' && $batch->storage_path, 404);
        abort_if($batch->expires_at && $batch->expires_at->isPast(), 410, 'Export link expired.');
        abort_unless(Storage::disk('local')->exists($batch->storage_path), 404);

        return Storage::disk('local')->download(
            $batch->storage_path,
            $batch->download_name ?? 'export.csv'
        );
    }

    public function status(int $export): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()?->can('reports.export'), 403);
        $batch = ExportBatch::query()->findOrFail($export);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'download_url' => $batch->status === 'completed' ? route('exports.download', $batch) : null,
            'error_message' => $batch->error_message,
        ]);
    }
}
