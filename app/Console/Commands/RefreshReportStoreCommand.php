<?php

namespace App\Console\Commands;

use App\Services\Reporting\ReportStoreRefresher;
use Illuminate\Console\Command;

class RefreshReportStoreCommand extends Command
{
    protected $signature = 'schoolos:refresh-report-store {--date=}';

    protected $description = 'Refresh the advanced reporting fact store from OLTP aggregates';

    public function handle(ReportStoreRefresher $refresher): int
    {
        $n = $refresher->refreshAll($this->option('date') ?: null);
        $this->info("Wrote/updated {$n} report fact row(s).");

        return self::SUCCESS;
    }
}
