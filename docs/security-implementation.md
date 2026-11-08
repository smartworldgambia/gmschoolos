# Security Implementation (Phase 1)

## Tenancy (fail-closed)

* `App\Support\TenantContext` (singleton) holds the current school and an explicit *platform mode* depth.
* `App\Models\Scopes\SchoolScope` (global scope on every `BelongsToSchool` model):
  * tenant bound → `WHERE table.school_id = :tenant`
  * platform mode (`runAsPlatform()` / trusted artisan process) → no filter
  * neither → `WHERE 1 = 0`
* `BelongsToSchool::creating` throws if a row would be created without an owner (no school_id, no tenant, not platform mode).
* `runForSchool($school, fn)` binds one school for provisioning/jobs and restores the previous state.
* Never fix a tenant problem by removing the scope — bind the tenant or use an explicit platform path.
* Queue workers: tenant context is cleared on `JobProcessing/Processed/Failed`; jobs bind their own school.

## Authentication and verification

* Open self-registration is disabled. Accounts come from school provisioning, invitations or admin-created users.
* `User implements MustVerifyEmail`; `verified` middleware guards tenant and platform routes.
* Two-factor secrets/recovery codes are not mass-assignable. Privileged roles (`config/rbac.php → two_factor_roles`)
  must enrol when `schoolos.security.require_two_factor_privileged` is true (default true in production).

## Authorisation

* `config/rbac.php` defines permissions (platform + school scope), roles, `platform_roles`, `two_factor_roles`.
* `php artisan schoolos:sync-rbac` (also run by the seeder) idempotently syncs to spatie/laravel-permission.
* Routes use `permission:<name>` middleware; controllers/policies use `can()`; **no authorisation by role name**.
  Legacy role names (`school_admin`, `teacher`, `student`, `parent`, `platform_admin`) are retained and mapped to permissions.
* `portal.admin|teacher|student|parent` permissions drive dashboards and navigation (`SchoolNav` is permission-filtered).
* Platform staff = `school_id IS NULL` + a platform role; they use `/platform/*` (gate: `platform.access`) and cannot browse tenant routes.

## School access gate

`App\Services\School\SchoolAccessGate::blockReason()` → `suspended | trial_expired | subscription_expired | payment_overdue | null`.
Enforced in `EnsureSchoolContext`; blocked users get HTTP 403 with `Errors/AccessBlocked`. Billing routes
(`config('schoolos.access.always_allowed_routes')`) stay reachable for the Phase 2 payment pages. Records are never deleted.

## Browser hardening

