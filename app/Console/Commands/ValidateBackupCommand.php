<?php

namespace App\Console\Commands;

use App\Services\Platform\BackupValidator;
use Illuminate\Console\Command;

class ValidateBackupCommand extends Command
{
    protected $signature = 'schoolos:validate-backup {--trigger=manual}';

    protected $description = 'Run restore-readiness validation and record the result';

    public function handle(BackupValidator $validator): int
    {
        $run = $validator->run(
            trigger: (string) $this->option('trigger'),
            ranBy: null,
        );

        $this->line("Status: {$run->status} ({$run->duration_ms} ms)");
        foreach ($run->checks ?? [] as $check) {
            $mark = ($check['ok'] ?? false) ? 'OK' : 'FAIL';
            $this->line("  [{$mark}] {$check['name']}: ".($check['detail'] ?? ''));
        }

        return $run->status === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}
