# Runbook: AWS cutover (if justified)

## Preconditions

- [ ] Measured need documented (latency, connection budget, or host limits)
- [ ] Staging on AWS mirrors prod config (ADR-004 abstractions)
- [ ] Gate 2 evidence recorded for Profile A on AWS staging
- [ ] Backup + restore drill succeeded against RDS
- [ ] Rollback DNS TTL lowered; named approver for go/no-go

## Cutover outline

1. Freeze noncritical writes (or enter maintenance window)
2. Final DB dump / replica promotion to RDS
3. Point app/worker ECS tasks at new secrets + Redis/SQS
4. Smoke: `/health`, `/ready`, `/metrics`, login, fee payment dry-run
5. Flip DNS / load balancer
6. Watch p95, error rate, queue depth for 60–120 minutes
7. Keep previous host warm for rollback window

## Abort

If error rate or p95 exceeds Gate 2 thresholds for 10 continuous minutes, revert DNS and restore prior host. Record a failed `capacity_certification_runs` note.
