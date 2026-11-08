# ADR-004: Hosting portability (Laravel Cloud → AWS)

## Status

Accepted (Phase 1)

## Context

Initial production may use Laravel Cloud if limits fit; long-term high scale may move to AWS. Business logic must not depend on a single host.

## Decision

- Environment-based configuration only
- Standard Laravel filesystem, queue, cache, mail, and session abstractions
- Health/readiness HTTP endpoints
- Database via migrations; secrets outside the repo
- No Laravel Cloud-only APIs in domain code

## Consequences

- Migration is an ops/IaC exercise, not an application rewrite
- Requires a rehearsed cutover runbook before any production host change
- Some managed features (autoscaling knobs) differ by provider and must be validated per environment
