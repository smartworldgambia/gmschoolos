# Gambia SchoolOS — Architecture Overview

**Product:** Multi-tenant School Management SaaS + LMS  
**Market:** The Gambia first; Africa and international expansion later  
**Status:** Phase 1 foundation (greenfield). Capacity for extreme scale is a designed path, not a verified claim.

## Assessment (repository start)

`C:\xampp\htdocs\gbschools` began as an empty workspace. There was no prior Laravel application, schema, or tenancy implementation to preserve. Phase 1 scaffolds a modular Laravel monolith with shared-database tenancy and an Inertia/React frontend.

## Logical architecture

```
Users / PWA clients
  → DNS and domain routing (central + school subdomains / custom domains)
  → CDN (versioned static assets)
  → Load balancer / managed ingress
  → Stateless Laravel + Inertia application instances
  → TenantContext middleware (hostname → School)
  → Domain modules (MIS, LMS, Finance, Comms, …)
  → MySQL (shared DB, school_id scoping) | Redis (cache/sessions) | Queues | Object storage
```

## Application style

- **Modular monolith** first; extract services only when measured load, isolation, or deployment independence justify it.
- **Stateless web/API instances:** sessions, cache, files, and queue state live in shared infrastructure—not local disk or in-process memory.
- **Async by default** for imports, exports, report PDFs, bulk notifications, and media processing.

## Modules (boundaries)

| Module | Responsibility |
|--------|----------------|
| Identity | Auth, roles, MFA-ready hooks, platform vs school admins |
| Tenant & subscription | Schools, domains, plans, entitlements, onboarding |
| Admissions & SIS | Students, guardians, enrolment |
| Academics | Years, terms, levels, classes, subjects, timetables |
| Attendance | Daily registers, summaries |
| Examinations | Assessments, grades, report cards |
| Finance | Fee structures, invoices, payments, reconciliation |
| LMS | Courses, lessons, assignments, quizzes, gradebook |
| External content | SCORM package metadata + LTI tool launch hooks |
| HR & payroll | Staff leave, payroll drafts/posts |
| Library | Catalogue and loans |
| Transport | Vehicles, routes, student assignments |
| Boarding | Hostels, rooms, allocations |
| Safeguarding | Confidential case records (admin-only) |
| Reporting store | Lagged `report_facts` for analytics |
| Website/CMS | Public school landing and news |
| Communications | Notices, email/SMS hooks |
| Platform ops | Health, audit, usage metrics |

## Frontend

Laravel + **Inertia.js + React + Tailwind CSS + Vite**. Design system: navy (`#1A2B4C`), white surfaces, teal (`#14B8A6`) accents. UI concept images are visual references, not pixel-perfect mandates. Reusable shells and components support desktop, tablet, and mobile breakpoints.

## Capacity note

**Two million simultaneous active users is a capacity objective.** It must not be claimed as supported until documented, repeatable load tests pass defined latency, error-rate, reliability, and cost criteria for named traffic profiles (ordinary day, peak education, extreme burst).

## Related documents

- [Scaling strategy](scaling-strategy.md)
- [Tenant isolation](tenant-isolation.md)
- [Database strategy](database-strategy.md)
- [Disaster recovery](disaster-recovery.md)
- [Cost management](cost-management.md)
- [Implementation roadmap](../implementation-roadmap.md)
- [Workload modelling](workload-modelling.md)
- [Capacity certification](capacity-certification.md)
- [Autoscaling](autoscaling.md)
- [ADRs](decisions/)
