<?php

namespace App\Jobs;

use App\Services\Reporting\ReportStoreRefresher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshReportStoreJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(config('schoolos.queues.reports', 'reports'));
    }

    public function handle(ReportStoreRefresher $refresher): void
    {
        $refresher->refreshAll();
    }
}
