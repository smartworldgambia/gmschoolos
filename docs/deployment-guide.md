# Deployment Guide

**Status: Implemented as a production checklist (Phase 11).** GitHub Actions (`.github/workflows/ci.yml`) runs PHPUnit, i18n gates and `npm run build` when the repo is on GitHub. This XAMPP environment has no git remote, so that workflow has **not** been executed on GitHub.

**Honest capacity:** 2,000,000 concurrent users are **not certified**. k6 scripts exist; they are evidence only when actually run against staging.

**Honest integrations:** Wave/Stripe SaaS and live mail/SMS remain **Requires External Configuration, NOT Verified**. Billing is annual-only.

## Production cutover (every release)

1. Back up the database **and** `APP_KEY`. Stored integration secrets are encrypted with the key.
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && NODE_OPTIONS=--max-old-space-size=4096 npm run build`
4. `php artisan migrate --force` then `php artisan schoolos:sync-rbac`
5. Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SCHOOLOS_FORCE_HTTPS=true`, HSTS on, `SCHOOLOS_CSP_MODE=enforce`, `SCHOOLOS_REQUIRE_2FA_PRIVILEGED` left on, a real `MAIL_*` driver, Redis for cache/session/queue if available. **Platform mail:** on Laravel Cloud set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT=587`, `MAIL_USERNAME`, `MAIL_PASSWORD` (or `MAIL_URL`). `MAIL_FROM_ADDRESS` defaults to `noreply@gambiaschoolos.com`. Platform → Integrations is prefilled from that identity; save, test, and switch on to overlay env. Without SMTP credentials, invitations are only written to the log.
6. Set `SCHOOLOS_METRICS_TOKEN` (long random) and/or `SCHOOLOS_METRICS_ALLOW_IPS`. Production **refuses** `/metrics` when both are empty. Probes send `Authorization: Bearer <token>` or `X-Metrics-Token`. `/health`, `/ready` and `/up` stay public for the load balancer.
7. Scheduler: `* * * * * php artisan schedule:run`. Queue workers: `deploy/workers/supervisord.conf` (critical, default, imports, exports, notifications, reports).
8. `php artisan config:cache` and `php artisan route:cache` after env is correct. `php artisan config:clear` before the next env change.
9. Smoke: `/health` 200, `/ready` 200, `/metrics` 401/403 without the token and 200 with it, login, one school admin page in EN/FR/AR.
10. Rollback: restore the previous `public/build`, application code, and database backup. Do not run down-migrations unless a release note says they are safe.

Phase-specific additive steps follow. Each still applies on first deploy of that phase.

## Phase 1 — security baseline


1. `composer install --no-dev --optimize-autoloader`, `npm ci && NODE_OPTIONS=--max-old-space-size=4096 npm run build`.
2. `php artisan migrate --force` (back up the database first; Phase 1 added no destructive migrations).
3. `php artisan schoolos:sync-rbac` — creates/updates roles and permissions. **Required**, otherwise `permission:` routes return 403.
4. Verify existing users who must sign in: `php artisan schoolos:verify-email user@example.com` (until real mail is configured).
5. Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SCHOOLOS_FORCE_HTTPS=true`, HSTS on, a real mail driver, `SCHOOLOS_CSP_MODE=enforce`,
   `SCHOOLOS_REQUIRE_2FA_PRIVILEGED` left at default (on). Never commit secrets.
6. Run queue workers with `php artisan queue:work` (workers stay tenant fail-closed; jobs bind their own school).
7. Optionally roll CSP out with `SCHOOLOS_CSP_MODE=report-only` plus `SCHOOLOS_CSP_REPORT_URI` first.


## Phase 2 — billing deployment steps

