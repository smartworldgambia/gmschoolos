<?php

namespace App\Console\Commands;

use App\Services\School\SchoolDomainService;
use Illuminate\Console\Command;

class VerifyDomainsCommand extends Command
{
    protected $signature = 'schoolos:verify-domains';

    protected $description = 'Verify pending custom domains via DNS, activate passing ones and release stale claims';

    public function handle(SchoolDomainService $domains): int
    {
        $stats = $domains->verifyPending();

        $this->info(sprintf(
            'Checked %d pending domain(s): %d verified, %d expired.',
            $stats['checked'],
            $stats['verified'],
            $stats['expired'],
        ));

        return self::SUCCESS;
    }
}
