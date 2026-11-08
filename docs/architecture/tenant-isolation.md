# Tenant Isolation

## Model (Phase 1)

**Shared database with explicit `school_id` scoping.** Platform administrators have `school_id = null` and operate only on central routes. School-owned rows always carry `school_id`.

A documented migration path to dedicated databases/clusters for high-volume tenants exists in [database-strategy.md](database-strategy.md) and ADR-001; it is not required for launch.

## Resolution

1. Resolve tenant from **verified hostname** (subdomain or mapped custom domain) or from the authenticated user’s school when on a school portal session.
2. Store the active school in `TenantContext` (request-scoped).
3. **Never trust** a client-supplied `school_id` without authorization.

## Enforcement surfaces

| Surface | Mechanism |
|---------|-----------|
| HTTP / Inertia | `IdentifyTenant` + `EnsureTenantAccess` middleware |
| Eloquent | `BelongsToSchool` trait + global scope |
| Policies | Ownership checks on ID lookups |
| Cache | Keys prefixed with `school:{id}:` |
| Storage | Paths under `schools/{id}/…` |
| Jobs | Require tenant id on job payload; refuse missing context for tenant work |
| Exports / reports | Scoped queries only |
| Search (future) | Tenant filter on every query |

## Platform vs school admin

- Central domain: SaaS marketing, school registration, platform console (production: `gambiaschoolos.com`).
- School subdomain / custom domain: public school site + authenticated school portals (`{slug}.gambiaschoolos.com`).
- Cross-tenant access attempts must 404/403; automated tests cover guessed IDs and manipulated requests.

## Noisy-neighbour controls (progressive)

Per-tenant request/API rate limits, import/export quotas, upload size limits, report concurrency caps, fair queue scheduling, usage metrics and alerts. Prefer tenant throttling before platform-wide restrictions.

## Isolation tiers (Phase 7)

| Tier | Meaning |
|------|---------|
| `shared` | Default shared DB + `school_id` |
| `premium_isolated` | Same DB; stricter quotas / partition_key tagging for future partitioning |
| `dedicated_db` | Planned/active row in `tenant_database_connections`; app uses dedicated connection only when status=`active` |

Manage via platform **Capacity** or `TenantIsolationService`. Dedicated DB cutover is an ops exercise — planning a connection does not move data by itself.

## Testing requirements

Feature tests must cover:

- Accessing another school’s student/invoice/course by ID
- Queue jobs without tenant context
- Direct file URL patterns outside tenant path
- Platform admin cannot be confused with school admin via `school_id` injection
