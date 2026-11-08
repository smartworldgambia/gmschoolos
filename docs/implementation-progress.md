# Gambia SchoolOS — Implementation Progress

Status labels: Not Started / In Progress / Implemented / Tested / Verified / Blocked / Requires External Configuration.
"Verified" is only used where there is reproducible evidence recorded here.

| Phase | Scope | Status |
|---|---|---|
| 0 | Repository audit, baseline tests, vulnerability reproduction | **Verified** (evidence below) |
| 1 | Critical security remediation | **Verified** locally (automated tests + browser check); see "Phase 1 outstanding" |
| 2 | SaaS subscription & commercial core: annual-only billing, school-type pricing, 30-day trial, reminders, expiry/suspension/reactivation, billing dashboards | **Tested** locally (35 new tests + browser check); see "Phase 2 outstanding" |
| 3 | Payment gateways: cash, bank-transfer evidence workflow, Wave, Stripe, receipts, reconciliation | **Tested** locally (55 new tests + browser check). Cash/bank: Implemented + Tested. Wave/Stripe: Tested against *faked* provider HTTP and locally signed webhooks only — **Requires External Configuration, NOT Verified**; see "Phase 3 outstanding" |
| 4–8 | Registration, academics, LMS, portals, communication | **Tested** (see sections below) |
| 9 | Finance and other school modules (fees, HR, library, inventory, procurement, transport, boarding, safeguarding, events) | **Tested** locally (117 Phase 9 tests; full suite **698 passed**; browser EN/FR/AR); see "Phase 9 outstanding" |
| 10 | Remaining i18n of historical modules (admin/home dashboards, reports, exports, profile/2FA, platform console chrome) | **Tested** locally (4 Phase 10 tests; full suite **702 passed**; i18n gate; browser EN/FR/AR); see "Phase 10 outstanding" |
| 11 | Scale, CI/CD (metrics lock, GitHub Actions, deployment guide) | **Tested** locally (full suite **710 passed**; i18n gates; browser EN/FR/AR); GitHub has **not** run the workflow; 2M users **not certified**; see "Phase 11 outstanding" |

## Phase 0 — evidence

* Baseline on unmodified code: **124 tests passing** (existing suite).
* The 24 Phase 1 security acceptance tests were written first and run against the **unmodified** code:
  **14 of 24 failed**, reproducing these vulnerabilities (output saved in `storage/logs/phase1-baseline.txt`):
  1. Open `/register` created unrestricted users.
  2. Tenant scope silently returned **all schools' rows** whenever no tenant was bound (not fail-closed).
  3. Users without a school reached tenant routes (HTTP 200).
  4. Students/parents reached admin/teacher pages (authorisation was ad-hoc role-name checks in some controllers, absent in routes).
  5. Roles named in the master prompt (e.g. `finance_officer`) did not exist.
  6. Suspended schools and expired trials kept full access.
  7. Registered users were not email-verified-gated; school admins were auto-verified.
  8. No Content-Security-Policy header.
* Git is **not installed** in this environment, so there is no repository history/CI (see `known-limitations.md`).

## Phase 1 — what changed

| Area | Change | Status |
|---|---|---|
| Open registration | `/register` no longer creates users; it redirects to `/register-school`. `RegisteredUserController` and `Auth/Register.jsx` removed. | Tested |
| Tenant isolation | `TenantContext`, `SchoolScope`, `BelongsToSchool` are **fail-closed**: bound tenant ⇒ scoped, explicit platform mode ⇒ unscoped, neither ⇒ zero rows; creating tenant data with no owner throws. | Tested |
| RBAC | `config/rbac.php` single source of truth (25 roles / 61 permissions) synced by `App\Support\Rbac` (`php artisan schoolos:sync-rbac`, also called by `RolesAndPlansSeeder`). Every tenant and platform route has a `permission:` middleware; controller/policy checks converted from role names to permissions. | Tested |
| Platform vs school users | `EnsureSchoolContext` rewritten: school users must belong to the host's school; platform staff cannot browse tenant routes; school-less non-platform users get 403. `EnsurePlatformAdmin` requires `platform.access` and runs the request in explicit platform mode. | Tested |
| Suspension / trial | `SchoolAccessGate` blocks suspended schools, expired trials, expired/cancelled/past-due subscriptions with an `Errors/AccessBlocked` page (EN/FR/AR). **Data is never deleted.** Grace period default 0 days (`SCHOOLOS_GRACE_DAYS`). | Tested |
| Email verification | `User` implements `MustVerifyEmail`; school registration no longer auto-verifies the admin and sends the verification notification. | Tested |
| CSP | Nonce-based CSP (`SecurityHeaders`), no `unsafe-eval`, no script `unsafe-inline`; nonce applied in `app.blade.php`/Ziggy/Vite. Modes: `enforce` / `report-only` / `off`. | Tested + browser-checked |
| 2FA | Mass-assignment of `two_factor_*` removed; privileged roles forced to enrol when `SCHOOLOS_REQUIRE_2FA_PRIVILEGED` is on (default **on in production**). | Tested |
| Non-HTTP processes | Artisan commands run as trusted platform processes; queue workers stay fail-closed and wipe the tenant between jobs; production password rules (12+, mixed, symbols, uncompromised). | Implemented |
| Auditing | Every denied tenant access is logged (`security.access_denied`) and written to the school activity log when a school is known. | Implemented |

### Test evidence

* Full suite after Phase 1: **154 passed (885 assertions), 0 failed** (`php artisan test`).
* Security acceptance suite: `tests/Feature/Security/SecurityAcceptanceTest.php` — **30 tests passing**
  (baseline run on unmodified code: 14/24 failed).
* Browser check (CSP): school public site renders with the header
  `script-src 'self' 'nonce-…'`, 0 broken images, map iframe allowed, app mounted, 5 nonce'd scripts.
* `/register` under the Apache sub-folder redirects to `/gbschools/public/register-school` (browser-checked).

### Phase 1 outstanding / honest caveats

* The CSP was exercised on the public school site; authenticated pages were covered by tests but not clicked through in a browser.
* The `Errors/AccessBlocked` page is covered by an Inertia component test, not yet by a screenshot.
* Email is on the `log` mail driver — verification links go to `storage/logs`. Real delivery: **Requires External Configuration**.
* Existing dev database: run `php artisan schoolos:sync-rbac` after pulling (done locally). User `smartworldgambia@gmail.com` is unverified — use `php artisan schoolos:verify-email <email>`.
* No git/CI (Phase 11). No load test evidence (Phase 9) — **no scalability claim is made**.


## Phase 2 — SaaS subscription and commercial core

| Area | Change | Status |
|---|---|---|
| Pricing | One configurable **annual** price per school type (`school_type_prices`, append-only, effective-dated, optional tax rate). No tiers, no monthly price, no per-feature pricing. Managed at *Platform > Pricing* (`platform.pricing.manage`). The seeded numbers are **development placeholders**, labelled as such in the UI. | Tested |
| Annual only | `SchoolSubscription` forces `billing_cycle='annual'` on every save and rejects unknown statuses; there is no monthly route, column write path or UI. The old `subscription_plans` / tiered plan pages, controller and model were removed. | Tested |
| Trial | Every registration / platform provisioning / demo school starts an automatic trial (`SCHOOLOS_TRIAL_DAYS`, default 30), `payment_status = not_required_during_trial`. Exactly one subscription per school (unique index). | Tested |
| Lifecycle | trial → active → expired → suspended → active. Only a **verified, full** annual payment activates/renews/reactivates; partial, excess and duplicate-reference payments are rejected. Period = 12 calendar months (leap-day safe); renewals extend from the current end. Every transition is written to `billing_events`. | Tested |
| Expiry / suspension | `schoolos:billing:process` (hourly, idempotent, `--dry-run`) expires then suspends. Access enforcement is the Phase 1 `SchoolAccessGate`, which now reads the same lifecycle (`ends_at` is canonical). **No school record is ever deleted.** | Tested |
| Reminders | Trial: 14/7/3/1 days; paid: 30/14/7/3/1 days (env-configurable). Only the most urgent unsent offset is sent, deduplicated by a ledger (`billing_notifications`, unique subscription+kind+expiry). Status moves queued → sent/failed only from the Notification events, never on queueing. Recipients: verified users with `subscriptions.manage`, else the billing e-mail. | Tested |
| School billing page | `/billing`: status, countdown, school type, annual fee, payment status, payment history, how to pay. Reachable while blocked. Countdown banner on the admin dashboard. `AccessBlocked` shows the amount due and a link to billing. | Tested + browser-checked (FR, AR/RTL) |
| Platform dashboards | *Platform > Billing*: trial/active/expired/suspended counts, pending/failed payments, verified revenue by year/type/method, upcoming trial ends and renewals, recent payments, filters and CSV export (formula-injection guarded). School detail: subscription card, authorised school-type change (audited) and **record verified payment** (cash / bank transfer / Wave — manual, after offline verification). | Tested + browser-checked |
| Commercial surfaces | Landing pricing section and the registration wizard fee step read the live price table; no plan choice exists. | Tested + browser-checked (FR) |
| i18n | `resources/js/i18n/billingCatalog.js` (EN/FR/AR, about 190 keys per locale) merged into `appCatalog`; nav keys `appNav.billing`/`appNav.pricing`; single-line shared buttons; logical RTL classes. | Tested (key parity script) |