`SecurityHeaders`: CSP with per-request nonce (`script-src 'self' 'nonce-…'`, `object-src 'none'`, `base-uri 'self'`,
`frame-ancestors 'self'`, `form-action 'self'`), `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP, optional HSTS.
Configure via `SCHOOLOS_CSP_MODE`, `SCHOOLOS_CSP_EXTRA_SCRIPT_SRC`, `SCHOOLOS_CSP_EXTRA_CONNECT_SRC`, `SCHOOLOS_CSP_REPORT_URI`.
Known allowances: `style-src 'unsafe-inline'` (React inline style attributes for brand colours), `img-src https:`, `frame-src https:` (maps, LTI/SCORM tools).

## Audit

Denied tenant access → `Log::warning('security.access_denied')` and, when a school is known, an `activity_logs` row.


## Phase 2 — billing security controls

* Permissions: `platform.pricing.manage` (set prices), `platform.payments.verify` (record verified payments), `platform.subscriptions.manage` (change school type),
  `platform.finance.view` (dashboard/export), `subscriptions.view`/`subscriptions.manage` (school side). `platform_support` cannot record payments; school roles cannot reach platform billing.
* Payment integrity: full-amount-only activation, unique transaction reference per method, row-locked transaction, price snapshot per payment, all transitions audited in `billing_events`.
* The billing cycle is not mass-assignable and is forced to annual on save; unknown statuses throw.
* Tenant isolation: school billing pages only read the caller's school (tested); the CSV export neutralises spreadsheet formula injection.
* Blocked schools keep read access to `billing.*` / `subscription.*` / `logout` only; everything else returns the 403 access page and is audited.
* No payment secrets exist in the repository; `SCHOOLOS_PAY_*` flags are booleans only.


## Phase 3 - payment security controls

* **Fail-closed activation.** Only `SubscriptionLifecycle::recordVerifiedPayment()` can activate; the browser return URL, query strings, uploaded receipts and unverified references are never inputs to that decision.
* **Webhooks:** HMAC verified over the raw body with constant-time comparison and a replay window; invalid signature -> 400 and nothing recorded; idempotency table; the payment is re-fetched from the provider before any state change; Wave strategy cannot be downgraded. The endpoint is stateless (no session/CSRF), throttled, and returns 404 when the provider is off.
* **No downgrade / no silent change:** amount, currency and reference must match what was created; a mismatch fails the payment and escalates. The charged amount shown to the school must be the amount submitted.
* **Secrets:** keys come only from the environment, are never returned to the browser, never stored in the database, and the settings page shows no secret. Only a payload hash is stored for webhooks. No card data is stored.
* **Evidence uploads:** server-side content sniffing, allow-list (PDF/JPG/PNG), size cap, random storage name on a private disk, tenant-prefixed path, download-only with `nosniff` and `default-src 'none'`, every view audited, filename never trusted.
* **RBAC:** `platform.payments.verify` (confirm/reject/re-check/reconcile), `platform.payments.configure` (bank accounts, platform_admin only), `platform.finance.view` (read), `subscriptions.manage` (school start/cancel/upload). Tested for support, auditor, teacher, accountant and cross-school users.
* **Tenant isolation:** school payment lookups go through `SchoolScope`; another school's reference is a 404. Platform actions re-enter the school context to write audit rows.
* **Concurrency:** payment rows are locked in the settle/evidence transactions; duplicate transaction references per method are refused; webhook claims rely on a unique index.
* **Audit:** every transition writes `billing_events` with actor and payment id (initiated, session created, evidence uploaded/viewed, verified, rejected, cancelled, expired, failed, mismatch, refunded, refused confirmation, settlement error, re-checked).
* **Export:** CSV neutralises formula-injection prefixes and re-enters platform mode inside the stream callback.

## Phase 4 - onboarding security controls

* **Registration abuse:** `throttle:10,1`; guests only; terms agreement required and versioned; subdomain reserved-word and format rules; logo restricted to images under 2 MB.
* **Repeat trial claims:** duplicate detection (block + flag), platform review queue, confirmation suspends the school and ends the trial. Duplicate errors never reveal which school matched. Records are never deleted.
* **No privilege through registration or invitation:** the registrant becomes `school_admin` of the new school only; invitations can only grant roles in `schoolos.onboarding.invitable_roles` (no admin, owner or platform roles).
* **Invitation tokens:** 64 random characters, only the SHA-256 hash stored, single use (row locked on accept), expiry, revoke, resend rotates the token; every unusable token returns the same HTTP 410 page (no enumeration).
* **Tenant isolation:** all wizard writes run under the tenant context; `SetupController` aborts 403 if the user's school differs from the tenant; levels/classes/subjects/invitations of another school return 404; covered by tests.
* **Authorisation:** wizard requires `school.settings.manage`; review queue requires `platform.schools.view/manage`.
* **Destructive actions guarded:** removal of levels, classes and subjects is refused when dependent records exist.
* No global scope or middleware was weakened.

## Phase 5 - academic and student security controls

* **Tenant isolation:** every new model uses `BelongsToSchool`; request validation uses tenant-aware `exists` rules, so a class, subject, student or examination id from another school is rejected or returns 404 (tested for classes, curriculum, promotion, exams and students).
* **Least privilege:** new permissions `attendance.record / approve / staff`, `exams.mark / manage`, `results.publish`, `admissions.manage`, `academics.manage`, `students.view_sensitive`. Teachers are limited by `TeacherScope` to their classes and allocated subjects and fail closed when nothing is allocated. Students and guardians never gain staff permissions and only see their own records and **published** results.
* **Privilege-escalation defect fixed:** administrators hold all four `portal.*` permissions, so permission checks on a portal permission alone treated them as learners or guardians. Identity helpers on `User` now decide, and the policies, fee and invoice controllers, attendance and timetable use them.
* **Sensitive data:** medical, welfare and religion fields are returned only to holders of `students.view_sensitive`. Photos and documents are stored on the private disk under random names, validated by type and size, and served only through authorised routes; downloads and removals are audited.
* **Public application form:** published schools only, consent required, honeypot field, throttle and a repeat-submission guard; it can create only a reviewable application, never a student or an account.
* **Integrity of records:** student numbers are permanent and cannot be edited; import never overwrites an existing record; attendance has one row per student, date and session, cannot be recorded for the future, and is locked after the edit window so changes need an approved correction; movements, grade changes and corrections are append-only history.
* **Results:** marks are editable only in draft by the allocated teacher; submission locks them; publication needs `results.publish`; a published result can change only through a logged correction (reason of at least 5 characters, old and new values, actor).
* **Uploads and CSV:** CSV is parsed server-side with a row limit and per-row validation; formulas are not evaluated and error text is rendered as text.
* **Not covered:** application fees, SMS delivery, and external-examination data are out of scope for this phase.

## Phase 6 - external examination, admissions and records security controls

* **Tenant isolation:** all 18 new tables use `BelongsToSchool` (tenant-aware validation for ids); another school's session, candidate, result, round, application or document returns 404 (tested for each). **Defect found and fixed:** `SubstituteBindings` ran before the tenant was bound, so bound tenant models returned 404 in a real browser while tests passed because they pre-bound the tenant. The priority list in `bootstrap/app.php` now orders `EnsureTwoFactorVerified -> IdentifyTenant -> EnsureSchoolContext -> SubstituteBindings`; regression tests clear the tenant first and fail without the fix.
* **Least privilege:** `external_exams.view / manage / verify`, `senior_admissions.view / manage`, `records.manage / issue`. Teachers and learners cannot reach these routes (403 tested). Verification of results needs `external_exams.verify`; issuing documents needs `records.issue`.
* **Integrity of official identifiers:** official index numbers are never trimmed or generated, must be unique per session, cannot look like internal references, and changes need a permission, a reason and a history row. Result changes and application decisions are append-only.
* **Documents and files:** candidate documents, application documents and stamps are stored on the private disk under random names, validated by type and size, and served only through authorised routes. CSV is parsed server-side with a row limit; formulas are not evaluated.
* **Public surfaces:** the Senior Secondary form accepts published schools only, requires consent, has a honeypot, throttle and repeat guard, and can create only a reviewable application. The verification page is throttled (`SCHOOLOS_RECORDS_VERIFY_THROTTLE`), uses an unguessable random token, treats unknown and draft tokens identically, and discloses only type, reference, school, issue date, valid/revoked state and the holder's initials.
* **Anti-misrepresentation:** every school transcript, testimonial and letter states it is a school record, not an official WAEC result or certificate.
* **Not covered:** email delivery of reminders is not verified; there is no WAEC integration; PDF signing (cryptographic) is not implemented - authenticity relies on the verification URL.


## Phase 7 - LMS security controls

* **Tenant isolation:** every LMS model uses the fail-closed `SchoolScope`; tests confirm a user of school B cannot see or open school A's courses, lessons, quizzes, attempts, files, threads or gradebook.
* **Role checks on every action:** manager / course teacher / enrolled learner via `LmsAccess`; a teacher of another course gets 403; a learner not enrolled (or enrolled in a draft course) gets 403; a draft or class-restricted lesson is 404 to learners.
* **Quiz integrity:** correct answers are not in the page props while taking (tested by inspecting the payload); results and correct answers appear only per the release rules; attempts are owned by the student (another learner cannot save or submit them); the deadline is enforced server-side; one running attempt; attempt limits; question ids not belonging to the attempt are ignored; a quiz with attempts cannot be edited or deleted.
* **Submissions and files:** private disk, file-type allow-list and size limits, served via an authorised controller; learners can open only their own submission; file counts are capped (`SCHOOLOS_LMS_MAX_SUBMISSION_FILES`).
* **Gradebook privacy:** learners see only their own rows and only released quiz marks; CSV export is staff only and neutralises spreadsheet formula injection.
* **External links:** only `http://` and `https://` are accepted when stored and again when launched (no `javascript:` or `data:` URLs); the launch route checks course access (found and fixed during this phase).
* **Forum:** posts are escaped by React; a staff member can hide a post; learners cannot post in a course they are not enrolled in.
* **Validation:** every foreign key is validated with the tenant-aware `inSchool()` rule so ids from other schools are refused.
* **Audit/Messages:** user-facing errors are stable codes (`err.lms.*`) translated on the client, so no internal text leaks.

