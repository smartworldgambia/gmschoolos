# ADR-001: Shared-database tenancy with school_id scoping

## Status

Accepted (Phase 1)

## Context

Gambia SchoolOS needs multi-tenant isolation for many schools, affordable launch cost, and a path to dedicated databases for large tenants. Installing a separate application per school is rejected.

## Decision

Use a **shared MySQL database** where tenant-owned rows include `school_id`. Resolve tenant via hostname/`TenantContext`. Enforce with middleware, Eloquent global scopes, policies, and tenant-prefixed cache/storage keys.

## Consequences

- Simple ops and migrations at launch
- Strong need for automated cross-tenant tests
- High-volume tenants may later move to dedicated DBs without rewriting business modules if repository/query boundaries stay school-scoped
