# Cost Management

## Principle

Demand-driven scaling with explicit ceilings. Never allow unbounded autoscaling spend; never crash the platform with an unexpectedly tiny capacity cap without a documented emergency load-shed path.

## Day-one controls

- Budgets and spending alerts per environment (dev / staging / production)
- Resource tags: `env`, `service`, `project=schoolos` (see `config/schoolos.php` → `cost.tags`)
- Maximum autoscaling instance and worker counts (`SCHOOLOS_AS_*`, `deploy/autoscaling/`)
- Queue and log retention limits
- Lifecycle policies for temporary exports and old backups
- Per-tenant relative cost units from usage snapshots: `php artisan schoolos:cost-report` and platform **Scale** page (`SCHOOLOS_COST_DAILY_BUDGET`)

## What not to do

- Do not quote infrastructure cost for 2M users until workload, region, HA target, and services are modelled from measured profiles.
- Do not run oversized non-production fleets continuously.
- Do not keep debug logs or unbounded audit blobs without retention.

## Emergency spend brake

Documented process (see [overload-shedding.md](../runbooks/overload-shedding.md)):

1. Cap noncritical workers (reports, analytics, bulk mail)
2. Enable stricter tenant rate limits
3. Serve cached public pages aggressively
4. Manual override to raise caps only with named approver and time-boxed review
