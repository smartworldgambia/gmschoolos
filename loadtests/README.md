# Load tests (k6)

## Gates

| Gate | Script | Intent |
|------|--------|--------|
| 1 | `k6/gate1-smoke.js`, `gate1-journeys.js` | Correctness / low load |
| 2 | `k6/gate2-sustained.js` | Sustained latency |
| 3 | `k6/gate3-burst.js` | Burst; use multiple generators (`k6/distributed/`) |

## Prerequisites

- [k6](https://k6.io/docs/get-started/installation/)
- App at `BASE_URL` (default `http://127.0.0.1:8000`)
- Seed for authenticated journeys
- Production `/metrics` needs `METRICS_TOKEN` (`Authorization: Bearer`). Local/testing stays open when no token is configured.

## Commands

```bash
k6 run loadtests/k6/gate1-smoke.js
k6 run -e METRICS_TOKEN=your-token loadtests/k6/gate1-smoke.js
k6 run loadtests/k6/gate2-sustained.js
k6 run -e PEAK_RATE=50 loadtests/k6/gate3-burst.js

npm run loadtest:gate1
npm run loadtest:gate2
npm run loadtest:gate3
```

Record evidence (does **not** flip a 2M claim):

```bash
php artisan schoolos:record-capacity-run gate2 A --p95=700 --errors=0.3 --rps=21 --vus=20 --duration=120 --environment=staging
```