### Phase 2 test evidence

* Full suite: **189 passed (1218 assertions), 0 failed** (`php artisan test`), up from 154.
* New: `tests/Feature/Billing/AnnualBillingTest.php` — **35 tests**: price history/scheduling/validation, trial, one subscription per school,
  monthly impossible, full-payment-only, leap-year period, early renewal, price change vs paid periods, expiry/suspension/reactivation with data
  preserved, grace days, idempotent processing, reminders dedupe + delivery tracking + failure recording, recipients, school/platform dashboards,
  permission boundaries, cross-school isolation, audited type change, CSV export.
* Existing tests updated for the plan-less model (registration, custom domains, ops, security, landing).
* A real defect was found by the export test: a streamed CSV runs after the request pipeline and therefore outside platform mode (it would have exported an
  empty file). Fixed by re-entering platform mode inside the stream callback.
* Migration verified: fresh `migrate:fresh --seed` on a throw-away database, `migrate:rollback --step=1` and re-migrate all succeed. The dev database was
  migrated in place (**no backup was taken**; it is a local SQLite dev file).
* Browser (Cursor browser): wizard fee step (FR, "GMD 18,000 par an"), Platform > Pricing, Platform > Billing, school dashboard countdown banner, school Billing in Arabic (RTL).

### Phase 2 outstanding / honest caveats

* **Prices are placeholders** (18,000 GMD for primary etc.). No approved price list was supplied; the Platform Administrator must set real prices before launch.
* **No payment gateway exists.** Platform finance staff record a payment *after verifying it outside the system*; the system cannot confirm cash, bank or
  Wave money itself. Bank-evidence upload/approval, Wave, Stripe and receipts-as-documents are **Phase 3** (*Requires External Configuration* for Wave/Stripe).
* Lifecycle e-mails are English-only (server-rendered). The in-app UI is fully EN/FR/AR.
* Reminder mail runs on the `log` mail driver locally; real delivery is **Requires External Configuration**.
* The hourly scheduler must actually run (cron `schedule:run` / `schedule:work`); that is a deployment requirement, not verified here.
* Pre-existing demo school 1 was backfilled as "active" until 2027-11-01 with no payment row (demo data, not a real payment).
* Custom domains were previously gated by plan code; with no plans they are available to every school (bounded by `custom_domain_limit` and DNS proof).
* Schools list / school detail page chrome that was English-only before Phase 2 is unchanged except for the billing parts, which are localised.
* No load/concurrency evidence for billing (Phase 9). No scalability claim is made.


## Phase 3 - payment workflows (cash, bank transfer, Wave, Stripe, receipts, reconciliation)

| Area | Change | Status |
|---|---|---|
| Payment ledger | `billing_payments` extended (provider, provider_reference, checkout_url, charged amount/currency/fx rate, evidence metadata, rejection/failure/refund/cancel timestamps, expiry); status machine pending / pending_verification / verified / rejected / failed / cancelled / expired / refunded; one open payment per school; unique (provider, provider_reference). Additive migration, `down()` verified. | Tested |
| Cash | School generates a reference; finance records amount/date/receipt-book number; only `platform.payments.verify` confirms; partial/excess refused (audited); printable receipt. | Tested |
| Bank transfer | Platform admin publishes bank details (`platform_bank_accounts`, never deleted - deactivated); school uploads evidence (content-sniffed PDF/JPG/PNG, 5 MB, private disk, random name); `pending_verification`; finance must tick "I checked the real statement" and enter the bank transaction reference; approve or reject (reason shown to school); evidence downloads audited. | Tested |
| Wave | Official Checkout API client, Bearer auth, unique client reference, signed webhook (signing or shared-secret strategy, no downgrade), server-side re-fetch of the checkout, amount/currency/reference match, reconciliation. | Tested with faked HTTP - **Requires External Configuration, NOT Verified** |
| Stripe | Hosted Checkout Session (idempotency key), `Stripe-Signature` verification (HMAC-SHA256, 300 s tolerance, constant-time), server-side re-fetch, amount/currency match, idempotent webhooks, refund detection, failure handling, reconciliation. No card data stored. | Tested with faked HTTP - **Requires External Configuration, NOT Verified** |
| Currency | Explicit per-gateway currency + fixed rate; unavailable without a strategy; disclosed to the school; `expected_charge` guard; round up only with an explicit rate. | Tested |
| Reconciliation | `schoolos:billing:reconcile` every 15 min (`withoutOverlapping`, `--dry-run`): re-checks open gateway sessions, expires stale manual payments, never touches `pending_verification`. Platform report: totals, needs-attention list (overdue verification, mismatches, settlement errors, refunds, unresolved gateway sessions), webhook counts. | Tested |
| Platform UI | *Payments* queue (filters, stats, CSV export with formula-injection guard), payment detail (evidence, cash/bank/reject forms, re-check, audit trail, webhook deliveries), reconciliation, settings (honest per-method status, webhook URLs, bank accounts). | Tested + browser-checked (EN/FR/AR) |
| School UI | *Billing*: Subscribe now / Renew early with only available methods, disclosed converted charge; payment page (cash steps, bank details + evidence upload, gateway waiting/check status, cancel, timeline); history with View/Receipt; printable receipt. | Tested + browser-checked (EN, FR, AR/RTL) |
| i18n | `resources/js/i18n/paymentsCatalog.js` (EN/FR/AR, 259 keys each, parity + placeholder script clean) merged into `appCatalog`; shared single-line `btn-*`; logical RTL classes. | Tested |

### Phase 3 test evidence

* Full suite: **244 passed (1720 assertions), 0 failed** (`php artisan test`), up from 189.
* New: `tests/Feature/Billing/PaymentWorkflowTest.php` - **55 tests**: cash and bank flows; evidence validation (exe, php, text disguised as PNG, oversize); tenant
  isolation; permissions; Stripe and Wave signatures (invalid, stale, tampered, replay); amount/currency mismatch; browser return never activates; provider outage
  returns 5xx then a retry succeeds; refunds; late confirmation; reconciliation (settles missed webhooks, reports outages, expires stale payments, never expires
  under-verification payments); settings secrecy; CSV export; currency helper exactness; audit ordering; no monthly billing introduced.
* Defects found by the tests while building: a test harness leak of tenant context between requests (helpers now clear it); `UploadedFile::fake()` reports the mime
  from the extension, so the "disguised file" case now uses a real text file labelled `.png` (the server-side sniffing refused all four cases).
* `npm run build` clean. Browser (Cursor browser, temporary users and data, all removed afterwards): platform Payments list (EN), Payment settings (FR) incl. adding
  a bank account, school Billing with Subscribe panel, school bank-transfer payment page (EN and AR/RTL), platform payment detail (AR).

### Phase 3 outstanding / honest caveats

* **Wave and Stripe have never talked to the real providers.** All results come from `Http::fake` and locally generated signatures. They are not Verified.
* **No approved FX rate** was supplied. Both gateways are unavailable until `SCHOOLOS_*_CURRENCY` / `SCHOOLOS_*_FX_RATE` are set (a business decision).
* Wave's published currency is XOF; GMD support must be confirmed with Wave.
* Flash/validation messages produced by controllers (for example "Bank account saved.") and lifecycle e-mails are English-only; all page copy is EN/FR/AR.
* Refunds are detected and flagged, not initiated from the UI; access is not withdrawn automatically on refund.
* Receipts are print-to-PDF HTML pages (no PDF library installed).
* No separate "second approver" for cash/bank approval (single authorised confirmation).
* No load/concurrency evidence for payment endpoints (Phase 9). No scalability claim is made.


## Phase 4 - school registration and provisioning

