# Distributed load generators

For Gate 3+, run the same script from **multiple** load-generator hosts so the generators are not the bottleneck.

## Pattern

1. Point all generators at the same staging `BASE_URL`
2. Split arrival rate across N generators (e.g. peak 50 RPS → 2×25)
3. Aggregate k6 JSON summaries (or Cloud/k6 outputs)
4. Record the combined evidence:

```bash
php artisan schoolos:record-capacity-run gate3 B \
  --p95=900 --errors=0.5 --rps=48 --vus=80 --duration=120 \
  --environment=staging --notes="2 generators × 25 RPS"
```

## Honesty

Distributed Gate 3 evidence improves confidence for Profile B. It does **not** certify 2,000,000 concurrent users. Gate 6–7 in the load-testing plan remain future work with dedicated capacity windows.
