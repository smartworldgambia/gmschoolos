# Gambia SchoolOS

Multi-tenant School Management SaaS + LMS for The Gambia (Laravel 12, Inertia React, Tailwind).

**Capacity note:** Architecture documents a path toward large scale. The platform does **not** claim support for 2,000,000 simultaneous users until load tests document that capacity.

## Requirements

- PHP 8.2+
- Composer 2
- Node.js 20+ (LTS)
- SQLite (default local) or MySQL (XAMPP)

## Setup

```bash
composer install
copy .env.example .env   # Windows: copy .env.example .env
php artisan key:generate
# Ensure database/database.sqlite exists when using sqlite
php artisan migrate --seed
npm install
npm run build            # or npm run dev
php artisan serve
```

Open `http://localhost:8000`.

Architecture docs: [`docs/architecture/overview.md`](docs/architecture/overview.md)  
Roadmap: [`docs/implementation-roadmap.md`](docs/implementation-roadmap.md)

## Demo accounts (after seed)

| Role | Email | Password |
|------|-------|----------|
| School admin | `admin@brightfuture.test` | `password` |
| Teacher | `teacher@brightfuture.test` | `password` |
| Student | `student@brightfuture.test` | `password` |
| Platform admin | `platform@schoolos.test` | `password` |
| Parent | `parent@brightfuture.test` | `password` |

Demo school public site: `/sites/brightfuture`

## Key URLs

- `/` — Premium SaaS marketing landing (nav, hero, features, pricing from DB plans)
- `/register-school` — school onboarding wizard (supports `?plan={id}`)
- `/sites/{slug}` — registered school public site (e.g. `/sites/brightfuture`)
- `/admin` — school admin dashboard (live aggregates)
- `/teacher`, `/student` — role dashboards
- `/students`, `/fees`, `/academics`, `/lms/courses`
- `/health`, `/ready` — health checks
- `/metrics` — queue pools + autoscaling hints (JSON). Public in local/testing when no token is set; **fail-closed in production** without `SCHOOLOS_METRICS_TOKEN` or `SCHOOLOS_METRICS_ALLOW_IPS`. Header: `Authorization: Bearer …` or `X-Metrics-Token`.
- `/platform` — platform console (ops admin)

## Configuration

- `SCHOOLOS_CENTRAL_DOMAINS` — hosts for the SaaS marketing app (production: `gmschoolos.edu.gm`)
- `SCHOOLOS_BASE_DOMAIN` — base domain for `{slug}.{base}` school hosts (production: `gmschoolos.edu.gm` → `{slug}.gmschoolos.edu.gm`)
- Prefer Redis for cache/session/queue in production (see ADR-003)
- Worker pools / autoscaling caps — see `config/schoolos.php` and `deploy/`

## Tests

```bash
php artisan test
```

Load tests (Gate 1, requires [k6](https://k6.io/)): see [`loadtests/README.md`](loadtests/README.md).

## Phase status

**Phases 1–7** are implemented for current scope, including workload modelling, isolation tiers, AWS cutover sketches, and capacity-evidence recording.

**2M concurrent users remain uncertified.** SCORM/LTI are launch hooks only — not a full runtime.
