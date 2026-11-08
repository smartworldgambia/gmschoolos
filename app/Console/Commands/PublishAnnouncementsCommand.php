<?php

namespace App\Console\Commands;

use App\Services\Communications\AnnouncementService;
use Illuminate\Console\Command;

class PublishAnnouncementsCommand extends Command
{
    protected $signature = 'schoolos:announcements:publish';

    protected $description = 'Publish school and class announcements whose scheduled time has come';

    public function handle(AnnouncementService $service): int
    {
        $this->info('Published '.$service->publishDue().' announcement(s).');

        return self::SUCCESS;
    }
}