1. **Back up the database first** (production migrations are not run without a backup and approval).
2. `php artisan migrate --force` (adds billing tables; backfills existing subscriptions: trialing→trial, past_due→expired, cycle→annual; adds a one-subscription-per-school unique index when no duplicates exist).
3. `php artisan schoolos:sync-rbac` (renames `platform.plans.manage` to `platform.pricing.manage`, adds finance permissions).
4. Set the **approved** annual price for every school type at *Platform > Pricing* (seeded values are placeholders). Run `php artisan db:seed --class=SchoolTypePriceSeeder` only on fresh installs.
5. Ensure the scheduler runs: cron `* * * * * php artisan schedule:run` (`schoolos:billing:process` is hourly). Dry run: `php artisan schoolos:billing:process --dry-run`.
6. Ensure a queue worker processes the `notifications` queue and a real mail driver is configured (reminder e-mails).
7. Environment (all optional): `SCHOOLOS_TRIAL_DAYS`, `SCHOOLOS_BILLING_CURRENCY`, `SCHOOLOS_TRIAL_REMINDER_DAYS`, `SCHOOLOS_PAID_REMINDER_DAYS`, `SCHOOLOS_GRACE_DAYS`, `SCHOOLOS_PAY_*`.
8. `npm run build` (with `NODE_OPTIONS=--max-old-space-size=4096` on small machines).


## Phase 3 - payment deployment steps

1. **Back up the database first**, then `php artisan migrate --force` (additive: new columns on `billing_payments` / `billing_events`, tables `billing_webhook_events`, `platform_bank_accounts`).
2. `php artisan schoolos:sync-rbac` (adds `platform.payments.configure`).
3. Scheduler: `* * * * * php artisan schedule:run` also runs `schoolos:billing:reconcile` every 15 minutes (dry run: `php artisan schoolos:billing:reconcile --dry-run`).
4. Evidence storage: keep `SCHOOLOS_EVIDENCE_DISK` on a **private** disk (never under `public/`), encrypted and backed up.
5. Publish at least one bank account at *Platform > Payments > Payment settings*; set `SCHOOLOS_CASH_OFFICE` / `SCHOOLOS_CASH_INSTRUCTIONS`.
6. Gateways (only when approved): set the env names listed in `.env.example` (`STRIPE_*`, `WAVE_*`, `SCHOOLOS_STRIPE_*`, `SCHOOLOS_WAVE_*`, `SCHOOLOS_PAY_*`), register the webhook endpoints `https://<host>/webhooks/stripe` and `https://<host>/webhooks/wave`, run `php artisan config:clear`/`config:cache`. Start with **test/sandbox keys**.
7. The application must be reachable over public HTTPS for provider webhooks; use a tunnel locally.
8. After the first sandbox payment per gateway, check *Webhook deliveries* on the payment and run reconciliation before recording the integration as Verified.
9. `npm run build`.

## Phase 4 - onboarding deployment steps

1. **Back up the database first**, then `php artisan migrate --force` (additive: new columns on `schools`; tables `departments`, `school_setup_steps`, `school_registration_reviews`, `staff_invitations`).
2. Optional env: `SCHOOLOS_TERMS_VERSION` (recorded with each registration; change it when the legal text changes) and `SCHOOLOS_INVITATION_TTL_DAYS` (default 7).
3. Review and publish the Terms and Privacy pages (`/terms`, `/privacy`) before opening registration; the form links to them.
4. Configure outgoing mail (welcome and invitation emails) and a queue worker if mail is queued.
5. Make the platform team responsible for *Platform > Registrations* (open reviews also raise a Platform alert).
6. Existing schools are unaffected: the wizard appears only for schools registered through it with setup incomplete.
7. `npm run build`.

## Phase 5 - academic deployment steps

