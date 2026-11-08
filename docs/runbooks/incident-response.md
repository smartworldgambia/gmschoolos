# Runbook: Incident Response

## Severity (initial)

| Level | Example | Response |
|-------|---------|----------|
| SEV1 | Data loss, cross-tenant leak, payments down | Page on-call; mitigate; notify affected schools |
| SEV2 | Elevated 5xx, queue backlog, login failures | Investigate within 30m; status update |
| SEV3 | Single-tenant degradation | Tenant throttle; schedule fix |

## Steps

1. Confirm blast radius (platform vs one school)  
2. Preserve evidence (request ids, logs)—never log PII unnecessarily  
3. Mitigate (rollback deploy, disable noncritical jobs, rate-limit tenant)  
4. Communicate status to stakeholders  
5. Post-incident review within 5 business days  

## Escalation

Engineering lead → product owner → school communications (for SEV1 customer impact).
