<?php

namespace App\Console\Commands;

use App\Services\Scale\CacheTuner;
use Illuminate\Console\Command;

class WarmCacheCommand extends Command
{
    protected $signature = 'schoolos:cache-warm';

    protected $description = 'Warm public site and landing price caches';

    public function handle(CacheTuner $cache): int
    {
        $result = $cache->warm();
        $this->info('Warmed landing prices: '.($result['prices'] ? 'yes' : 'no'));
        $this->info("Warmed public sites: {$result['public_sites']}");

        return self::SUCCESS;
    }
}
