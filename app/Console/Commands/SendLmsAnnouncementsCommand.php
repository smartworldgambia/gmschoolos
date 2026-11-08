<?php

namespace App\Console\Commands;

use App\Services\Lms\CommunityService;
use Illuminate\Console\Command;

class SendLmsAnnouncementsCommand extends Command
{
    protected $signature = 'schoolos:lms:announce';

    protected $description = 'Notify enrolled learners about scheduled course announcements that have just become due';

    public function handle(CommunityService $service): int
    {
        $this->info('Notified '.$service->notifyDue().' learner(s).');

        return self::SUCCESS;
    }
}
