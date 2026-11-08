<?php

namespace App\Services\Ops;

use App\Models\ErrorReport;
use App\Models\PlatformAlert;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorReporter
{
    public function report(Throwable $e, array $context = []): void
    {
        if (! config('schoolos.errors.enabled', true)) {
            return;
        }

        // Group by class + message + file (omit line so identical probes dedupe across call sites).
        $fingerprint = hash('sha256', $e::class.'|'.mb_substr($e->getMessage(), 0, 200).'|'.$e->getFile());

        $payload = [
            'exception' => $e::class,
            'message' => mb_substr($e->getMessage(), 0, 2000),
            'file' => $e->getFile().':'.$e->getLine(),
            'url' => request()?->fullUrl(),
            'user_id' => auth()->id(),
            'school_id' => app(TenantContext::class)->id() ?? auth()->user()?->school_id,
            'context' => $context,
        ];

        Log::channel(config('schoolos.errors.log_channel', 'stack'))->error($payload['message'], $payload);

        try {
            $report = ErrorReport::query()->firstOrNew(['fingerprint' => $fingerprint]);
            $report->fill([
                'level' => 'error',
                'exception_class' => $e::class,
                'message' => $payload['message'],
                'url' => $payload['url'],
                'user_id' => $payload['user_id'],
                'school_id' => $payload['school_id'],
                'context' => [
                    'file' => $payload['file'],
                    'extra' => $context,
                ],
                'last_seen_at' => now(),
            ]);
            $report->occurrences = ($report->exists ? $report->occurrences : 0) + 1;
            $report->save();

            if (config('schoolos.errors.raise_platform_alerts', true) && $report->wasRecentlyCreated) {
                PlatformAlert::query()->create([
                    'severity' => 'critical',
                    'source' => 'health',
                    'title' => 'Unhandled exception',
                    'message' => $payload['exception'].': '.$payload['message'],
                    'school_id' => $payload['school_id'],
                    'context' => ['fingerprint' => $fingerprint, 'error_report_id' => $report->id],
                ]);
            }
        } catch (Throwable $inner) {
            Log::warning('Failed to persist error report', ['error' => $inner->getMessage()]);
        }

        $webhook = config('schoolos.errors.webhook_url');
        if ($webhook) {
            try {
                Http::timeout(3)->post($webhook, [
                    'text' => '[SchoolOS] '.$payload['exception'].': '.$payload['message'],
                    'fingerprint' => $fingerprint,
                    'url' => $payload['url'],
                    'env' => config('app.env'),
                ]);
            } catch (Throwable $inner) {
                Log::warning('Error webhook failed', ['error' => $inner->getMessage()]);
            }
        }
    }

    public function status(): array
    {
        return [
            'enabled' => (bool) config('schoolos.errors.enabled', true),
            'webhook_configured' => filled(config('schoolos.errors.webhook_url')),
            'raise_platform_alerts' => (bool) config('schoolos.errors.raise_platform_alerts', true),
            'recent_count' => ErrorReport::query()->where('created_at', '>=', now()->subDay())->count(),
        ];
    }
}