| Area | Change | Status |
|---|---|---|
| Registration | Official name, type, registration number (optional), district, region, school email, phone, address, subdomain, optional logo, administrator name/email/phone/password, mandatory terms + privacy agreement (version and time recorded). Throttled `10/min`. | Tested + browser-checked (AR/RTL) |
| Duplicate detection | `DuplicateDetector`: **blocks** the same normalised registration number or the same name in the same region (generic error, never names the matched school); **flags** (registers, creates a pending review and a `PlatformAlert`) similar name, same phone, same address, same school email, administrator email aliases (`+tag`, gmail dots). | Tested |
| Provisioning | `SchoolProvisioner`: one transaction creates school, subdomain, admin (school_admin, not platform), 30-day trial through `SubscriptionLifecycle::startTrial`, level scaffold, default website. **No students, staff, fees, classes or marks are fabricated.** Welcome email carries the trial end date. | Tested |
| Setup wizard | 12 steps (profile, branding, school type, structure, academic year, departments, classes, subjects, staff, students, parents, website). Save and resume (`school_setup_steps`), required steps cannot be skipped, data-driven steps complete only when real records exist, reopen after completion. Dashboard redirects new school admins to the wizard until complete. | Tested + browser-checked |
| Academic structure | Levels, academic year/terms (ordering, term-in-year, overlap checks), departments, classes, subjects with delete guards (classes with students/timetable, levels with classes, subjects in the timetable). | Tested |
| Staff invitations | Random 64-char token, only the SHA-256 hash stored, single use, 7-day expiry, resend rotates the token, revoke, restricted to `invitable_roles` (never admin/owner/platform), accepted account is verified and school-scoped. | Tested + browser-checked |
| Platform review | *Platform > Registrations*: filter, clear, or confirm duplicate (notes required). Confirming suspends the school and ends the trial. **Nothing is deleted.** | Tested + browser-checked |
| i18n | `resources/js/i18n/onboardingCatalog.js` (EN/FR/AR) merged into `appCatalog`; single-line `btn-*`; logical RTL classes. | Tested (parity script) |

### Phase 4 test evidence

* Full suite: **271 passed (2168 assertions), 0 failed** (`php artisan test`), up from 244.
* New: `tests/Feature/OnboardingTest.php` - **27 tests** (full provisioning without fabricated records, terms and required fields, subdomain rules, rate limit,
  two tenants side by side, duplicate block/flag/alias, wizard redirect and legacy schools, progress persistence, required-step guards, academic-year validation,
  tenant isolation of levels/classes/subjects, permission gating, branding/website/parents, completion/reopen, invitation role restriction, hashed single-use
  token, expiry/revoke/resend/forged tokens, cross-school isolation, review queue access and decisions, paid-school guard, no monthly surface).
* Defects found while building: the welcome email lost the trial end date (lazy-loading a tenant-scoped relation outside tenant context returns null; the
  provisioner now returns the date), test harness hit the registration throttle, profile/branding auto-completed from registration data (now explicit only).
* Migration `2026_11_04_100000_create_phase4_onboarding` verified up / rollback / up on a throwaway database; applied to the dev database.
* Browser (Cursor browser, temporary users and data, all removed afterwards): wizard steps, progress, invitation flow, platform review card (EN), invalid
  invitation page (AR/RTL, HTTP 410), registration form (AR/RTL).

### Phase 4 outstanding / honest caveats

* The registration form's final review step (terms checkbox) was exercised by feature tests, not re-inspected visually in FR/AR after the last edit.
* Controller flash/error strings and emails are English-only; page copy is EN/FR/AR.
* A registered school's public website is published by the existing landing provisioner with disclosed sample copy; step 12 lets the admin keep it private.
* School type is confirm-only in the wizard (it determines the annual price); changes go through support/platform.
* The parent-access setting is recorded only; portal enforcement belongs to a later phase.
* Optional steps can be marked done with zero items via Continue.
* `finance_officer` and `headteacher` carry billing/website permissions by existing RBAC design and are invitable.
* Duplicate detection is heuristic; the platform review is the control against repeated trial claims. No scalability claim is made.
* Wave and Stripe remain **Requires External Configuration, NOT Verified** (unchanged from Phase 3).

## Phase 5 - academic and student management

| Area | Change | Status |
|---|---|---|
| Student records | Permanent unique number `STU-YYYY-00001` from a per-school sequence row (never reused, never editable). Guardian linking (siblings share one guardian by phone), emergency contact, sensitive fields (medical, welfare, religion) hidden without `students.view_sensitive`, private photo and documents (random names on the private disk, served only through authorised routes, audited). Duplicate warning by name + date of birth with explicit confirmation. | Tested |
| Admissions | Public form (`/sites/{slug}/apply`: consent, honeypot, throttle, repeat-submission guard, only for published schools) and walk-in desk. Workflow `submitted -> under_review -> accepted / waitlisted / rejected / withdrawn -> enrolled`; reject needs a reason; waiting-list position; enrolment only from `accepted`, checks class capacity and the plan quota, creates the student and number in one transaction. No application fee (see limitations). | Tested + browser-checked |
| CSV import | Queued job, per-row validation (names, gender, DOB, class, status, guardian email), duplicates inside the file or against existing students are **rejected and reported, never overwritten and never silently skipped**; existing student numbers are never overwritten; row-by-row error report with translated reasons. | Tested |
| Academic structure | Levels, classes (stream, capacity, class teacher), departments (head), subjects, curriculum per class with teacher allocation and weekly periods, copy curriculum from another class. Academic years and **three terms by default, with no hard-coded dates** (the school chooses; ordering, in-year and overlap rules enforced); holidays, exam periods, admission periods and results-publication events. | Tested |
| Movements | Promote, repeat, change class, withdraw, transfer out, graduate, reinstate; each writes an append-only `student_movements` row with reason and actor; bulk promotion is all-or-nothing against the target class capacity. | Tested |
| Teacher scope | `TeacherScope`: managers are unrestricted; a teacher sees only their class-teacher classes, `class_subjects` allocations, headed-department subjects and own timetable slots. A teacher with no allocation cannot mark or register (fail-closed). | Tested |
| Attendance | Daily (`full`, `am`, `pm`) and subject-period registers, statuses present / absent / late / excused / left early with times and reasons; one record per student, date and session; no future dates; foreign students rejected; locked after `SCHOOLOS_ATTENDANCE_EDIT_DAYS` (1) so later changes need a **correction request** that an approver accepts or rejects (reject needs a note). Staff and teacher attendance with its own permission. Daily / weekly / monthly / termly reports. Guardians with portal accounts are notified once per absence (queued database + mail). | Tested + browser-checked |
| Timetable | Periods and breaks (no overlap), rooms, teacher unavailability, lessons saved as **drafts**. Validation: class, teacher and room overlap, break, teacher unavailable, room inactive, subject not in the class curriculum, teacher not allocated. Publication is all-or-nothing and refused while any conflict exists. Parents and students see only published lessons for their own children / class. | Tested + browser-checked |
| Examinations and results | Per-school assessment categories with weights (active weights must total 100) and a per-school grading scale (lowest band must start at 0). Weighted total = sum(score / max x weight). Workflow `draft -> submitted -> approved -> published`; marks editable only in draft and only for allocated class + subject; publication needs `results.publish`. Published results can be corrected only by a publisher, with a reason of at least 5 characters, and every change is written to the append-only `grade_changes` history. Learners and guardians see published results only; printable report card. | Tested + browser-checked |
| Identity fix | `User::isStaffPortal / isLearnerPortal / isGuardianPortal / isFamilyPortal`. `school_admin` and `school_owner` hold every `portal.*` permission through `*school`, so checks of `can('portal.student')` alone treated administrators as learners. Fixed in the student, invoice and fee policies and controllers and in attendance and timetable. | Tested |
| i18n | `academicCatalog.js` (594 keys, EN / FR / AR, built from one row list so locales cannot drift). Every `err.*` and `ok.*` code sent by controllers and services is translated client-side (`messageText`). Logical Tailwind classes, single-line `btn-*`. | Parity script + browser (EN / FR / AR) |

### Phase 5 test evidence

* Full suite: **311 passed (2653 assertions), 0 failed** (`php artisan test`), up from 271.
* New: `tests/Feature/AcademicOperationsTest.php` - **40 tests**: student registration and number permanence, duplicates, quota and capacity, cross-tenant class, sensitive fields, private documents, teacher scope, parent and student rights, public application (consent, honeypot, repeat guard, unpublished school), admission workflow and permissions, CSV import (invalid and duplicate rows reported, never overwritten), movements, calendar, curriculum, department head, daily and period attendance, locked registers with corrections, guardian notification once, staff attendance, attendance reports, student history, timetable conflicts / availability / publication blocking / role views, period overlap, weighted results and scale, category weights, mark restrictions, result workflow, published lock with change history, published-only visibility, tenant scoping, and the mark sheet with learners that have no marks yet.
* Defects found and fixed while building: administrators treated as learners or guardians (see Identity fix); `GradeChange` missing `student` / `subject` relations; staff attendance unique-constraint violation on SQLite (date compared as text); the import job cleared the tenant mid-request (now restores the previous tenant); mark sheet crashed for a learner without a grade row (`optional(...)?->scores->...` evaluates `->scores` on the wrapper; found in the browser, regression test added); two file inputs were wider than a 390 px screen.
* Migration `2026_11_05_100000_create_phase5_academic_student_management` applied to the dev database.
* Browser (Cursor browser, temporary school and users, all removed afterwards): attendance register (AR / RTL), mark entry with live weighted total, saved flash message and grade (FR), timetable (AR / RTL). An automated sweep loaded **24 pages in EN, FR and AR at 1280 px and 390 px** and checked for server errors, raw translation keys, horizontal overflow, wrapped buttons, `lang` and `dir`: clean after the fixes above.

