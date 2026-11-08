<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\School;
use App\Services\Finance\InvoiceService;
use App\Services\Ops\OpsSettings;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/** Reminds guardians about overdue school-fee invoices, at most every `reminder_days` days and `reminder_max` times per invoice. */
class SendFeeRemindersCommand extends Command
{
    protected $signature = 'schoolos:fees:remind';

    protected $description = 'Remind guardians about overdue school-fee invoices';

    public function handle(InvoiceService $invoices, OpsSettings $settings, TenantContext $tenant): int
    {
        $sent = 0;

        School::query()->where('status', 'active')->each(function (School $school) use ($invoices, $settings, $tenant, &$sent) {
            $cfg = $settings->group('finance', $school);
            if ((int) $cfg['reminder_max'] < 1) {
                return;
            }

            $tenant->runForSchool($school, function () use ($invoices, $cfg, &$sent) {
                Invoice::query()->late()->where('reminder_count', '<', (int) $cfg['reminder_max'])
                    ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<=', now()->subDays((int) $cfg['reminder_days'])))
                    ->each(function (Invoice $invoice) use ($invoices, &$sent) {
                        $invoices->notifyGuardians($invoice, 'reminder');
                        $invoice->forceFill(['reminded_at' => now(), 'reminder_count' => $invoice->reminder_count + 1])->save();
                        $sent++;
                    });
            });
        });

        $this->info("Sent {$sent} fee reminder(s).");

        return self::SUCCESS;
    }
}
