# Runbook: Overload Shedding

## When

p95 latency or 5xx rising, DB connections saturated, or queue oldest-age exceeding threshold.

## Preserve first

Auth → core records → attendance/exams → fee callbacks → core LMS.

## Actions (in order)

1. Pause sheddable worker pools first (`exports`, `notifications`, `reports` — see `deploy/workers/supervisord.conf` and platform **Scale** page). Keep `critical`/`default` (and imports if enrolment is in progress) running.  
2. Tighten per-tenant rate limits  
3. Serve public school sites from cache/CDN only (`schoolos:cache-warm`; public site TTL via `SCHOOLOS_CACHE_PUBLIC_SITE`)  
4. Disable expensive dashboard widgets (return “temporarily unavailable”)  
5. If still critical: read-only mode for non-write paths; keep payment callbacks and exam submit writable if possible  

Probe queue depth: `GET /metrics` → `autoscaling.current_queue_depth`.

## Never

Silently drop grades, payments, attendance, or exam submissions. Prefer 503 with retry guidance and durable queue buffering.
