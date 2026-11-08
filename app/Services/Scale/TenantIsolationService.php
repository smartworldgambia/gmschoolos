<?php

namespace App\Services\Scale;

use App\Models\School;
use App\Models\TenantDatabaseConnection;
use Illuminate\Support\Str;
use RuntimeException;

class TenantIsolationService
{
    public const TIERS = ['shared', 'premium_isolated', 'dedicated_db'];

    public function tiers(): array
    {
        return [
            'shared' => [
                'label' => 'Shared DB',
                'description' => 'Default school_id scoping on the primary database.',
            ],
            'premium_isolated' => [
                'label' => 'Premium isolated path',
                'description' => 'Same DB with stricter quotas, dedicated worker fairness, partition_key tagging for future table partitioning.',
            ],
            'dedicated_db' => [
                'label' => 'Dedicated database',
                'description' => 'Separate DB connection for a noisy tenant. Requires provisioning + cutover; connection row must be active.',
            ],
        ];
    }

    public function assignTier(School $school, string $tier, ?string $connectionName = null): School
    {
        if (! in_array($tier, self::TIERS, true)) {
            throw new RuntimeException('Invalid isolation tier.');
        }

        $school->isolation_tier = $tier;
        $school->partition_key = $school->partition_key ?: 'school_'.$school->id;
        $school->isolation_reviewed_at = now();

        if ($tier === 'dedicated_db') {
            $school->db_connection = $connectionName ?: ('tenant_'.$school->slug);
        } elseif ($tier === 'shared') {
            $school->db_connection = null;
        }

        $school->save();

        return $school->fresh();
    }

    public function planDedicatedDatabase(School $school, array $attrs): TenantDatabaseConnection
    {
        $name = $attrs['connection_name'] ?? ('tenant_'.Str::slug($school->slug));

        $conn = TenantDatabaseConnection::query()->updateOrCreate(
            ['school_id' => $school->id],
            [
                'connection_name' => $name,
                'driver' => $attrs['driver'] ?? 'mysql',
                'host' => $attrs['host'] ?? 'db.example.internal',
                'port' => $attrs['port'] ?? 3306,
                'database' => $attrs['database'] ?? ('schoolos_'.$school->slug),
                'username' => $attrs['username'] ?? 'schoolos',
                'password_encrypted' => isset($attrs['password']) ? encrypt($attrs['password']) : null,
                'status' => 'planned',
                'notes' => [
                    'message' => 'Planned only — app will not switch until status=active and cutover runbook completed.',
                ],
            ]
        );

        $this->assignTier($school, 'dedicated_db', $name);

        return $conn;
    }

    /**
     * Resolve which Laravel DB connection name a school should use.
     * Dedicated connections are only returned when the planning row is active.
     */
    public function connectionFor(School $school): string
    {
        if ($school->isolation_tier !== 'dedicated_db' || ! $school->db_connection) {
            return config('database.default');
        }

        $row = TenantDatabaseConnection::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->first();

        return $row?->connection_name ?? config('database.default');
    }

    public function platformSummary(): array
    {
        $counts = School::query()
            ->selectRaw('isolation_tier, COUNT(*) as c')
            ->groupBy('isolation_tier')
            ->pluck('c', 'isolation_tier');

        return [
            'tiers' => $this->tiers(),
            'counts' => [
                'shared' => (int) ($counts['shared'] ?? 0),
                'premium_isolated' => (int) ($counts['premium_isolated'] ?? 0),
                'dedicated_db' => (int) ($counts['dedicated_db'] ?? 0),
            ],
            'planned_connections' => TenantDatabaseConnection::query()->where('status', 'planned')->count(),
            'active_dedicated' => TenantDatabaseConnection::query()->where('status', 'active')->count(),
        ];
    }
}
