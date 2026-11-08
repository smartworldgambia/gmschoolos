<?php

namespace App\Services\Platform;

use App\Models\BackupValidationRun;
use App\Models\Payment;
use App\Models\PlatformAlert;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackupValidator
{
    /**
     * Run restore-readiness checks against the live DB (staging drill should
     * point APP_ENV/DB at a restored copy). Does not invent pass when data is empty.
     */
    public function run(string $trigger = 'manual', ?int $ranBy = null): BackupValidationRun
    {
        $started = microtime(true);
        $checks = [];

        $checks[] = $this->check('database_connection', function () {
            DB::connection()->getPdo();

            return ['ok' => true, 'detail' => DB::connection()->getDatabaseName()];
        });

        $requiredTables = [
            'schools', 'users', 'students', 'payments', 'grades',
            'school_subscriptions', 'domains',
        ];

        $checks[] = $this->check('critical_tables', function () use ($requiredTables) {
            $missing = array_values(array_filter($requiredTables, fn ($t) => ! Schema::hasTable($t)));

            return [
                'ok' => $missing === [],
                'detail' => $missing === [] ? 'all present' : 'missing: '.implode(', ', $missing),
                'missing' => $missing,
            ];
        });

        $checks[] = $this->check('sample_tenant', function () {
            $school = School::query()->orderBy('id')->first();
            if (! $school) {
                return ['ok' => false, 'detail' => 'no schools to verify'];
            }

            $students = Student::withoutGlobalScopes()->where('school_id', $school->id)->count();
            $payments = Payment::withoutGlobalScopes()->where('school_id', $school->id)->count();

            return [
                'ok' => true,
                'detail' => "{$school->slug}: students={$students}, payments={$payments}",
                'school_id' => $school->id,
                'students' => $students,
                'payments' => $payments,
            ];
        });

        $checks[] = $this->check('local_storage_writable', function () {
            $disk = Storage::disk('local');
            $path = 'backup-validation/'.now()->format('YmdHis').'.txt';
            $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $disk->delete($path);

            return ['ok' => $exists, 'detail' => $exists ? 'write/read/delete ok' : 'write failed'];
        });

        $checks[] = $this->check('platform_admin_present', function () {
            $count = User::query()->whereNull('school_id')->count();

            return [
                'ok' => $count > 0,
                'detail' => "{$count} central user(s)",
            ];
        });

        $failed = collect($checks)->contains(fn ($c) => ! ($c['ok'] ?? false));
        $status = $failed ? 'failed' : 'passed';
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $run = BackupValidationRun::query()->create([
            'status' => $status,
            'trigger' => $trigger,
            'ran_by' => $ranBy,
            'duration_ms' => $durationMs,
            'checks' => $checks,
            'notes' => $failed
                ? 'One or more restore-readiness checks failed. See checks JSON.'
                : 'Restore-readiness checks passed against current database.',
        ]);

        if ($failed) {
            PlatformAlert::query()->create([
                'severity' => 'critical',
                'source' => 'backup',
                'title' => 'Backup validation failed',
                'message' => 'schoolos:validate-backup reported failures. Review backup_validation_runs #'.$run->id,
                'context' => ['run_id' => $run->id, 'checks' => $checks],
            ]);
        }

        return $run;
    }

    protected function check(string $name, callable $fn): array
    {
        try {
            $result = $fn();

            return array_merge(['name' => $name], $result);
        } catch (Throwable $e) {
            return [
                'name' => $name,
                'ok' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }
}
