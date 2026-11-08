<?php

namespace App\Services\School;

/**
 * Thin wrapper over PHP DNS lookups so domain verification can be faked in tests
 * and swapped for a DoH / provider API resolver later without touching callers.
 */
class DnsResolver
{
    /** @return list<string> CNAME targets, lowercase without trailing dot */
    public function cname(string $host): array
    {
        return $this->lookup($host, DNS_CNAME, 'target');
    }

    /** @return list<string> IPv4 addresses */
    public function a(string $host): array
    {
        return $this->lookup($host, DNS_A, 'ip');
    }

    /** @return list<string> TXT values (multi-part records joined) */
    public function txt(string $host): array
    {
        $records = $this->records($host, DNS_TXT);
        $values = [];

        foreach ($records as $row) {
            $value = $row['txt'] ?? null;
            if ($value === null && isset($row['entries']) && is_array($row['entries'])) {
                $value = implode('', $row['entries']);
            }
            if (is_string($value) && $value !== '') {
                $values[] = trim($value);
            }
        }

        return $values;
    }

    /** @return list<string> */
    protected function lookup(string $host, int $type, string $key): array
    {
        $values = [];
        foreach ($this->records($host, $type) as $row) {
            $value = $row[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $values[] = strtolower(rtrim($value, '.'));
            }
        }

        return array_values(array_unique($values));
    }

    /** @return array<int, array<string, mixed>> */
    protected function records(string $host, int $type): array
    {
        try {
            $result = @dns_get_record($host, $type);
        } catch (\Throwable) {
            return [];
        }

        return is_array($result) ? $result : [];
    }
}
