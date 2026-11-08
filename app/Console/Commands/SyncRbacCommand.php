<?php

namespace App\Console\Commands;

use App\Support\Rbac;
use Illuminate\Console\Command;

class SyncRbacCommand extends Command
{
    protected $signature = 'schoolos:sync-rbac';

    protected $description = 'Sync the role/permission matrix from config/rbac.php (idempotent; run on every deploy)';

    public function handle(): int
    {
        Rbac::sync();

        $this->info(sprintf(
            'Synced %d role(s) and %d permission(s).',
            count(config('rbac.roles', [])),
            count(config('rbac.permissions.platform', [])) + count(config('rbac.permissions.school', []))
        ));

        return self::SUCCESS;
    }
}