### Phase 5 outstanding / honest caveats

* Application fees are **not implemented** (belongs to the payments phase); the public form says submitting does not guarantee a place.
* **SMS is not implemented.** Guardian alerts are queued database + mail notifications, not delivery-verified, and only reach guardians who have a portal account.
* No class ranking or position; report cards are print-ready HTML, not PDF; no timetable auto-generation; no room-capacity check.
* The default grading scale (A >= 80, B+ >= 70, B >= 60, C >= 50, D >= 40, F) and weights (CA 40, exam 60) are editable starting points, **not official Gambian or WAEC grading**. That is Phase 6.
* Parent and student login provisioning from admissions belongs to Phase 8; enrolment creates the student and guardian records only.
* Laravel's built-in validation messages and a few legacy English messages are shown as received; default assessment category names are English until the school renames them.
* Rules tightened versus earlier behaviour: enrolment only from `accepted`; admissions require gender, date of birth, guardian name and phone; teachers without an allocation cannot mark; publishing requires approval.

## Phase 6 - Gambian external examination workflows

| Area | Change | Status |
|---|---|---|
| GABECE (Grade 9) and WASSCE (Grade 12) registration | School-managed sessions per exam type and year with an optional registration window. Candidates are registered from active students only; the level must be Grade 9 (GABECE) or Grade 12 (WASSCE) when the school has mapped its level to a grade number, and unmapped levels are not guessed at. Each candidate has a permanent internal reference (`IC-GABECE-2026-00001` / `IC-WASSCE-2026-00001`, deliberately unlike an official index number) that is **never** an official index number. Subjects per exam type are school-editable. Documents (registration form, birth certificate, photo) go to the private disk. | Tested + browser-checked |
| Official index number | Stored **exactly as supplied** (the `official_index_number` input is excluded from `TrimStrings`; blank-padded, internal-looking and duplicate values are refused). Changing a recorded number needs `external_exams.verify` and a reason, and is written to history. The system never generates one. | Tested |
| Results | CSV import (queued, row-limited, per-row error report; candidates matched by official index number, internal reference or student number) and manual entry. Workflow `entered -> verified -> published`; a second authorised person verifies; correction after verification or publication needs a reason and keeps old/new values in `external_result_changes`. Grades are limited to the WAEC grade list for the exam type. | Tested |
| Results collection register | Per candidate: results received on, collected on, collector name and ID, staff responsible, acknowledgement text, outstanding status. Filterable register and a printable report (`/external-exams/collections/print`). | Tested + browser-checked |
| Certificate tracking | Awaiting certificate / Received by school / Available for collection / Collected / Outstanding. The 2-3 year wait is shown as an **estimate only**, never as a promise. Daily command `schoolos:records:remind` (07:30) sends a `CollectionReminder` database + mail notification to staff with `external_exams.manage`, every `SCHOOLOS_COLLECTION_REMINDER_DAYS` (14) days, at most `SCHOOLOS_COLLECTION_REMINDER_MAX` (6) times. | Tested; **mail delivery NOT verified** |
| Senior Secondary application portal | Per-school **rounds** with an opening and closing date, capacity, waiting-list capacity and a rule set held in data: minimum subjects, required subjects, subject combinations, aggregate method and threshold, optional weighted scoring and eligibility criteria. Nothing is hard-coded to a named school. Public form (`/sites/{slug}/senior-secondary/{round?}`: consent, honeypot, throttle, published schools only) captures applicant, previous school, GABECE results and chosen subjects. The engine calculates the aggregate, assesses eligibility, ranks, and proposes **admit / waitlist / reject** as a recommendation only. | Tested + browser-checked |
| Human review and audit | A reviewer approves, rejects or waitlists; deviating from the engine recommendation needs a reason; every decision is an append-only `ss_application_events` row. Admission letters are generated from the Records Office. | Tested |
| Records Office - transcripts | Grade 9 transcript = 9 terms (Grade 7, 8, 9 x Terms 1-3). Grade 12 transcript = exactly **8 terms** (Grade 10 T1-3, Grade 11 T1-3, Grade 12 T1-2); Grade 12 Term 3 is not auto-included and an authorised admin may approve an exception with a reason. Terms with no data are shown as such, not invented. Header carries school identity, student, photo, permanent ID, programme, year, subjects, marks, grades, descriptions, summary, issue date, unique reference, signatures and stamp. | Tested |
| Records Office - testimonials, letters, templates | Templates with merge fields and authorised custom text; signatories and stamp image per school (`/records/settings`). | Tested + browser-checked |
| Document workflow | Draft -> Submitted for review -> Approved -> Issued -> Reprinted / Revoked, with an issuance register (`records_document_events`), per-school unique reference numbers from `school_sequences`, reason required to revoke. | Tested |
| PDF and verification | dompdf PDF with a QR code (chillerlan/php-qrcode) pointing to `/verify/{token}`. The public page confirms authenticity with **minimal disclosure** (type, reference, issuing school, issue date, valid or revoked, and the holder's initials only - no full name, marks, date of birth, photo or contact details; unknown and draft tokens look identical), throttled. Every document carries the statement that it is a school record and **not** an official WAEC result or certificate. | Tested; PDF rendered in tests, visual PDF review is manual |
| Middleware order fix | `SubstituteBindings` ran before the tenant was bound, so route-model binding on tenant models 404'd in a real browser while tests (which pre-bind the tenant) passed. Priority list now runs `EnsureTwoFactorVerified -> IdentifyTenant -> EnsureSchoolContext -> SubstituteBindings`. | Tested (regression tests fail without it) |
| i18n | `recordsCatalog.js` (548 keys, EN / FR / AR from row lists `recordsRows1-3.js`; 570 keys used by pages/services, 0 missing, 0 unreferenced; checked by `node scripts/check-phase6-i18n.mjs`). | Parity script + browser (EN / FR / AR) |

### Phase 6 test evidence

* Full suite after the final changes: **387 passed, 0 failed** (php artisan test), up from 311. An intermediate run showed one failure (login redirects to two factor challenge) caused by the middleware-order fix; the 2FA gate was moved ahead of the tenant checks and the test passes.
* New files: `tests/Feature/Phase6TestCase.php` (shared fixtures), `ExternalExamsTest.php`, `SeniorSecondaryTest.php`, `RecordsOfficeTest.php`; plus three "no tenant pre-bound" regression tests.
* Migration `2026_11_06_100000_create_phase6_external_exams_records` (18 tables) applied to the dev database.
* Browser (Cursor browser, temporary school and user): an automated sweep loaded **18 pages in EN, FR and AR at 1280 px** (external exams x6, Senior Secondary x3, Records Office x7, public verification page, public Senior Secondary form) and checked for server errors, raw translation keys, horizontal overflow, wrapped buttons, `lang` and `dir`: clean. The 404 on `/external-exams/sessions/{id}` found here led to the middleware-order fix above.

### Phase 6 outstanding / honest caveats

* **Nothing here contacts WAEC.** Index numbers, results and certificate status are entered or imported by the school; there is no WAEC API and none is claimed.
* Collection reminders are database + mail notifications and are **not delivery-verified**; there is no SMS.
* Admission letters and transcripts are school documents; the school must configure signatories and a stamp image before issuing.
* Senior Secondary rules are configurable but the school must enter its own thresholds; no defaults reflect any named school.
* The PDF layout has been generated in tests but has not been reviewed on a printer; review one issued sample per school before relying on it.
* Automatic result import from WAEC files in an official layout is not implemented; the CSV template is the school's own format.



## Phase 7 - integrated LMS (native Laravel, no separate Moodle)

The LMS uses the school's existing users, classes, subjects, teacher allocations and RBAC. Nothing is hosted elsewhere.

| Area | What exists | Status |
|---|---|---|
| Courses | Create / edit / archive, categories, subject linking, lead teacher plus co-teachers, learning objectives, sections (ordered), class-based audiences, enrolment (manual and "sync from class"), withdrawal. A teacher can create a course only for a subject they are allocated to. | Tested + browser-checked |
| Access model | `LmsAccess`: manager (`lms.manage`), teacher (`lms.teach` and teaches that course), learner (learner-portal user with an **active** enrolment in a **published** course). Other users get 403; a draft or restricted lesson looks like a 404 to learners. Per-course teacher scoping, tenant isolation tested. | Tested |
| Lessons | Text / video / document / link / external link, ordered within sections, draft or published, scheduled publication (`publish_at`), restriction to specific classes, file uploads (private disk) and web links, per-student completion tracking. | Tested + browser-checked |
| Assignments | Instructions, deadline, file and text submissions, late policy (allow / penalty / block), resubmission limit, rubric, teacher feedback, grade, attachments, timestamps, late flag. | Tested + browser-checked |
| Question banks | Private per teacher (shared banks are explicit), multiple choice, true/false, short answer, essay; a bank question that is in use cannot be deleted. | Tested |
| Quizzes | Question types above; random pulls from banks (snapshotted per attempt so later bank edits never alter a taken quiz); time limit (20 s grace, deadline capped at the closing time, enforced by the server, not the browser); attempt limits; shuffle questions and options; one running attempt that is resumed; autosave; auto-grading of MC / true-false / short answer; essays wait for a teacher; grade method (highest / latest / average); result release (immediate / after close / scheduled / manual). **Correct answers are never sent to the browser while taking**, and are shown after the attempt only if marks are released, "show correct answers" is on and no attempts remain (or the quiz is closed). A quiz that has attempts cannot be edited or deleted. | Tested + browser-checked (EN / FR / AR) |
| Short-answer grading | `AnswerMatcher` normalises Unicode, case, whitespace, trailing punctuation and numbers (decimal comma; ambiguous "1,000" is never guessed). It is **not** a complete grading engine: no synonyms, no spelling tolerance, no semantic marking. Teachers can override any mark. | Tested; limitation documented |
| Gradebook | Per learner: assignment marks, released quiz marks, published examination results (shown separately), lesson progress, teacher feedback; CSV export. The course percentage is a teaching aid, **not an official mark**. Learners see only themselves and only released quiz marks. | Tested + browser-checked |
| Progress and analytics | Enrolment, lesson completion, assignment completion, quiz attempts, average grades, inactive learners (`SCHOOLOS_LMS_INACTIVE_DAYS`). Staff only. | Tested + browser-checked |
| Communication | Course announcements (scheduled, optional notification), discussion forum with questions and accepted answers, moderation (hide post), per-student teacher feedback. | Tested; **announcement email delivery NOT verified** |
| SCORM / LTI | **Not implemented.** Only a link-only hook remains (a lesson can open an http(s) URL; tool and package records store a name and URL). There is no SCORM runtime, no LTI launch, no grade passback. The nav item is now "External content", not "SCORM". | See capability table below |
| i18n | `lmsCatalog.js` (516 keys, EN / FR / AR from `lmsRows1-3.js`; parity checked by `node scripts/check-phase7-i18n.mjs`). All `err.lms.*` / `ok.lms.*` message codes translated. | Parity script + browser (EN / FR / AR) |
| Dev-time fixes found by tests and browser | `quiz_questions.correct_answer` was NOT NULL (essays failed): made nullable. Matcher treated "1,000" as 1: now ambiguous cases return no match. External-content launch had no per-course access check: added, and only http(s) URLs are accepted. Forum thread with no `type` crashed: defaults to discussion. Quiz clock showed fractional seconds: floored. | Fixed + regression tests |

### Phase 7 capability status (honest)

| Capability | Status |
|---|---|
| Native courses, lessons, assignments, quizzes, gradebook, progress, announcements, forum | Implemented, Tested, browser-checked |
| Quiz result release and answer secrecy | Tested |
| Automatic short-answer marking | Implemented, **limited** (exact match after normalisation) |
| Rubrics | Implemented as teacher-entered criteria and marks; no rubric templates library |
| SCORM 1.2 / 2004 | **Not Started** (not implemented, not advertised) |
| LTI 1.1 / 1.3 | **Not Started** (not implemented, not advertised) |
| External web links in lessons | Implemented, Tested |
| Announcement email | **Requires External Configuration** (`MAIL_*`); delivery NOT verified |
| Anti-cheating / proctoring | Not Started (time limit and attempt limit only) |
| Video hosting | Not provided; link to a video URL or upload within `SCHOOLOS_LMS_FILE_MAX_KB` |

### Phase 7 test evidence

* Full suite: **445 passed, 0 failed** (4055 assertions; was 387 after Phase 6).
* New LMS suites: `LmsCoursesTest` (21), `LmsQuizTest` (21 tests, 288 assertions), `LmsCommunityTest` (12 tests, 276 assertions), `LmsExternalLinkTest` (4), plus the updated legacy `Phase3LmsCommsTest` (4) and base class `Phase7TestCase`. They cover all seven acceptance criteria: teachers create courses; learners see only their assigned courses; lessons are published and scheduled; assignments are submitted (including late and resubmission); quizzes are taken with the timer and attempt limits; grades are recorded and released; progress is tracked.
* Migration `2026_11_07_100000_create_phase7_lms` (13 new tables, 11 extended) applied to the dev database; `lms.view`, `lms.teach` and `lms.manage` confirmed on the existing school roles.
* Browser (Cursor browser, temporary school, teacher and learner, then removed): automated sweep of **17 teacher pages and 8 learner pages in EN, FR and AR at 1280 px**, plus the result pages, checking server errors, raw translation keys, horizontal overflow, wrapped buttons, `lang` and `dir`: clean. A real run: the learner started the quiz, answered by clicking and typing, autosave showed "saved", submitted; auto-marked items scored 5 and the essay showed "Awaiting marking" with no score leaked. The sweep found the fractional-seconds clock, now fixed.

### Phase 7 outstanding / honest caveats

* SCORM and LTI are **not implemented**. Do not describe the product as SCORM- or LTI-compliant.
* Announcement notifications are database + mail and are not delivery-verified; there is no SMS.
* Short-answer marking is exact-match after normalisation; teachers must review borderline answers.
* The gradebook percentage is not an official mark; official marks come from the Phase 5/6 examination workflow.
* Parent views of a child's courses and course messaging between parents and teachers belong to Phase 8 (portals and communication).
* The announcement scheduler (`schoolos:lms:announce`, every 10 minutes) needs the Laravel scheduler running.
* Large file uploads are limited by `SCHOOLOS_LMS_FILE_MAX_KB` and by PHP `upload_max_filesize` / `post_max_size`.

## Phase 8 - portals and communication

Teacher, student and parent portals, secure account provisioning, announcements, internal messaging, SMS and a per-person notification centre. Everything is tenant-scoped, fail-closed, and EN / FR / AR with RTL.

| Area | What exists | Status |
|---|---|---|
| Account provisioning (Part 16) | One invitation flow for **staff, learners and guardians** (`/accounts`, permission `users.manage`): invite, resend (new token), revoke invitation, accept (verifies the e-mail), suspend (ends live sessions), reinstate, revoke access (roles removed, records kept), restore (only with a role that fits the person), send a reset link by e-mail (the admin never sees a link or password). Staff invitations can hand over chosen class/subject allocations. Admin and owner roles can never be invited; protected accounts and one's own account cannot be suspended or revoked; a learner or guardian can be linked to **one** login only; guardian invitations obey the school's `parent_access` switch. `EnsureAccountActive` (first in the web stack) logs blocked users out, and login refuses them with a translated code. | Implemented, Tested (14 tests) |
| Teacher portal | Dashboard scoped by `TeacherScope`: own classes, own subject allocations, own timetable, own courses, marking queue of own courses, announcements, unread messages. **No "show everything" fallback**: a teacher with no allocation sees empty lists. | Implemented, Tested, browser-checked |
| Student portal | Dashboard shows only active enrolments in **published** courses, live assignments not yet submitted, published quizzes, and results that are released (published exam grades, plus continuous-assessment grades as entered). A login with no learner record sees an empty portal. | Implemented, Tested, browser-checked |
| Parent portal | Only the children linked to the guardian record (a parent without a guardian record gets 403); released results only; invoices, balances and payments for those children only; announcements; unread messages; "contact teacher". | Implemented, Tested, browser-checked |
| Announcements (19.1) | School-wide, staff, teachers, learners, guardians, or specific classes; draft, publish now, schedule (`schoolos:announcements:publish`, every minute), expiry, cancel. Recipients are frozen at publication; the feed shows only what was addressed to you; opening the feed marks read; the author sees reach and read counts. Teachers (`announcements.class`) can address **only classes they teach**. In-app is always on; e-mail and SMS are chosen per announcement and still obey each person's preferences. | Implemented, Tested (18 tests) |
| Internal messaging (19.3) | Direct conversations and class groups, attachments (private disk, mime/size/count checks, served only to participants), read status, unread badges, mute, lock, report, moderator review. Rules: staff to staff always; staff to guardian/learner only for classes they teach (leadership and office roles: any); a guardian can write only to teachers of their own children's classes or office staff; a learner can only answer unless `SCHOOLOS_MESSAGING_STUDENTS_START=true`; **families can never message each other**; class groups hide other families' names. Moderators open a conversation **only if it has a report**, and each view is audited. | Implemented, Tested (18 tests) |
| Notifications and preferences (19.2, 19.5) | In-app centre (paginated, filter by category / unread), per-category channel choices (in-app, e-mail, SMS), mandatory account/security notices, e-mail only to **verified** addresses, delivery history per person and per school. History says **"logged"** (never "sent") when the mail driver is `log` or `array`. | Implemented, Tested (15 tests) |
| SMS (19.4) | Providers: `log` (default; recorded as *logged*, nothing leaves the server), `null`, **Twilio**, **Africa's Talking**. Phone normalisation (E.164, Gambian 7-digit numbers), GSM/Unicode segment count, per-school switch, daily cap, per-language templates with school overrides, queue + retry with back-off, permanent failures stop at once, a sent message is never sent twice, cost tracking where the provider reports it, SMS log with masked numbers and manual retry, delivery-report webhooks (Twilio signature check; Africa's Talking path secret) that never downgrade `delivered`. | Implemented, Tested against **faked HTTP only**. **Requires External Configuration. NOT Verified** |
| Communications centre | `/communications` (`communications.manage`): SMS switch, sender name, daily cap, parent/learner messaging switches, provider state with an honest note, test SMS, templates, SMS log, delivery history. | Implemented, Tested (12 tests) |
| i18n | `commCatalog.js` (446 keys, EN / FR / AR from `commRows1-3.js`; parity checked by `node scripts/check-phase8-i18n.mjs`). All `ok.*` / `err.*` codes for accounts, messages, announcements and SMS translated; role labels and the shell's role chip are localised. | Parity script + browser (EN / FR / AR) |
| Dev-time fixes found by tests and browser | **Audience bug:** school administrators hold every portal permission (`*school`), so "learners" and "guardians" announcements also reached them; "teachers" now means teaching roles and learners/guardians exclude staff. **Existence leak:** a stranger replying to or reporting a conversation got a redirect; now 404 exactly like opening it. Stale-relation test fixtures corrected. Wrong "Back to messages" label on announcements/preferences, the student-messaging hint (said "off by default" while the switch is on) and the SMS-log note (claimed text was not stored) were corrected. | Fixed + regression tests |

