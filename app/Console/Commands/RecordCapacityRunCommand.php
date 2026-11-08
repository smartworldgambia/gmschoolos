<?php

namespace App\Console\Commands;

use App\Services\Scale\CapacityCertifier;
use Illuminate\Console\Command;

class RecordCapacityRunCommand extends Command
{
    protected $signature = 'schoolos:record-capacity-run
        {gate : gate1|gate2|gate3|gate4}
        {profile : A|B|C}
        {--p95= : p95 latency ms}
        {--errors= : error rate percent}
        {--rps= : observed RPS}
        {--vus= : virtual users}
        {--duration= : duration seconds}
        {--environment=staging}
        {--notes=}';

    protected $description = 'Record a capacity certification evidence run from k6 (or manual) results';

    public function handle(CapacityCertifier $certifier): int
    {
        $run = $certifier->record([
            'gate' => $this->argument('gate'),
            'profile' => $this->argument('profile'),
            'environment' => $this->option('environment'),
            'p95_ms' => $this->option('p95'),
            'error_rate_pct' => $this->option('errors'),
            'rps' => $this->option('rps'),
            'vus' => $this->option('vus'),
            'duration_seconds' => $this->option('duration'),
            'notes' => $this->option('notes'),
        ]);

        $this->line("Recorded #{$run->id} status={$run->status}");
        $this->line('two_million_certified='.(config('schoolos.capacity.two_million_certified') ? 'true' : 'false'));

        return $run->status === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}
