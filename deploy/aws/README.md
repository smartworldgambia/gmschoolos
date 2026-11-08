# AWS migration sketch (Phase 7)

Justified only when measured staging/production load exceeds Laravel Cloud (or current host) limits **or** isolation/cost requires it. This is IaC documentation, not an automatic cutover.

## Target shape (Stage 4)

| Concern | AWS service (example) |
|---------|------------------------|
| App | ECS Fargate behind ALB |
| Workers | Separate ECS services per queue pool |
| DB | RDS MySQL / Aurora (primary; dedicated DBs for `dedicated_db` tier) |
| Cache/queue | ElastiCache Redis + SQS (or Redis queues) |
| Objects | S3 |
| CDN | CloudFront for public assets |
| Secrets | Secrets Manager / SSM |
| Observability | CloudWatch + existing `/health` `/ready` `/metrics` |

## Artefacts

- `terraform/main.tf` — stub modules and variables (not applied by CI)
- Cutover: [`docs/runbooks/aws-cutover.md`](../../docs/runbooks/aws-cutover.md)

## Portability rule (ADR-004)

No Laravel Cloud-only APIs in domain code. Migration is ops/config + DNS + data, not an app rewrite.
