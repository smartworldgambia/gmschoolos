<?php

namespace App\Console\Commands;

use App\Services\Platform\UsageRecorder;
use Illuminate\Console\Command;

class SnapshotUsageCommand extends Command
{
    protected $signature = 'schoolos:snapshot-usage {--date=}';

    protected $description = 'Capture per-tenant usage snapshots for quotas and billing';

    public function handle(UsageRecorder $recorder): int
    {
        $date = $this->option('date') ?: null;
        $count = $recorder->snapshotAll($date);
        $this->info("Captured usage snapshots for {$count} school(s).");

        return self::SUCCESS;
    }
}
