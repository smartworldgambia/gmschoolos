<?php

namespace App\Console\Commands;

use Database\Seeders\SmartworldNurserySeeder;
use Illuminate\Console\Command;

class SeedSmartworldNurseryCommand extends Command
{
    protected $signature = 'schoolos:seed-smartworld-nursery';

    protected $description = 'Seed or refresh the Smartworld Nursery showcase tenant (ECD levels, domains, classrooms, children).';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => SmartworldNurserySeeder::class,
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}
