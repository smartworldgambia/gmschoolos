<?php

namespace App\Console\Commands;

use App\Services\Billing\PaymentReconciler;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'schoolos:billing:reconcile {--dry-run : List what would be checked without contacting providers}';

    protected $description = 'Re-check unconfirmed Stripe/Wave sessions server-side and expire stale open payments (idempotent).';

    public function handle(PaymentReconciler $reconciler): int
    {
        $r = $reconciler->run((bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%schecked: %d, settled: %d, expired: %d, errors: %d',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $r['checked'], $r['settled'], $r['expired'], $r['errors'],
        ));

        return $r['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