1. Deploy the code and run `php artisan migrate --force` (migration `2026_11_05_100000_create_phase5_academic_student_management`; back up the database first).
2. Optional environment keys (defaults shown in `.env.example`): `SCHOOLOS_STUDENT_PREFIX` (STU), `SCHOOLOS_STUDENT_DOC_MAX_KB` (5120), `SCHOOLOS_STUDENT_DOC_DISK` (`local`, keep it private), `SCHOOLOS_ATTENDANCE_EDIT_DAYS` (1).
3. Run `npm ci && npm run build` and redeploy `public/build`.
4. A **queue worker is required** for CSV import and guardian attendance alerts (`QUEUE_CONNECTION=database`; run `php artisan queue:work`). Without one, imports stay "queued".
5. Configure mail (`MAIL_*`) so guardian alerts and admission acknowledgements can be sent. There is no SMS gateway.
6. Ensure `storage/app` (private disk) is writable and **not** web-served; student documents and photos live there.
7. Existing schools: run `php artisan db:seed --class=RolesAndPlansSeeder` (or your usual permission sync) so the new `attendance.*`, `exams.*` and `results.publish` permissions reach existing roles.
8. Each school must set up its academic calendar (year and terms), levels and classes, curriculum and teacher allocation, periods, and (optionally) its assessment categories and grading scale before attendance, timetable and results are usable. Nothing is pre-filled with fixed dates.
9. Smoke test after deploy: register a student, take a daily register, add and publish a lesson, enter marks, submit, approve and publish an examination, in EN, FR and AR.

## Phase 6 - external exams, Senior Secondary and Records Office deployment steps

1. Back up the database, deploy the code, run `php artisan migrate --force` (migration `2026_11_06_100000_create_phase6_external_exams_records`, 18 new tables).
2. Optional environment keys (defaults in `.env.example`): `SCHOOLOS_EXTERNAL_IMPORT_MAX_ROWS` (5000), `SCHOOLOS_EXTERNAL_IMPORT_MAX_KB` (2048), `SCHOOLOS_COLLECTION_REMINDER_DAYS` (14), `SCHOOLOS_COLLECTION_REMINDER_MAX` (6), `SCHOOLOS_RECORDS_DISK` (`local`, keep private), `SCHOOLOS_RECORDS_STAMP_MAX_KB` (1024), `SCHOOLOS_RECORDS_VERIFY_THROTTLE` (`60,1`).
3. `composer install --no-dev` (adds `dompdf/dompdf` and `chillerlan/php-qrcode`; PHP `gd` and `mbstring` are required), then `npm ci && npm run build`.
4. Sync permissions for existing schools: `php artisan db:seed --class=RolesAndPlansSeeder` (new `external_exams.*`, `senior_admissions.*`, `records.*`).
5. A **queue worker** (CSV result import, notifications) and the **scheduler** (`* * * * * php artisan schedule:run`, which runs `schoolos:records:remind` daily at 07:30) must be running. Configure `MAIL_*` for reminders.
6. `storage/app` (private disk) must be writable and not web-served: candidate documents, signatory stamps and issued documents live there.
7. Each school: map levels to Grade 9 / Grade 12, add exam subjects, set Records settings (signatories, stamp, contact details), create a Senior Secondary round with its own rules, and review one issued PDF before use.
8. The middleware priority change in `bootstrap/app.php` is required (tenant is bound before route-model binding); do not remove it.
9. Smoke test: register a candidate, record an index number, import and verify results, publish, record a collection, create a Senior Secondary round and a test application, issue a transcript, open its PDF and scan the QR code to `/verify/{token}`, in EN, FR and AR.

## Phase 7 - LMS deployment steps

1. Back up the database, deploy the code, run `php artisan migrate --force` (migration `2026_11_07_100000_create_phase7_lms`: 13 new tables and new columns on `courses`, `course_sections`, `lessons`, `assignments`, `quizzes`, `quiz_questions`, `quiz_attempts`, `quiz_attempt_answers`). Existing legacy courses keep working.
2. Optional environment keys (defaults in `.env.example`): `SCHOOLOS_LMS_DISK` (`local`, keep private), `SCHOOLOS_LMS_FILE_MAX_KB` (10240), `SCHOOLOS_LMS_MAX_SUBMISSION_FILES` (5), `SCHOOLOS_LMS_INACTIVE_DAYS` (14). Raise PHP `upload_max_filesize` and `post_max_size` if larger files are needed.
3. `npm ci && npm run build`.
4. Sync permissions for existing schools: `php artisan db:seed --class=RolesAndPlansSeeder` (new or changed `lms.view`, `lms.teach`, `lms.manage`).
5. The **scheduler** (`* * * * * php artisan schedule:run`) must be running: `schoolos:lms:announce` runs every 10 minutes for scheduled announcements. Configure `MAIL_*` if announcement emails are wanted; delivery is not verified by this release.
6. `storage/app` must be writable and not web-served: LMS files and submissions live on the private disk.
7. The middleware priority in `bootstrap/app.php` (tenant bound before route-model binding) is required; do not remove it.
8. Each school: create courses (teachers need a subject allocation), enrol learners or sync from a class, publish lessons, and review one quiz end to end before use.
9. Smoke test: create a course, publish a lesson, set an assignment, submit as a learner and grade it, create a quiz with a time limit, take it as a learner, grade the essay, release marks, open the gradebook and progress pages, in EN, FR and AR.
10. Do not advertise SCORM or LTI support; it does not exist in this release.

