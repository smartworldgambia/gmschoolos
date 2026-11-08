# Database Strategy

## Engine

Supported relational database (MySQL/MariaDB on XAMPP for local; managed MySQL/Postgres-compatible on production as configured).

## Phase 1 schema approach

- Single shared database.
- Tenant-owned tables include `school_id` (indexed, often composite with business keys).
- Paginate all list endpoints; never load full academic histories into memory.
- Prefer eager loading; monitor N+1.
- Short transactions; avoid long locks on fee and grade writes.

## Core entity groups

1. **Tenancy:** schools, domains, subscription_plans, school_subscriptions  
2. **Identity:** users, roles/permissions (Spatie)  
3. **Academics:** academic_years, terms, levels, classes, streams, subjects  
4. **People:** students, guardians, student_guardians, staff  
5. **Operations:** attendance_records, fee_items, invoices, invoice_items, payments  
6. **LMS:** courses, course_sections, lessons, assignments, quizzes, grades  
7. **Advanced ops:** staff leave, payroll, library, transport, boarding, safeguarding  
8. **Reporting store:** `report_facts` (refreshed asynchronously; not OLTP)  
9. **External content:** `lti_tools`, `scorm_packages` (+ lesson launch FKs)  
10. **Audit:** activity_logs  

## Growth path

| Trigger (examples) | Action |
|--------------------|--------|
| Slow tenant-scoped queries | Indexes, query rewrite, caching of safe summaries |
| Read-heavy public sites | CDN + cache; read replica if needed |
| Single tenant dominates write I/O | Dedicated DB / cluster for that tenant |
| Cross-tenant analytics harm OLTP | Separate reporting store with defined lag |

Do **not** shard at launch. Partition or move tenants only with measured volume, write throughput, and operational runbooks.

## Imports / exports

1. Upload to object storage  
2. Validate format/size  
3. Process asynchronously in chunks  
4. Surface progress and row errors  
5. Idempotent matching rules; downloadable error report  

Exports: async generation + time-limited download links.

## Migrations

Backward-compatible changes preferred. Expand → migrate → contract. Test rollbacks in non-production before production apply.
