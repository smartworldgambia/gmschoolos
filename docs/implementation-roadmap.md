# Implementation Roadmap

Mapped from the master product plan to this repository.

## Phase 1 — Foundation

- [x] Repository assessment (empty → greenfield)
- [x] Architecture docs and ADRs
- [x] Laravel + Inertia/React scaffold
- [x] Shared-DB tenancy + auth/roles
- [x] Core schema + seeders
- [x] Design system
- [x] Nine priority screens with real aggregates
- [x] Tenant isolation tests + health endpoints

## Phase 2 — Core school MIS

- [x] Full admissions workflows, CSV import jobs
- [x] Timetables and room allocation
- [x] Examination publishing workflows
- [x] Expanded fee reconciliation
- [x] Parent portal hardening

## Phase 3 — LMS and communications

- [x] Lesson completion tracking, question banks, rubrics
- [x] Notifications (email/SMS providers)
- [x] Mobile PWA polish
- [x] Async import/export UX

## Phase 4 — SaaS operations

- [x] Platform console (schools, plans, domains, billing)
- [x] Quotas and usage reporting
- [x] Monitoring/alerting integration
- [x] Backup restore validation

## Phase 5 — Scale foundations

- [x] Query profiling, cache tuning
- [x] Independent worker pools
- [x] Load-test automation (k6/Gatling/Locust)
- [x] Autoscaling config and cost attribution

## Phase 6 — Advanced modules

- [x] HR/payroll, library, transport, boarding, safeguarding
- [x] Advanced reporting store
- [x] SCORM/LTI where feasible

## Phase 7 — Large-scale expansion

- [x] Workload modelling, AWS migration if justified
- [x] Partitioning / tenant isolation tiers
- [x] Distributed load tests and capacity certification

## Production hardening (post Phase 7)

- [x] Exception → `ErrorReporter` (log, `error_reports`, optional webhook, platform alerts)
- [x] Security headers middleware
- [x] TOTP MFA (enable / challenge / recovery codes / disable) without external MFA package
- [x] Platform Ops console page
- [x] Checklist + `.env.example` keys updated

**Honest capacity status:** tooling and evidence framework are in place; **2,000,000 concurrent users remain uncertified.**

Security, tenant isolation, and observability are not deferred—they begin in Phase 1 and deepen each phase.
