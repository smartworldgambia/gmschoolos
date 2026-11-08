<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\ErrorReport;
use App\Services\Ops\ErrorReporter;
use Inertia\Inertia;
use Inertia\Response;

class OpsController extends Controller
{
    use SharesPlatformShell;

    public function __invoke(ErrorReporter $reporter): Response
    {
        $recent = ErrorReport::query()
            ->latest('last_seen_at')
            ->limit(30)
            ->get()
            ->map(fn (ErrorReport $r) => [
                'id' => $r->id,
                'exception_class' => $r->exception_class,
                'message' => $r->message,
                'occurrences' => $r->occurrences,
                'url' => $r->url,
                'last_seen_at' => $r->last_seen_at?->diffForHumans(),
            ]);

        return Inertia::render('Platform/Ops/Index', array_merge($this->platformShellProps(), [
            'errorReporting' => $reporter->status(),
            'recentErrors' => $recent,
            'security' => [
                'headers_enabled' => (bool) config('schoolos.security.headers_enabled', true),
                'hsts_enabled' => (bool) config('schoolos.security.hsts_enabled', false),
                'mfa_available' => true,
                'force_https' => (bool) config('schoolos.security.force_https', false),
            ],
        ]));
    }
}