### Phase 8 capability status (honest)

| Capability | Status |
|---|---|
| Provisioning, portals, announcements, messaging, notification centre and preferences | Implemented, Tested, browser-checked |
| In-app notifications | Implemented, Tested |
| E-mail notifications (invitations, resets, announcements, messages, attendance, exams, fees) | Implemented, Tested with the `array` mailer. **Requires External Configuration** (`MAIL_*`). **Delivery NOT verified** - no real message has been sent to a real inbox |
| SMS with the `log` driver | Implemented, Tested. Nothing is delivered, and the UI says so |
| SMS via Twilio / Africa's Talking | Implemented, Tested with faked HTTP and signed callbacks. **Requires External Configuration. NOT Verified** until a real test SMS arrives on a phone |
| SMS delivery reports | Implemented, Tested; needs the provider to be pointed at the webhook URLs |
| Parent view of LMS courses | Not Started (parents see attendance, results, fees, timetable and messages, not course content) |
| Push notifications / WhatsApp | Not Started |

### Phase 8 test evidence

* Full suite: **555 passed, 0 failed** (5174 assertions; was 445 after Phase 7). Run output kept in `storage/app/p8-full.txt`.
* New suites (110 tests): `Phase8AccountsTest` (14), `Phase8MessagingTest` (18), `Phase8SmsTest` (21), `Phase8NotificationsTest` (15), `Phase8AnnouncementsTest` (18), `Phase8PortalsTest` (12), `Phase8CommunicationsTest` (12), on the shared `Phase8TestCase`.
* Migration `2026_11_08_100000_create_phase8_portals_communication` (11 new tables; `users` and `staff_invitations` extended) applied to the dev database; the six new permissions confirmed on the school roles with `php artisan schoolos:sync-rbac`.
* `npm run build` passes; `node scripts/check-phase8-i18n.mjs` and `node scripts/check-phase7-i18n.mjs` pass.
* Browser (Cursor browser, temporary school with an administrator, teacher, parent and learner, then removed): automated sweep of **16 administrator pages and 9 / 8 / 6 teacher / parent / learner pages in EN, FR and AR at 1280 px**, checking server errors, raw translation keys, horizontal overflow, wrapped buttons and `lang` / `dir`: clean. An administrator opening someone else's conversation got 404 (as designed); a learner opening Communications got 403.

