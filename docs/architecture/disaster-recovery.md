# Disaster Recovery

## Objectives (initial targets — unvalidated until restore drills pass)

| Metric | Initial target | Status |
|--------|----------------|--------|
| RPO | ≤ 24 hours (daily backups); aim for PITR when provider supports it | Not yet proven in production |
| RTO | ≤ 8 hours for full restore of DB + critical files | Not yet proven |

Do not promise tighter RPO/RTO until restore procedures are rehearsed and timed.

## What must be recoverable

- Relational database (schools, students, grades, fees, LMS progress)
- Object storage (documents, logos, learning resources, exports)
- Secrets and environment configuration (from secure store, not from backups alone)
- DNS / domain mappings inventory

## Controls

- Automated database backups with encryption at rest via platform services
- Backup success monitoring and alerting
- Separate credentials for backup access where possible
- Object-storage versioning/lifecycle for critical buckets when enabled
- Documented restore runbook: [restore-drill.md](../runbooks/restore-drill.md)

## Multi-region

Active-active multi-region is **not** required for first release. Design portability (env config, migrations, S3-compatible storage) so regional failover can be added when business case and ops maturity justify cost.

## Incident communications

See [incident-response.md](../runbooks/incident-response.md). After restore: integrity checks on sample tenants (student counts, recent payments, latest grade writes) before declaring recovery complete.
