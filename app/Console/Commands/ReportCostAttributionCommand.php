<?php

namespace App\Console\Commands;

use App\Services\Scale\CostAttributor;
use Illuminate\Console\Command;

class ReportCostAttributionCommand extends Command
{
    protected $signature = 'schoolos:cost-report {--from=} {--to=} {--alert}';

    protected $description = 'Report relative cost attribution by school from usage snapshots';

    public function handle(CostAttributor $cost): int
    {
        $summary = $cost->platformSummary($this->option('from') ?: null, $this->option('to') ?: null);

        $this->info("Period {$summary['from']} → {$summary['to']}");
        $this->info("Total units: {$summary['total_units']} / budget {$summary['period_budget_units']} ({$summary['budget_used_pct']}%)");

        $this->table(
            ['School', 'Units', 'Students≈', 'Requests', 'Exports', 'Imports'],
            collect($summary['top'])->map(fn ($r) => [
                $r['school'], $r['units'], $r['students'], $r['requests'], $r['exports'], $r['imports'],
            ])->all()
        );

        if ($this->option('alert')) {
            $alert = $cost->raiseBudgetAlertIfNeeded($this->option('from') ?: null, $this->option('to') ?: null);
            $this->line($alert ? 'Budget alert raised.' : 'No budget alert needed.');
        }

        return self::SUCCESS;
    }
}