## Phase 8 - portals and communication deployment steps

1. Back up the database, deploy the code, run `php artisan migrate --force` (migration `2026_11_08_100000_create_phase8_portals_communication`: 11 new tables, new columns on `users` and `staff_invitations`).
2. Sync permissions for existing schools: `php artisan schoolos:sync-rbac` (new `messages.use`, `messages.moderate`, `messages.office`, `announcements.manage`, `announcements.class`, `communications.manage`).
3. `npm ci && npm run build`.
4. Environment keys (defaults in `.env.example`): `SCHOOLOS_SMS_DRIVER` (`log` by default), `SCHOOLOS_SMS_COUNTRY_CODE`, `SCHOOLOS_SMS_DAILY_CAP`, `SCHOOLOS_SMS_MAX_ATTEMPTS`; Twilio: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM` or `TWILIO_MESSAGING_SERVICE_SID`, `TWILIO_STATUS_CALLBACK`; Africa's Talking: `AT_USERNAME`, `AT_API_KEY`, `AT_SENDER_ID`, `AT_SANDBOX`, `AT_WEBHOOK_SECRET`; messaging: `SCHOOLOS_MESSAGING_DISK`, `SCHOOLOS_MESSAGING_ATTACHMENT_KB`, `SCHOOLOS_MESSAGING_MAX_ATTACHMENTS`, `SCHOOLOS_MESSAGING_STUDENTS_START`.
5. **Mail:** configure `MAIL_*` (invitations, resets, announcements and alerts depend on it) and run a queue worker for the `notifications` queue. Send yourself an invitation and a password reset and confirm they arrive before announcing e-mail as working.
6. **SMS (optional, per school):** choose a driver, set its credentials and restart workers. Give the provider the delivery-report URLs shown on **Communications -> Settings** (Twilio: set `TWILIO_STATUS_CALLBACK` to the exact public URL of `/webhooks/sms/twilio`; Africa's Talking: `/webhooks/sms/africastalking/{AT_WEBHOOK_SECRET}` with a long random secret). Then in each school switch SMS on, set a daily cap, and use **Send test** to a phone you can check. Only when it arrives is SMS verified.
7. The **scheduler** (`* * * * * php artisan schedule:run`) must run: `schoolos:announcements:publish` publishes scheduled announcements every minute.
8. `storage/app` must be writable and not web-served: message attachments live on the private disk. The webhook routes are public and stateless; keep `throttle:600,1` and HTTPS.
9. The middleware priority in `bootstrap/app.php` (tenant bound before route-model binding) is required; do not remove it.
10. Each school: invite staff, learners and guardians from **User accounts** (check Settings -> parent access), publish a test announcement, and exchange a test message between a teacher and a parent.
11. Smoke test in EN, FR and AR: invite and accept a teacher, a learner and a guardian; suspend and reinstate one; a teacher announces to their class; a parent sees only their child; a parent reports a message and a headteacher moderates it; notification preferences save; Communications shows the true provider state.
12. Do not state that SMS or e-mail delivery is verified until the steps above have been done with real providers.

## Phase 8 follow-up - entering mail and SMS credentials in the platform console

1. No migration is needed; run `php artisan schoolos:sync-rbac` once so `platform.integrations.configure` exists, and rebuild assets (`npm ci && npm run build`).
2. **Back up `APP_KEY`** together with the database. Stored credentials are encrypted with it; if it changes they become unreadable and must be entered again.
3. Sign in as a **platform administrator** (not finance/support/operations/auditor) and open **Integrations** (`/platform/integrations`). Keep working `MAIL_*` in `.env` as the fallback until the console settings are verified. From address/name, port 587 and STARTTLS are prefilled from `config/schoolos.php`.
4. Mail: confirm the prefilled from address (`noreply@gambiaschoolos.com`), enter host, username and password (STARTTLS 587 or SSL 465); enter your own password; **Save**; **Send test email** to an inbox you control; check the message arrived; **Switch on**; press **I received it**. Then send a real invitation and a password reset and confirm they arrive.
5. SMS: choose Twilio or Africa's Talking, enter the credentials, save, **Send test SMS** to a phone you hold, confirm it arrived, switch on, confirm. Copy the delivery-report URL shown on the card into the provider dashboard (Africa's Talking: replace `<secret>` with your webhook path secret). Each school then switches SMS on itself in Communications.
6. Queue workers: the console value is read before every job and the mail transport is rebuilt when it changes, so a restart is not required; a restart is still harmless. The shared cache (`CACHE_STORE`) must be reachable by web and workers (file and database stores are; `array` is not).
7. To go back to `.env` at any time: **Switch off** (keeps the values) or **Remove saved settings**.
8. Production: keep 2FA required for privileged roles (`schoolos.security.require_two_factor_privileged`), keep the number of platform administrators small, and watch the "Recent changes" list and the notice e-mails.

## Phase 9 - finance and operations deployment steps

1. Back up the database, deploy the code, run `php artisan migrate --force` (migrations `2026_11_09_100000_create_phase9_finance`, `110000` HR/payroll, `120000` library/inventory/procurement, `130000` transport/boarding/welfare/events).
2. `php artisan schoolos:sync-rbac` so `fees.approve`, `payroll.approve`, `procurement.*`, `events.*`, `staff.self`, `inventory.manage` and the existing library/transport/boarding/safeguarding permissions exist on roles.
3. `npm ci && npm run build`.
4. School fees are configured per school under **Fees → Setup** (methods, due days, reminders). This is **not** the platform Integrations console and not SaaS Wave/Stripe.
5. Scheduler: `schoolos:fees:remind` for overdue school invoices. Queue worker still required for in-app/e-mail notices (low stock, safeguarding, events, procurement).
6. Each school: add fee categories/structures; confirm a second person holds `fees.approve` / `procurement.approve` / `payroll.approve` if four-eyes is wanted (self-approval stays off). Nominate safeguarding officers (`safeguarding.manage`); every teacher with `safeguarding.report` can file a concern but cannot open the case list.
7. Smoke test in EN, FR and AR: issue an invoice and record a cash payment; create a stock item and a receipt; submit and approve a purchase request as two people; assign a rider and a boarder; file a safeguarding concern as a teacher (403 on the case list); publish an event.
8. Do not tell schools that Wave/Stripe SaaS or e-mail/SMS delivery works until those have been verified with real providers (still outstanding from Phases 3 and 8).

## Phase 10 - leftover i18n

1. `npm ci && npm run build` so dashboard, profile, reports, exports and platform chrome pick up EN/FR/AR catalogs.
2. No migration. Confirm the language switcher on `/admin` and `/platform`.

## Phase 11 - CI and metrics

1. Set `SCHOOLOS_METRICS_TOKEN` and/or `SCHOOLOS_METRICS_ALLOW_IPS` **before** production traffic. Without them, `/metrics` returns 403.
2. Point autoscalers at `GET /metrics` with `Authorization: Bearer <token>` (not a query string).
3. Copy `.github/workflows/ci.yml` with the repo when git is available. This environment cannot push to GitHub.
4. Do not flip `schoolos.capacity.two_million_certified`. k6 Gate 1–3 must run on separate load generators against staging first; passing a gate records evidence only.
5. `npm ci && npm run build`.
