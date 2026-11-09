<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoLoginProvisioner;
use App\Support\DemoLoginCatalog;
use Illuminate\Console\Command;

class SyncDemoLoginsCommand extends Command
{
    protected $signature = 'schoolos:sync-demo-logins';

    protected $description = 'Move demo accounts onto the live mail host and reset their password to "password". Never overwrites non-demo emails.';

    public function handle(DemoLoginProvisioner $provisioner): int
    {
        $stats = $provisioner->sync();
        $host = DemoLoginCatalog::mailHost();

        $this->info("Demo logins synced for @{$host} (password for every demo account).");
        $this->info("  Platform accounts: {$stats['platform']}");
        $this->info("  School accounts:   {$stats['school']}");
        $this->info("  Emails relocated:  {$stats['relocated']}");
        $this->info("  Platform:  platform@{$host}");
        $this->info("  School:    admin@{slug}.{$host}");

        return self::SUCCESS;
    }
}
