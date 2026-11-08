<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\BackupValidationRun;
use App\Services\Platform\BackupValidator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BackupController extends Controller
{
    use SharesPlatformShell;

    public function index(): Response
    {
        $runs = BackupValidationRun::query()
            ->with('runner:id,name')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (BackupValidationRun $run) => [
                'id' => $run->id,
                'status' => $run->status,
                'trigger' => $run->trigger,
                'duration_ms' => $run->duration_ms,
                'notes' => $run->notes,
                'checks' => $run->checks,
                'runner' => $run->runner?->name,
                'created_at' => $run->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Platform/Backups/Index', array_merge($this->platformShellProps(), [
            'runs' => $runs,
            'runbookUrl' => '/docs/runbooks/restore-drill.md',
            'runUrl' => route('platform.backups.run'),
        ]));
    }

    public function run(BackupValidator $validator): RedirectResponse
    {
        $run = $validator->run('manual', auth()->id());

        $message = $run->status === 'passed'
            ? 'Backup validation passed.'
            : 'Backup validation failed — see run details.';

        return redirect()->route('platform.backups.index')->with(
            $run->status === 'passed' ? 'success' : 'error',
            $message
        );
    }
}