## Phase 8 - portals and communication security controls

* **Tenant isolation:** every new model is fail-closed (`SchoolScope`); tests confirm administrators, teachers and parents of one school cannot see, edit, cancel, retry or report anything of another school (404). Announcement class ids and invitation allocation ids are validated with the tenant-aware `inSchool()` rule.
* **Account lifecycle:** suspending or revoking ends live sessions at once (`EnsureAccountActive`), blocked users cannot log in, reset links are e-mailed and never shown to the administrator, protected accounts (owner / administrator) and one's own account cannot be suspended or revoked, restoring access never grants a role that does not fit the person, privileged roles cannot be invited, one learner or guardian record can have only one login, and every sensitive action is written to the audit log with the actor and reason.
* **Parents see only linked children:** the parent dashboard, student profile and messaging all start from the guardian record's linked students; another family's child gives 403. Only released results are shown to families.
* **Messaging permissions are server-side:** the permission matrix (staff / guardian / learner, class scope, school switches, blocked and other-school accounts) lives in one service and is tested; a recipient id typed into the browser cannot bypass it; families can never message each other; class groups hide other families' names; outsiders get 404 (not 403 or a redirect) when opening, replying to or reporting a conversation.
* **Moderation without surveillance:** moderators can open a conversation only when it has an open report; each view writes `messages.moderator_view` to the audit log; hiding a message also hides its files; hide / lock / dismiss require a report.
* **Attachments:** private disk, random file names, allow-list of types, size and count limits, served only to authorised viewers with `X-Content-Type-Options: nosniff` and no caching. Message bodies are stored and rendered as plain text.
* **SMS safety:** per-school switch (off by default), daily cap, numbers validated to E.164 before any provider call, template variables stripped of control characters (no second message can be smuggled in), SMS log shows masked numbers and never the text, secrets only in `.env`.
* **Webhooks:** Twilio callbacks are rejected (403) without a valid `X-Twilio-Signature` (also when the token is empty or the body was tampered with); Africa's Talking callbacks need the long path secret; reports for unknown references are ignored; a report cannot touch another provider's message and can never downgrade `delivered`.
* **Notification privacy:** the centre, preferences and history only ever read the caller's own rows; nobody can mark someone else's notification read; message e-mails and SMS name the sender only, never the message text; unverified addresses receive no e-mail; account/security notices cannot be switched off.
* **Announcements:** teachers can address only classes they teach; families cannot author; published announcements are frozen (cancel instead); recipient lists are frozen at publication; suspended and revoked accounts are excluded.
* **Messages shown to users** are stable codes (`ok.*`, `err.*`) translated on the client, so no internal text leaks.

