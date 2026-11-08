# ADR-003: Queue and cache drivers

## Status

Accepted (Phase 1)

## Context

Stateless scaling requires shared session/cache and async work for imports, PDFs, and notifications. Local XAMPP may not run Redis.

## Decision

- Configure Laravel for **Redis** as the preferred cache, session, and queue backend in production.
- Allow **database** or **sync**/`file` drivers for local development via `.env`.
- Never use in-process-only queues or local disk as the system of record for jobs or sessions in production.

## Consequences

- Portable between Laravel Cloud and AWS (ElastiCache / SQS-compatible via Laravel)
- Local DX remains simple on XAMPP
- Production checklist must verify Redis/queue workers before launch