### Phase 8 outstanding / honest caveats

* **E-mail delivery has not been verified and SMS delivery has not been verified.** Do not tell schools SMS works until Communications -> "Send test" has put a real message on a real phone with a real provider.
* The Twilio and Africa's Talking gateways were tested against documented response shapes only.
* Notification e-mail wording is English (the in-app text and SMS templates are localised).
* One global account per e-mail address: a guardian with children in two schools needs a separate address for each school.
* Learners cannot start conversations by default (child-safeguarding choice).
* The scheduler (`schoolos:announcements:publish`, every minute) and a queue worker for the `notifications` queue must be running.
* There is no read receipt or bounce handling for e-mail, so history shows "sent" or "logged", never "delivered".
* Messages are plain text; there is no encryption at rest beyond the database and the private disk, and no keyword filtering.

## Phase 8 follow-up - platform console: mail and SMS credentials

Requested after Phase 8: mail and SMS credentials can be entered by a **platform administrator** in the console (`/platform/integrations`) instead of editing `.env`. They are **platform-wide, not per school**: one SMTP account and one SMS provider account serve every school. Schools only decide whether to use SMS and how much (Communications -> Settings).

| Area | What exists | Status |
|---|---|---|
| Storage | `platform_settings` rows `integrations.mail` and `integrations.sms` (no new migration). Secrets (SMTP password, Twilio token, Africa's Talking API key and webhook path secret) are encrypted with the application key field by field; other values are plain. | Implemented, Tested |
| Permission | New `platform.integrations.configure`, held only by `platform_admin` and `super_admin` (`*platform`). Finance, support, operations and auditor roles get 403, and the menu entry is hidden from them. In production the existing 2FA requirement for privileged roles applies. | Implemented, Tested |
| Write-only secrets | The page never receives a secret. It shows "set / not set" and, for secrets of 16+ characters only, the last four characters. A blank secret on save keeps the stored one. | Implemented, Tested (HTML scanned for the secrets) |
| Re-authentication | Save, switch on, switch off and remove all need the administrator's own password (`current_password`). | Implemented, Tested |
| Nothing live untested | Saving never changes behaviour. **Switch on** is refused unless a test message with exactly these values (HMAC fingerprint of the values) was accepted. Changing a live value switches the integration **off** again and the server default (`.env`) takes over, so a typo cannot lock everyone out of password resets. | Implemented, Tested |
| Verified only by a person | "Test accepted" is not "received". **Verified** is set only when the administrator presses "I received it" for the current values; any change or new test clears it. | Implemented, Tested |
| Audit and notice | `ActivityLog` events `platform.integration.{saved,test_ok,test_failed,activated,deactivated,cleared,confirmed}` with the actor, integration and **field names only** (never values, never the host). The other platform administrators receive an e-mail for saved / switched on / switched off / removed. | Implemented, Tested |
| Applying | `IntegrationSettings::apply()` overlays only ACTIVE, fingerprint-matching values on the framework config at boot and before every queued job (`JobProcessing`), purging the mail transport when it changed; the encrypted blob is cached (`schoolos.integrations.v1`) and the cache entry is dropped on every write. `.env` values stay the fallback. Unreadable secrets (changed `APP_KEY`) are reported as "unreadable, enter again" and never activate. | Implemented, Tested |
| Errors | A provider or SMTP failure is stored with every configured value removed, capped at 300 characters, and shown on the card. | Implemented, Tested |
| i18n | `integrationsCatalog.js` (109 keys, EN / FR / AR; `node scripts/check-integrations-i18n.mjs`). Validation and flash messages are `ok.integ.*` / `err.integ.*` codes. | Parity script + browser (EN / FR / AR) |

### Status after this follow-up (honest)

| Capability | Status |
|---|---|
| Console for mail / SMS credentials | Implemented, Tested (26 tests), browser-checked in EN / FR / AR |
| Mail path with console settings | Verified end to end against a **local throw-away SMTP sink** (real SMTP conversation with AUTH; the sender name/address came from the console; a password-reset sent by a separate CLI process used the same settings). **A real provider and a real inbox have NOT been tested.** Email stays "Requires External Configuration - NOT Verified" until you use a real SMTP account, press "Send test email" and confirm receipt |
| SMS with console settings | Implemented, Tested with faked HTTP. A test against the real Twilio API with deliberately fake credentials returned `20003 Authentication Error`, which the console showed as "Test failed" and refused to switch on. **No real SMS has been delivered.** Requires External Configuration - NOT Verified |
| Wave / Stripe credentials in the console | Not Started (they stay in `.env`; the Payments screen is still read-only) |
| Per-school mail / SMS credentials | Not Started (not requested) |

### Follow-up test evidence

* `tests/Feature/IntegrationsConsoleTest.php`: 26 tests (permissions, menu, password re-ask, encryption at rest, write-only secrets, hint rules, blank-secret keep, unreadable secrets, hand-edited row cannot activate, test-before-activate, failed test leaks nothing, live change switches off, deactivate/clear restore the server default, worker pick-up, confirmation rules, validation codes, SMS provider completeness, Twilio and Africa's Talking application including the webhook secret, audit without values, notices to other administrators, server default shown without secrets, schools cannot see platform credentials).
* Full suite: **581 passed, 0 failed** (5448 assertions; 555 before this follow-up). Run output kept in `storage/app/int-full.txt`. `npm run build` passes; the Phase 7, Phase 8 and integrations i18n parity scripts pass.
* Browser: administrator saved SMTP settings (password hint showed only the last four characters), sent a test e-mail to the local sink, switched on, and the Twilio card showed the real "20003" failure; buttons keep one line (40-41 px) in EN, FR and AR; RTL verified. The temporary administrator, settings and sink were removed.

## Phase 9 - finance and other school modules

School-side fees (separate from SaaS billing), HR/payroll, library, inventory/assets, procurement, transport, boarding, restricted safeguarding, and events. Tenant-scoped, fail-closed, EN / FR / AR with RTL. Collection configuration for school fees is not the platform Wave/Stripe SaaS billing console.

| Area | What exists | Status |
|---|---|---|
| 9A Fees | Categories and structures, class/student charges, discounts/scholarships/waivers, installments, invoices, payments (Cash/Wave/Bank as school methods), receipts, reconciliation, reports. Permissions `fees.view\|manage`, `payments.record\|verify`, `fees.approve`. Command `schoolos:fees:remind`. | Implemented, Tested (40 tests in `Phase9FinanceTest`) |
| 9B HR | Staff, departments, positions, contracts, documents, leave, attendance hooks, performance, configurable payroll components, payslips (PDF). Self-service `my.leave` / `my.payslips` for `staff.self`. | Implemented, Tested (`Phase9HrTest` + legacy Phase 6 leave/payroll) |
| 9C Library | Catalogue, authors/categories, ISBN check, copies with accession numbers, loans (`library_book_id` kept), renewals, returns, overdue, fines, history. | Implemented, Tested (`Phase9LibraryTest` + legacy loan/return) |
| 9D Inventory / assets | Items, SKUs, locations, categories, signed movements (never below zero), one-shot low-stock alerts, tagged assets, assignment, maintenance, disposal. | Implemented, Tested (`Phase9InventoryTest`) |
| 9D Procurement | Requests → submit → second-person approve → PO → goods received (stock updated) → supplier invoice (only if received value covers it) → pay. Self-approval off by default. | Implemented, Tested (`Phase9ProcurementTest`) |
| 9E Transport | Vehicles, drivers, routes, stops, one active assignment per learner, vehicle capacity, attendance, term charges via `TermCharge` (`transport:{route}:{term}`). | Implemented, Tested (`Phase9TransportTest` + legacy vehicle store) |
| 9E Boarding | Hostels, rooms, auto-created beds, gender match, one active allocation, attendance, welfare notes (boarders only), term charges (`boarding:{hostel}:{term}`). | Implemented, Tested (`Phase9BoardingTest` + legacy hostel store) |
| 9F Safeguarding | Report vs manage split. Lists omit the summary. Opening a case is audited without the summary in properties. Free text encrypted (`SensitiveText`). Close needs a note. | Implemented, Tested (`Phase9SafeguardingTest` + legacy teacher 403 on the list) |
| 9F Events | Calendar, venues (clash refused), publish/cancel/complete, registration with capacity, attendance, optional announce-on-publish. | Implemented, Tested (`Phase9EventsTest`) |
| i18n | Catalogs `financeCatalog`, `hrCatalog`, `opsCatalog` (library, inventory/assets, procurement, transport, boarding, safeguarding, events). Gate `node scripts/check-phase9-i18n.mjs`. Nav keys `appNav.inventory/procurement/events/reportConcern` EN/FR/AR. | Parity script |

### Phase 9 capability status (honest)

| Capability | Status |
|---|---|
| School fees, HR, library, inventory, procurement, transport, boarding, safeguarding, events | Implemented, Tested |
| School fee Cash / Wave / Bank methods | Implemented, Tested as school collection config. **Not** the SaaS billing gateways. Wave/Stripe SaaS remain **Requires External Configuration, NOT Verified** |
| Payslips and receipts PDF | Implemented, Tested with Dompdf |
| E-mail / SMS for fee reminders, leave, low stock, critical safeguarding, events | Implemented, Tested with the `array` mailer / in-app. **Delivery NOT verified**. Notification wording is English |
| Monthly SaaS billing | Not in this product (annual only since Phase 2) |

### Phase 9 test evidence

* Phase 9 suites: **117 passed** (`Phase9FinanceTest`, `Phase9HrTest`, `Phase9LibraryTest`, `Phase9InventoryTest`, `Phase9ProcurementTest`, `Phase9TransportTest`, `Phase9BoardingTest`, `Phase9SafeguardingTest`, `Phase9EventsTest`).
* Legacy Phase 6 flows kept: `test_hr_leave_and_payroll_flow`, `test_library_loan_and_return`, `test_transport_boarding_and_safeguarding`, `test_teacher_cannot_access_safeguarding`.
* Full suite: first run **697 passed, 1 failed** (`Phase8PortalsTest` parent nav still expected the pre-fees list). After expecting `fees` and `events` on the parent menu (parents already have those permissions), that test and the whole `Phase8PortalsTest` file (**12 passed**) succeed. Equivalent full suite: **698 passed, 0 failed**.
* `node scripts/check-phase9-i18n.mjs` passes (finance 319, hr 278, library 130, inventory 125, procurement 132, transport 63, boarding 72, safeguarding 52, events 88). `npm run build` passes.
* Dev database: migrations `2026_11_09_100000` … `130000` applied; `php artisan schoolos:sync-rbac` (86 permissions).
* Browser (Bright Future, `127.0.0.1:8123`): school admin saved inventory item *Chalk* / `CHK-1` and event *Sports Day* (draft, publish/cancel). Fees, procurement requests, and safeguarding case list rendered with translated chrome. Language switcher: AR (RTL, `dir=rtl`) → FR (`dir=ltr`, `lang=fr`) → EN. Teacher (`teacher@brightfuture.test`) sees Events + Report a concern, not Inventory/Procurement/Safeguarding cases; `/safeguarding/report` is allowed; `/safeguarding` is **403**. Buttons stayed one line (Save item / Save event / New invoice / Save request / Submit report). No raw i18n keys on Phase 9 pages. Dashboard leftover English labels were deferred to Phase 10 and are now localised.

### Phase 9 outstanding / honest caveats

* **E-mail and SMS delivery have not been verified.** Fee reminders and safeguarding notices are not proven on a real inbox or phone.
* **Wave and Stripe (SaaS) remain Requires External Configuration, NOT Verified.** School fee "Wave" is a labelled school payment method, not a live Wave API charge.
* Notification e-mail wording is English (in-app labels are EN/FR/AR).
* Event registration unique indexes include cancelled rows; a dropped registration is reused rather than inserted again.
* Safeguarding officers must have `safeguarding.manage`; reporters with only `safeguarding.report` never see the case file after submit.
* Phase 11 (CI files, metrics lock, deployment guide) is documented below.

## Phase 10 — remaining historical i18n

Leftover English chrome that earlier phases skipped: school admin dashboard, generic fallback home, reports, exports, profile / 2FA, and platform console pages (dashboard, schools, backups, ops, scale, monitoring, capacity, marketing). School-entered content, notification email bodies, Laravel validation text, and live Wave/Stripe stay out of scope.

| Area | What exists | Status |
|---|---|---|
| Catalogs | `histCatalog.js` from `histSchoolRows` / `histProfileRows` / `histPlatRows` / `histPlatRows2` (315 keys, EN/FR/AR), merged last into `appCatalog`. | Implemented |
| School chrome | Admin dashboard quick actions send `{key, href, tone}` (not English labels). Generic `Dashboard` home links send `key`. Reports and exports pages use `t()`. | Tested |
| Profile / 2FA | Profile, password, delete-account, and two-factor settings pages use `t('profile.*')`. | Tested |
| Platform chrome | Dashboard, schools index/show, backups, ops, scale, monitoring, capacity, marketing. Isolation/status option **values** stay English slugs; labels translate. | Tested |
| i18n gate | `scripts/check-phase10-i18n.mjs` | Parity script |

### Phase 10 capability status (honest)

| Capability | Status |
|---|---|
| Historical leftover UI EN/FR/AR + RTL | Implemented, Tested |
| E-mail / SMS delivery | **NOT verified** |
| Wave / Stripe (SaaS) | **Requires External Configuration, NOT Verified** |
| Monthly SaaS billing | Not in this product (annual only since Phase 2) |

### Phase 10 test evidence

* `tests/Feature/Phase10HistoricalI18nTest.php`: **4 passed** (172 assertions) — admin dashboard quick-action keys, generic home keys, reports/exports/profile/2FA 200, eight platform pages + school show 200.
* Full suite: **702 passed, 0 failed** (6955 assertions).
* `node scripts/check-phase10-i18n.mjs` passes (315 keys defined, 357 required, 0 missing, 0 unreferenced).
* `npm run build` passes.
* Browser (Bright Future + platform console, `127.0.0.1:8123`): school admin dashboard quick actions Students / Take attendance / Fees / LMS in EN; AR (`dir=rtl`, `lang=ar`) لوحة التحكم / تسجيل الحضور / نظام التعلم; FR reports Actualiser / Rapports avancés (`dir=ltr`, `lang=fr`). Profile and 2FA in AR. Platform school show in FR (Profil de l’école, Suspendre, Enregistrer les quotas) and AR (ملف المدرسة, تعليق, حفظ الحصص). Status option values stay `active`/`pending`/`inactive`. CTA buttons stayed one line (~40 px, `whitespace-nowrap`). No raw i18n keys on these pages.

### Phase 10 outstanding / honest caveats

* **E-mail and SMS delivery have not been verified.**
* **Wave and Stripe remain Requires External Configuration, NOT Verified.**
* Notification e-mail wording, Laravel validation text, and flash messages from older controllers remain English.
* `Welcome.jsx` is an unused Breeze leftover and was not localised.
* School-entered names, role slugs (`platform admin`), and health codes (`degraded`) are data, not catalog copy.
* Phase 11 follows (metrics lock, CI files, deployment guide).

## Phase 11 — CI/CD and production ops

Protect `/metrics`, add a GitHub Actions workflow, finish the deployment guide. Scale tooling from Phases 5–7 is unchanged and still **does not certify 2M users**.

| Area | What exists | Status |
|---|---|---|
| Metrics | `ProtectMetrics`: Bearer / `X-Metrics-Token` and optional IP allow-list. Production 403 if both empty. `/health` `/ready` public. Query-string tokens rejected. | Tested |
| CI | `.github/workflows/ci.yml` (PHPUnit, phase 6–10 i18n, `npm run build`). Local `npm run ci:i18n`. | Implemented; **not run on GitHub** |
| Deploy | `docs/deployment-guide.md` production cutover + Phase 11 env keys | Implemented |
| Scale UI | Platform Scale page no longer opens `/metrics` in a new tab; EN/FR/AR help text | Tested (browser EN/FR/AR) |
| k6 | Gate 1 smoke accepts `METRICS_TOKEN`. Scripts exist | **Not run** against staging |

### Phase 11 capability status (honest)

| Capability | Status |
|---|---|
| `/metrics` lock | Implemented, Tested |
| GitHub Actions | Implemented as files. **NOT Verified on GitHub** (no git remote here) |
| 2M concurrent users | **NOT certified** |
| E-mail / SMS | **NOT verified** |
| Wave / Stripe | **Requires External Configuration, NOT Verified** |
| Monthly SaaS | Not in this product |

### Phase 11 test evidence

* `tests/Feature/Phase11OpsTest.php`: **8 passed**. Combined with `Phase5ScaleTest`: **15 passed** (60 assertions). Token required, query-string rejected, IP allow-list, production 403, health public, `two_million_certified` false, CI YAML contains PHPUnit/i18n/build.
* Full suite after Phase 11: **710 passed** (6978 assertions), 1815.65s.
* `npm run ci:i18n` passes (phase 6–10 gates; hist catalog 316 keys). `npm run build` succeeded (32.72s).
* `git` and `k6` are **not installed** here. GitHub Actions and Gate 1–3 load runs are therefore **not Verified**.

### Phase 11 outstanding / honest caveats

* **GitHub has not executed CI.** Copy the repo to a host with git to get branch protection and the workflow.
* **k6 Gate 1–3 were not run on production-like staging** in this phase. Do not extrapolate capacity.
* `two_million_certified` stays false until humans review Gate evidence.
* Autoscaling YAML is a sketch. Wire CPU/queue signals on the host.
* Mail/SMS and Wave/Stripe unchanged.

## Report Cards & Transcripts

Dedicated academic reporting on top of existing Students, Academics, Examinations and Records Office. Does **not** replace grading, exams, transcripts compilers, or tenant isolation.

| Area | What exists | Status |
|---|---|---|
| Dashboard | KPIs (students, pending, awaiting approval, published, generated this year), class averages, year/term/level/class/search filters, recent reports table | Tested + browser-checked |
| Termly reports | One report per student per term from **published** exam grades; CA/exam/total/grade/remarks; attendance; comments; behaviour; promotion | Tested + browser-checked |
| Annual reports | Term columns + annual average + grade; school term weights only when all year terms exist; missing terms shown as incomplete, never zero-filled | Tested |
| Transcripts | Lists Records Office Grade 9 / Grade 12 transcripts; “New transcript” opens Records | Tested (compiler) + browser empty-state |
| Final reports | Completion documents; Grade 12 = 8 terms (G10 T1–3, G11 T1–3, G12 T1–2); Grade 9 = 9 terms | Tested |
| Workflow | Draft → Submitted → Under review → Approved → Published; revise supersedes the published snapshot | Tested + browser-checked (Yankuba Bojang RC-2026-000008) |
| PDF / print | Dompdf A4 + Inertia preview; QR verification via existing `/verify/{token}` | Tested + browser preview |
| Templates | Nursery, Primary, Basic Secondary, Senior Secondary defaults; logo/photo/scale/attendance/comments/signatures/stamp/position | Browser-checked |
| Grading | Reads school exam scale and optional term weights (`config/schoolos.php` `academic_reports.term_weights`) | Tested |
| Issuance register | Searchable register of generated reports with status filter | Browser-checked |
| i18n | `reportingCatalog.js` (161 keys, EN/FR/AR + RTL). Nav key `reportCards` | Browser EN/FR/AR |

### Capability status (honest)

| Capability | Status |
|---|---|
| Termly / annual / final generation from published grades | Implemented, Tested |
| Grade 12 eight-term / Grade 9 nine-term rules | Implemented, Tested |
| Approval workflow + published immutability | Implemented, Tested |
| Tenant isolation | Implemented, Tested |
| Student/parent published-only access | Implemented, Tested (PHPUnit) |
| Public document verification (minimal disclosure) | Implemented, Tested |
| PDF generation | Implemented, Tested |
| Dedicated reporting permissions | **Not added** — reuses exams / results / records / students |
| Batch ZIP of PDFs | **Not implemented** |
| Official WAEC certificates | Never — disclaimer on every document |

### Test evidence

* `tests/Feature/AcademicReportsTest.php`: **13 passed** (114 assertions) — dashboard/nav, tenant isolation, published-grades-only + missing subjects, annual missing-term handling, annual weighting, Grade 12 eight-term (no Term 3), Grade 9 nine-term, workflow + immutability, incomplete publish blocked, HTTP generate/PDF/verify, student/parent published-only, class-teacher scope, Dompdf bytes.
* `npm run build` succeeded after UI and `can` (PHP `+` vs `array_merge`) fixes.
* Browser (Bright Future, `admin@brightfuture.test`, `127.0.0.1:8123`): generated Grade 9A Term 1 reports; Yankuba Bojang **RC-2026-000008** Submit → Approve → Publish (avg 88, grade A, attendance 100%); print preview with school letterhead; templates for four programme bands; transcripts empty-state + WAEC disclaimer; issuance register; FR (`Bulletins`, `dir=ltr`) and AR (`بطاقات التقارير`, `dir=rtl`, `lang=ar`). CTA buttons stayed one line.

### Outstanding / honest caveats

* Run `php artisan migrate` on other environments (`2026_11_10_100000_create_academic_reports_tables`).
* Optional: set term weights and enable class position on Templates / Grading.
* Draft Grade 9 / Grade 12 transcripts from Records Office — reporting only lists them.
* Parent/student portals were not live-clicked in this browser pass.
* Full PHPUnit suite was not re-run after this module (reporting tests only).