## Phase 8 follow-up - platform credentials console (security controls)

Supersedes the Phase 8 line "secrets only in `.env`": mail and SMS secrets may now also live in the database, encrypted.

* **Encryption at rest:** each secret field is `Crypt::encryptString` (application key). Tests assert the stored JSON never contains the plain secret. The key and the database are separate secrets; both are needed to read a credential.
* **Never returned:** the Inertia props contain only "set" and, for secrets of 16+ characters, the last four characters. Tests scan the rendered HTML for the secret values. Provider errors are stored with every configured value redacted and capped at 300 characters.
* **Who:** only `platform.integrations.configure` (platform and super administrators). All routes (view, save, test, switch, confirm, remove) sit behind it; school users, finance, support, operations and auditor roles get 403. Not tenant data: nothing is readable from a school context.
* **Re-authentication:** save, switch on, switch off and remove validate `current_password`; saving is throttled (20/min) and the test action is throttled (6/min) so it cannot be used as a mail or SMS relay.
* **Safe activation:** only values with a successful test of exactly those values (HMAC-SHA256 fingerprint keyed with the application key) can go live; a row edited by hand in the database (flipped `enabled`, swapped host) fails the fingerprint check and is ignored. Changing a live value switches it off and the `.env` default takes over.
* **Trail and notice:** every action writes an `ActivityLog` row (`platform.integration.*`, field names only) and the other platform administrators receive an e-mail; a failure to send the notice never blocks the change.
* **Input limits:** SMTP host must match `[A-Za-z0-9.-]+` (no URLs or schemes), port 1-65535, encryption `tls|ssl`, webhook path secret `[A-Za-z0-9_-]`, callback URL must be a URL, phone numbers are normalised before any provider call.
* **Residual risk:** an attacker holding a platform-administrator session **and** that administrator's password could redirect mail. Keep 2FA enforced in production, keep few platform administrators, and watch the notice e-mails and the "Recent changes" list.

## Phase 9 - finance and operations security controls

* **Tenant isolation:** every new Phase 9 model uses fail-closed `BelongsToSchool`. Foreign ids are validated with `inSchool()`. Tests cover inventory locations, procurement suppliers, transport learners, boarding hostels, safeguarding cases and events.
* **Finance vs SaaS:** school fee methods and receipts are school-scoped (`fees.*`, `payments.*`). They do not read or write platform Wave/Stripe credentials.
* **Procurement four-eyes:** request and invoice approval refuse the same actor unless `ops.procurement.self_approval` is on. Approving a supplier invoice also requires received goods whose value covers the amount.
* **Inventory:** stock cannot go below zero. Low-stock notify fires once until the item recovers above the reorder level.
* **Safeguarding least disclosure:** `safeguarding.report` may file a concern; only `safeguarding.manage` may list or open a case. Index payloads omit `summary` / `subject_name`. `SafeguardingCase` hides those columns by default and encrypts them. `logAccess` writes `sg.accessed` with `case_id` only. Assign only to a school user who holds `safeguarding.manage`.
* **Boarding gender:** a male/female hostel refuses a learner of the other gender (mixed is allowed). Welfare notes require an active allocation.
* **Transport:** one active assignment per learner; vehicle capacity is enforced when it is greater than zero.
* **Events:** unpublished drafts are hidden from `events.view` (404 on show). Venue overlap with any non-cancelled event is refused.
* **Messages shown to users** are stable `ok.*` / `err.*` codes translated on the client.
