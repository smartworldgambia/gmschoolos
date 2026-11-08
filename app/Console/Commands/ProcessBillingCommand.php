<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionLifecycle;
use Illuminate\Console\Command;

class ProcessBillingCommand extends Command
{
    protected $signature = 'schoolos:billing:process {--dry-run : Report what would change without changing anything}';

    protected $description = 'Expire/suspend lapsed trials and annual subscriptions and send due reminders (idempotent).';

    public function handle(SubscriptionLifecycle $lifecycle): int
    {
        $result = $lifecycle->processDue(dryRun: (bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%sexpired: %d, suspended: %d, reminders: %d',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $result['expired'],
            $result['suspended'],
            $result['reminders'],
        ));

        return self::SUCCESS;
    }
}