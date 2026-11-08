# Capacity certification

## Policy

`config('schoolos.capacity.two_million_certified')` is **false** until Gate evidence for extreme profiles is explicitly reviewed and the flag is changed by humans with documentation. Phase 7 tooling records evidence; it never auto-flips a 2M claim.

## Gates (evidence)

| Gate | Script | Intent |
|------|--------|--------|
| 1 | `loadtests/k6/gate1-*.js` | Correctness / low load |
| 2 | `gate2-sustained.js` | Sustained latency |
| 3 | `gate3-burst.js` (+ distributed generators) | Burst behaviour |
| 4 | TBD | DB/queue bottleneck focus |

Record:

```bash
php artisan schoolos:record-capacity-run gate2 A --p95=720 --errors=0.2 --rps=22 --vus=20 --duration=120 --environment=staging
```

Or use the platform Capacity form.

## Pass criteria

Thresholds live in `config/schoolos.php` → `capacity.gates.*`. A “passed” row means that **run** met thresholds — not that the entire platform is certified for Profile C or 2M users.
