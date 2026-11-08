# Load Testing Plan

## Tools

Prefer **k6** (or Gatling/Locust) against a production-like staging environment. Load generators must be separate from the system under test.

## Journeys to script (progressive)

1. Login / logout  
2. Student and teacher dashboards  
3. Attendance submission  
4. Assignment submission / quiz attempt  
5. Fee lookup and payment initiation  
6. Report card view  
7. School public website  
8. Bulk import (async) and export download  
9. Concurrent examination activity  

## Profiles

- **A** Ordinary school day  
- **B** Peak education workload  
- **C** Extreme burst  

Measure: RPS, p50/p95/p99 latency, error rate, DB connections, cache hit ratio, queue depth, cost.

## Gates

| Gate | Focus |
|------|-------|
| 1 | Correctness and security at low load |
| 2 | Sustained load and latency targets |
| 3 | Burst and autoscaling behaviour |
| 4 | DB and queue bottlenecks |
| 5 | Failure recovery |
| 6 | Larger distributed capacity |
| 7 | Final target-scale assessment |

## Honesty rule

Do not extrapolate a two-million-user result from a small test without documenting assumptions and validating major bottlenecks.

## Phase 5–7 status

| Gate | Location |
|------|----------|
| 1 | `loadtests/k6/gate1-*.js` |
| 2 | `loadtests/k6/gate2-sustained.js` |
| 3 | `loadtests/k6/gate3-burst.js` + `loadtests/k6/distributed/` |
| Evidence | `schoolos:record-capacity-run` / platform Capacity |

Metrics probe: `GET /metrics` with `Authorization: Bearer` in production (`METRICS_TOKEN` for k6). Passing gates records evidence only — **2M concurrent users are not certified.** Gate 1–3 have **not** been run against a production-like staging environment in this XAMPP workspace.
