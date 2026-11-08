# ADR-002: Inertia.js + React + Tailwind

## Status

Accepted (Phase 1)

## Context

UI concepts describe a premium education SaaS with complex dashboards, wizards, and mobile shells. The team needs server-driven auth/session security with a modern component UI.

## Decision

Use **Laravel Breeze (Inertia React)**, **React**, **Tailwind CSS**, and **Vite**. Build a shared design system (navy / white / teal) and reusable shells rather than pixel-copying mockups.

## Consequences

- One deployable app (no separate SPA auth stack)
- React components map cleanly to dashboards and LMS views
- Requires Node for asset builds; CI must run `npm run build`
