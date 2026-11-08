# Workload modelling

Phase 7 introduces reproducible **estimates** for traffic profiles A/B/C.

## Commands

```bash
php artisan schoolos:model-workload
php artisan schoolos:model-workload --persist
```

Platform UI: **Capacity** (`/platform/capacity`).

## What the model does

Given assumed active users, schools, and requests/user/min, it estimates:

- RPS
- App replicas (from `SCHOOLOS_WL_RPS_PER_REPLICA`)
- Worker count (capped by autoscaling max)
- DB connections (`replicas × SCHOOLOS_WL_DB_CONN_PER_REPLICA`) vs budget

## What it does not do

- It does **not** certify production capacity
- It does **not** claim 2,000,000 concurrent users
- Profile C “extreme burst” is a modelling stress input, not a passed load test

Wire measured k6 results into certification via [`capacity-certification.md`](capacity-certification.md).
