# Production hardening

Shipped after Phase 7 as operational close-out (not a capacity claim).

## Error reporting

- Service: `App\Services\Ops\ErrorReporter`
- Hook: `bootstrap/app.php` `reportable` callback
- Storage: `error_reports` (fingerprint dedupe + occurrence count)
- Optional: `SCHOOLOS_ERRORS_WEBHOOK_URL` (Slack/Discord-style POST)
- Optional: raise `platform_alerts` on first sight of a fingerprint
- UI: Platform → Ops (`/platform/ops`)

## MFA (TOTP)

- Pure PHP helper: `App\Support\Totp` (RFC 6238, SHA1, 30s, 6 digits)
- Users enable via Profile → Manage two-factor (`/user/two-factor`)
- Login redirects to `/two-factor-challenge` when confirmed
- Middleware `EnsureTwoFactorVerified` gates authenticated routes until session flag `auth.two_factor_passed`
- Recovery codes stored encrypted; single-use

## Security headers

- Middleware `SecurityHeaders` on the web stack
- Toggle: `SCHOOLOS_SECURITY_HEADERS`
- HSTS only when `SCHOOLOS_SECURITY_HSTS=true` **and** the request is HTTPS

## Production env reminders

1. Set `APP_DEBUG=false`, strong `APP_KEY`, HTTPS URL
2. Prefer Redis for cache/session/queue
3. Set `SCHOOLOS_ERRORS_WEBHOOK_URL` (or external APM) for on-call visibility
4. Enable `SCHOOLOS_SECURITY_HSTS` behind TLS terminators
5. Encourage platform/school admins to enable MFA
