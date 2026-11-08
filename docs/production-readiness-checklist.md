# Production Readiness Checklist

Legend: `[ ]` not done · `[~]` partial · `[x]` done for current phase · `N/A` not required yet

## Application

- [x] Migrations apply cleanly on empty DB
- [x] Seeders produce demo school without hard-coded dashboard constants in UI code
- [x] Tenant isolation feature tests pass
- [x] Auth: school admin, teacher, student roles enforced (Spatie roles)
- [x] Health and readiness endpoints respond
- [x] `.env.example` documents required keys
- [x] `npm run build` succeeds
- [x] No secrets committed

## Security

- [x] CSRF enabled for web
- [~] Policies on student/fee/LMS records (tenant global scope + auth; dedicated policies expand in Phase 2)
- [~] Private disk for student documents (path convention documented; uploads limited)
- [x] Rate limiting on school registration
- [x] Audit log for sensitive admin actions (basic `activity_logs` in Phase 1)
- [x] Security headers middleware (`X-Frame-Options`, `nosniff`, Referrer-Policy; optional HSTS via env)
- [x] MFA (TOTP) enable/challenge/disable hooks + profile settings UI

## Operations

- [x] Queue worker pools documented (`deploy/workers/supervisord.conf`; Redis preferred in prod)
- [~] Backup/restore runbook exists; `schoolos:validate-backup` records runs (full staging drill still required)
- [x] Platform console for schools/plans/domains/billing + quotas/usage snapshots
- [x] Platform alerts + monitoring + Scale/cost page
- [x] Relative cost attribution (`schoolos:cost-report`) + budget alert hook
- [x] Error reporting (DB fingerprints + log + optional webhook + platform alerts; set `SCHOOLOS_ERRORS_WEBHOOK_URL` in prod)
- [x] Platform Ops page (`/platform/ops`) surfaces reporter + security status

## Scalability (honest)

- [x] Stateless session/cache drivers selectable via env
- [x] Autoscaling **configuration sketches** + `/metrics` (token/IP in production; host wiring still required)
- [x] Load tests Gate 1–3 scripts (k6) + capacity evidence recording
- [x] Workload modelling + tenant isolation tiers
- [x] AWS migration sketch / cutover runbook (apply only if justified)
- [x] Query profiling + public/dashboard cache TTLs
- [x] **2M concurrent users: NOT CLAIMED / NOT CERTIFIED**
- [x] GitHub Actions workflow (`.github/workflows/ci.yml`) — **not executed on GitHub** in this environment (no git remote)
- [x] `/metrics` fail-closed in production without token/IP allow-list

## Phase 1 UI

- [x] SaaS landing
- [x] School public landing
- [x] Registration wizard
- [x] School admin dashboard (DB aggregates)
- [x] LMS course interface
- [x] Student and teacher dashboards
- [x] Student profile and results
- [x] Fees and payments
- [x] Class and academic management

Update this file as items land; do not mark stubs complete.
