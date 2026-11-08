<?php

namespace App\Console\Commands;

use App\Services\ExternalExams\CollectionService;
use Illuminate\Console\Command;

class SendCollectionRemindersCommand extends Command
{
    protected $signature = 'schoolos:records:remind';

    protected $description = 'Remind school staff about printed results and certificates still waiting for collection';

    public function handle(CollectionService $service): int
    {
        $schools = $service->sendReminders();
        $this->info("Sent collection reminders for {$schools} school(s).");

        return self::SUCCESS;
    }
}
