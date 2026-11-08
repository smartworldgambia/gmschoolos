# Autoscaling configuration

Phase 5 delivers **configuration sketches and metrics hooks**, not a certified capacity number.

## Signals

| Signal | Source | Use |
|--------|--------|-----|
| CPU / memory | Host metrics | App replicas |
| p95 latency | APM / load balancer | App scale-out |
| Queue depth | `GET /metrics` (Bearer token in production) → `autoscaling.current_queue_depth` | Worker replicas |
| DB connections | DB metrics | Hard ceiling on app scale-out |

## Caps (env)

See `config/schoolos.php` → `autoscaling` and `.env.example` (`SCHOOLOS_AS_*`).

## Artefacts

- `deploy/autoscaling/app-service.example.yaml`
- `deploy/autoscaling/workers.example.yaml`
- `deploy/workers/supervisord.conf` — independent pools

## Cost attribution

Relative units from usage snapshots (`schoolos:cost-report`, platform **Scale** page). Tags: `project=schoolos`, `env`, `service`. Not currency; not a 2M-user quote.

## Honesty

Raising `maxReplicas` without Gate 2+ load evidence does not create capacity. **2,000,000 concurrent users remain uncertified.** Production `/metrics` is fail-closed without `SCHOOLOS_METRICS_TOKEN` or an IP allow-list (Phase 11).
