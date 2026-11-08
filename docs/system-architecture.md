# System Architecture (current state, post Phase 1)

* **Stack:** Laravel 12, Inertia 2 + React 18, Tailwind, Vite. spatie/laravel-permission 6, Breeze auth, Sanctum present.
* **Data:** SQLite in development (`database/database.sqlite`); session/cache/queue use the database; mail driver `log`.
  Production database (MySQL/PostgreSQL) and queue/cache backends (Redis) are a Phase 9–11 concern — **Requires External Configuration**.
* **Tenancy:** shared database with `school_id` on tenant tables. Tenant identified by host/custom domain or path slug
  (`IdentifyTenant`), re-validated against the signed-in user in `EnsureSchoolContext`. Fail-closed global scope (see `security-implementation.md`).
* **Request pipeline (tenant routes):** `auth → verified → identify.tenant → school.context → permission:<x>` then controller/policy.
  Web group also appends `HandleInertiaRequests`, `RecordTenantUsage`, `SecurityHeaders`, `EnsureTwoFactorVerified`.
* **Platform console:** `/platform/*` behind `auth → verified → platform.admin` (+ `permission:platform.*`), executed in explicit platform mode.
* **RBAC:** `config/rbac.php` → `App\Support\Rbac::sync()` → spatie tables.
* **Access gate:** `App\Services\School\SchoolAccessGate` (suspension / trial / subscription state).
* **Public sites:** `/sites/{school_slug}` and school host domains; login branding via `?school=slug`.
* **i18n:** `resources/js/i18n/*` catalogs for `en`, `fr`, `ar` (RTL for Arabic).
* **Subscription data (legacy, replaced in Phase 2):** `school_subscriptions` still carries `amount_monthly` and `billing_cycle='monthly'`.


## Phase 2 — annual subscription architecture

```
Platform admin --(PricingService::set)--> school_type_prices (append-only, effective-dated)
Registration / provisioning --(SubscriptionLifecycle::startTrial)--> school_subscriptions (1 per school, billing_cycle=annual, status=trial)
Verified payment --(recordVerifiedPayment, DB transaction, row lock)--> billing_payments + school_subscriptions(active, ends_at += 12 months) + billing_events
schoolos:billing:process (hourly) --> expire -> suspend -> reminders (ledger: billing_notifications) -> Notification events update delivery status
SchoolAccessGate (every protected request) <-- reads SubscriptionLifecycle::effectiveStatus (calendar based, ends_at canonical)
```

* Tables: `school_type_prices`, `school_subscriptions` (extended), `billing_payments`, `billing_events` (audit), `billing_notifications` (reminder ledger).
* School-scoped tables use `BelongsToSchool`; the scheduler and platform controllers run via explicit `runAsPlatform`; queued notifications carry plain data only.
* Access is never granted or removed by deleting data — only by status/`ends_at`.
* Streamed responses execute after the request pipeline: any export must re-enter platform mode inside the stream callback (see `Platform\BillingController::export`).


## Phase 3 - payment architecture

```
School --(PaymentController)--> PaymentService::initiate --> billing_payments (pending) [+ PaymentGateway::createCheckout for Wave/Stripe]
        Cash:  finance --confirmCash--------------------------\
        Bank:  school evidence -> pending_verification -> finance --approveBank--\
        Wave/Stripe: provider --webhook--> WebhookProcessor --(verify signature, claim idempotency row, re-fetch from provider)--\
                     browser return / "Check status" / schoolos:billing:reconcile --> PaymentService::refresh -------------------/
                                                                                             v
                                         SubscriptionLifecycle::recordVerifiedPayment (full amount only, row lock) --> subscription active + receipt + billing_events
```

* Gateways implement `App\Services\Billing\Gateways\PaymentGateway` (`StripeGateway`, `WaveGateway`); signature checks share `VerifiesSignedWebhooks`.
* Tables added: `billing_webhook_events`, `platform_bank_accounts`; `billing_payments` and `billing_events` extended (additive migration `2026_11_03_300000_create_phase3_payment_workflows`).
* `PaymentReconciler` (`schoolos:billing:reconcile`, every 15 minutes) re-checks open gateway sessions and expires stale manual payments; `report()` feeds *Platform > Payments > Reconciliation*.
* Public webhook route is registered outside the web group (no session/CSRF) in `bootstrap/app.php`.
* Frontend: school `Billing/Index`, `Billing/Payment`, `Billing/Receipt`; platform `Platform/Payments/{Index,Show,Reconciliation,Settings}`; catalog `paymentsCatalog.js`.

## Phase 4 - onboarding architecture

* `RegisterSchoolController` validates, then calls `SchoolProvisioner` (single transaction, `TenantContext::runForSchool`) which creates the school, administrator, trial subscription (`SubscriptionLifecycle::startTrial`) and website scaffold. It returns `school`, `admin`, `review`, `flags`, `trial_ends_at`.
* `DuplicateDetector` runs before provisioning: hard matches throw `DuplicateInstitutionException`; soft matches are returned as flags and stored in `school_registration_reviews` (pending) with a `PlatformAlert` (source `registration`).
* `SetupWizard` is the single source of step state (`school_setup_steps`: done / skipped / todo). Steps in `DATA_DRIVEN` are done only when real records exist. `schools.setup_completed_at` ends the redirect from `/dashboard`.
* `StaffInvitationService` + `StaffInvitationController` implement hashed single-use invitations; accepted users are created inside the school's tenant context.
* Platform side: `RegistrationReviewController` (clear / confirm duplicate) behind `platform.schools.manage`.
* New tables: `departments`, `school_setup_steps`, `school_registration_reviews`, `staff_invitations`; new `schools` columns `registration_number`, `district`, `terms_accepted_at`, `terms_version`, `setup_completed_at`. Tenant-scoped tables use `BelongsToSchool`.

## Phase 5 - academic and student architecture

* **Services** (all tenant-scoped through `BelongsToSchool`): `Students\StudentNumberGenerator` (per-school `school_sequences` row, locked update), `Students\StudentRegistrar` (create, guardian link, quota and capacity checks), `Students\StudentMovementService`, `Admissions\AdmissionWorkflow` (transition table, enrolment), `Academics\TeacherScope`, `Academics\TimetableValidator`, `Academics\AcademicCalendarService`, `Attendance\AttendanceService`, `Results\ResultsService`.
* **Error convention:** domain refusals throw `AcademicRuleException::because('err.xxx')`; `HandlesAcademicRules::attempt()` turns them into a flash `error` (`err.*`) or `success` (`ok.*`). The browser translates the code (`resources/js/lib/academic.js::messageText`), so the server never sends localised prose.
* **Data model additions:** `school_sequences`, `student_documents`, `student_movements`, `class_subjects`, `academic_events`, `attendance_corrections`, `staff_attendance`, `periods`, `teacher_unavailabilities`, `grading_scales`, `grading_bands`, `assessment_categories`, `grade_scores`, `grade_changes`, plus columns on `students`, `admission_applications`, `departments`, `subjects`, `attendance_records`, `timetable_slots`, `examinations`, `grades`.
* **Workflows:** admissions (`submitted -> under_review -> accepted | waitlisted | rejected | withdrawn -> enrolled`), attendance corrections (`pending -> approved | rejected`), timetable (`draft -> published`, all-or-nothing), results (`draft -> submitted -> approved -> published`, correction only by a publisher with a logged reason).
* **Portal identity:** `User::isStaffPortal / isLearnerPortal / isGuardianPortal / isFamilyPortal`; staff roles are evaluated first so administrators are never treated as learners.
* **Routes:** about 80 entries: `students.*` (16), `admissions.*` (6), `academics.*` (24), `attendance.*` (10), `timetable.*` (9), `examinations.*` (13) and the public `sites/{slug}/apply` pair. `/examinations/settings` is registered before `/examinations/{examination}`; id parameters use `whereNumber`.
* **Frontend:** pages under `Pages/{Students,Admissions,Academics,Attendance,Examinations}` and `Pages/School/Apply`; shared `Components/SchoolOS/Form.jsx` and `Components/School/ApplicationFields.jsx`; catalog `i18n/academicCatalog.js` (rows in `academicRows1-3.js`) merged into `appCatalog.js`.

## Phase 6 - external examinations, Senior Secondary and Records Office architecture

* **Services:** `ExternalExams\ExternalExamService` (sessions, registration, index numbers, results workflow), `ExternalResultImporter` (queued CSV), `CollectionService` (collection register, certificate tracking, reminders); `SeniorSecondary\RulesEngine` (pure, data-driven aggregate / eligibility / ranking) and `SeniorSecondaryService` (rounds, applications, decisions, events); `Records\TranscriptCompiler` (9 / 8 term layouts), `DocumentService` (workflow, issuance register), `DocumentPdf` (dompdf + QR), `RecordsSettings`, `SchoolSequence` (per-school reference numbers).
* **Data model:** `external_exam_sessions`, `external_exam_subjects`, `external_candidates`, `external_candidate_subjects`, `external_candidate_documents`, `external_results`, `external_result_changes`, `result_collections`, `certificate_records`, `ss_rounds`, `ss_applications`, `ss_application_results`, `ss_application_documents`, `ss_application_events`, `student_term_records`, `records_templates`, `records_documents`, `records_document_events`.
* **Workflows:** results (`entered -> verified -> published`, correction with reason), Senior Secondary (`submitted -> under review -> admitted | waitlisted | rejected`, human decision with audited override), documents (`draft -> submitted -> approved -> issued -> reprinted | revoked`), certificates (`awaiting -> received -> available -> collected | outstanding`).
* **Scheduler:** `schoolos:records:remind` daily at 07:30 (without overlapping).
* **Routes:** 70 entries - `external-exams.*` (29), `senior-secondary.*` (13), `records.*` (26), public `school.senior.apply` and `/verify/{token}`. Defined in `routes/phase6.php`, included inside the authenticated tenant-bound group; id parameters use `whereNumber`.
* **Middleware:** `bootstrap/app.php` prepends `EnsureTwoFactorVerified`, `IdentifyTenant` and `EnsureSchoolContext` ahead of `SubstituteBindings` in the priority list.
* **Frontend:** pages under `Pages/{ExternalExams,SeniorSecondary,Records,Verify}` and `Pages/School/SeniorApply`; shared `ReasonAction`; catalog `i18n/recordsCatalog.js` (rows in `recordsRows1-3.js`) merged into `appCatalog.js`.

## Phase 7 - LMS architecture

* **Single platform.** Courses are tenant-scoped models (`school_id` + `SchoolScope`, fail-closed). They link to existing `subjects`, `school_classes`, `students`, `users` and the Phase 5 teacher allocations (`TeacherScope`). There is no second user store.
* **Access layer.** `App\Services\Lms\LmsAccess` decides manager / teacher / learner for a course; controllers call `authorize()`, which aborts 403. `lessonVisible()` and `quizVisible()` hide drafts, scheduled and class-restricted content from learners.
* **Services** (`app/Services/Lms`): `CourseService` (courses, sections, lessons, enrolment, files), `AssignmentService`, `QuizService` (attempts, timing, grading, release), `AnswerMatcher`, `GradebookService`, `ProgressService`, `CommunityService`.
* **Quiz engine.** Random bank pulls are copied into attempt-owned `quiz_questions`, so a bank edit never changes a taken quiz. The server owns the clock: `due_at = min(started_at + limit + 20 s grace, closes_at)`; late answers are discarded. Taking payloads omit `correct_answer`; review payloads include it only when `QuizService::answersVisible()` allows it.
* **Files.** Private disk (`SCHOOLOS_LMS_DISK`), served only through `FileController` after an access check; never public URLs.
* **Routes.** `routes/phase7.php` (80 routes, `lms.*`) included from `routes/web.php`; middleware priority in `bootstrap/app.php` binds the tenant before route-model binding.
* **Frontend.** `resources/js/Pages/Lms/*` (23 pages), `Components/Lms/{LmsLayout,QuestionForm,QuestionSummary}`, `lib/lms.js`; copy from `i18n/lmsCatalog.js`.
* **Scheduler.** `schoolos:lms:announce` (every 10 minutes) delivers scheduled announcements.
* **External content.** A link-only hook (`lms/external`): tools and packages store a name and an http(s) URL; lessons can open a URL. It is **not** a SCORM runtime or an LTI platform.

## Phase 8 - portals and communication architecture

* **One identity model.** Staff, learners and guardians are `users` of one school, told apart by role permissions (`portal.admin|teacher|student|parent`). School administrators hold every portal permission, so audience and "kind" logic always asks "has `portal.student` **and is not staff**" (the same rule as `User::isLearnerPortal()` / `isGuardianPortal()`), never "has the permission".
* **Provisioning.** `App\Services\Accounts\AccountService` (suspend, reinstate, revoke, restore, reset) and `StaffInvitationService` (`inviteStaff|inviteStudent|inviteGuardian`, accept) share the `staff_invitations` table (`kind` = staff, student or guardian). Accepting links the new user to the `students` / `guardians` row and verifies the e-mail. `EnsureAccountActive` is the first middleware in the web group.
* **Messaging.** `App\Services\Messaging\MessagingService` is the only place that decides who may talk to whom (`canMessage`, `contacts`, `startDirect`, `startGroup`, `canPost`, moderation). Controllers never repeat the rules. Tables: `conversations`, `conversation_participants` (read pointer, mute), `messages`, `message_attachments`, `message_reports`. Attachments live on the private disk and are streamed after `canView`.
* **Announcements.** `App\Services\Communications\AnnouncementService`: `save` (draft / publish / schedule), `publish` (idempotent claim, frozen `announcement_recipients`, one `SchoolAnnouncement` notification), `publishDue` (scheduler, platform-wide, each school bound separately), `feed`, `markRead`, `stats`.
* **Notifications.** Notifications use the `RespectsPreferences` trait: `category()` + `offeredChannels()`; `NotificationPreferences::channels()` decides the real channels (mandatory categories, verified e-mail, SMS opt-in, school SMS switch, usable phone). `RecordNotificationDelivery` (listener on `NotificationSent`, `NotificationFailed`, `JobFailed`) writes `notification_deliveries`; SMS has its own `sms_messages` log.
* **SMS.** `App\Services\Sms`: `SmsService` (queue, deliver, retry, `applyReport`), gateways (`LogGateway`, `NullGateway`, `TwilioGateway`, `AfricasTalkingGateway`), `PhoneNumber`, `SmsTemplates`, `SendSmsJob` (back-off and attempt limit), `SmsChannel`. Webhooks: `POST /webhooks/sms/twilio` (HMAC-SHA1 signature) and `POST /webhooks/sms/africastalking/{secret}`, stateless routes registered in `bootstrap/app.php`.
* **Tenancy gotcha.** With a synchronous queue, `JobProcessed` clears the tenant; services that notify therefore re-bind the school afterwards. `User` is not tenant-scoped, so user look-ups add `school_id` explicitly.
* **Routes.** `accounts.*` (`users.manage`), `communications.*` (`communications.manage`), `messages.*` (`messages.use`; moderation `messages.moderate`), `announcements.*`, `notifications.*`, two SMS webhooks.
* **Frontend.** `Pages/{Accounts,Messages,Announcements,Notifications,Communications}`, rewritten `Teacher|Student|Parent/Dashboard`, `Components/Comm/AnnouncementList`, `lib/comm.js`; sidebar count badges come from the shared `badges` prop; copy from `i18n/commCatalog.js`.
* **Scheduler.** `schoolos:announcements:publish` every minute.

## Phase 8 follow-up - platform credentials console architecture

* **Scope.** One platform-wide set of mail (SMTP) and SMS-provider credentials; schools never touch them. Stored in `platform_settings` (`integrations.mail`, `integrations.sms`) as JSON: `fields` (secrets encrypted per field), `fingerprint`, `enabled`, `tested`, `confirmed`, `updated_*`.
* **`App\Services\Integrations\IntegrationSettings`** (singleton). `status()` for the page, `save()`, `test()`, `activate()`, `deactivate()`, `confirm()`, `clear()`, and `apply()`. The fingerprint is an HMAC (application key) of the fields that matter for the chosen provider, so the test result, activation and confirmation are all bound to the exact values.
* **Applying.** `apply()` runs after boot and on `JobProcessing`; it overlays `mail.default|mailers.smtp|from` and `schoolos.sms.driver|twilio|africastalking` **only for ACTIVE rows whose fingerprint matches**, remembers the server default it replaced (restored when switched off or cleared) and purges the mail manager when the applied state changes. Because it only touches keys it pushed itself, tests and `.env` overrides are untouched when nothing is active. The raw rows are cached under `schoolos.integrations.v1` (ciphertext only) and dropped on every write.
* **Testing.** `test()` pushes the saved values temporarily, sends one `IntegrationTestMail` (mail) or one SMS through the real gateway class (SMS), then restores the config; the outcome is stored with the fingerprint and a masked target.
* **UI and routes.** `Platform\IntegrationController` (`platform.integrations.*`, permission `platform.integrations.configure`), page `Pages/Platform/Integrations/Index.jsx`, nav entry shown only to those who may use it (`PlatformNav`), copy from `i18n/integrationsCatalog.js`.
* **Not covered.** Wave/Stripe keys, per-school credentials, and any attempt to read `.env` values (the console only reports the default driver and SMTP host).

## Phase 9 - finance and operations architecture

* **School fees ≠ SaaS billing.** `app/Services/Finance` (`InvoiceService`, `PaymentService`, `AdjustmentService`, `FinanceReports`, `ReceiptPdf`) and `Fees/*` controllers. Routes `fees.*`. School payment methods are rows on the school, not `IntegrationSettings`.
* **Shared ops helpers.** `App\Services\Ops\Ops` (audit, numbering, CSV, notify, `usersWith`), `OpsSettings` (`schools.settings.ops.<group>` with defaults in `config/schoolos.ops`), `TermCharge::ensure` (idempotent pending `student_fees` keyed by `source`).
* **HR.** `app/Services/Hr` + `Hr/*` controllers; routes in `routes/phase9_hr.php`; self-service `my.leave.*`, `my.payslips.index`.
* **Library.** `LibraryService` + `LibraryController`; `library.loans.store` still accepts `library_book_id`.
* **Inventory / assets.** `InventoryService` (signed ledger, never below zero, one-shot low-stock notify), `AssetService` (assign / return / retire / maintenance). Routes `inventory.*`.
* **Procurement.** `ProcurementService` draft→submit→decide→PO→receive→invoice→pay. `ProcurementAccess::tabs` is server-decided. Self-approval from `ops.procurement.self_approval`.
* **Transport / boarding.** `TransportService` / `BoardingService`; original store route names kept (`transport.vehicles.store`, `boarding.hostels.store`, …). Term billing writes `student_fees` with sources `transport:{routeId}:{termId}` and `boarding:{hostelId}:{termId}`.
* **Safeguarding.** `SafeguardingService`; list omits summary; `SensitiveText` + `$hidden`; `logAccess` on show. Report route is `safeguarding.report`; manage-only index/show.
* **Events.** `EventService` (venue clash, publish/cancel/complete, register/drop/attendance). Viewers see published events only.
* **Routes.** `routes/phase9_ops.php` required from the tenant auth group in `web.php`.
* **Frontend.** `Pages/{Fees,Hr,Library,Inventory,Procurement,Transport,Boarding,Safeguarding,Events}`; module layouts; copy from `financeCatalog.js`, `hrCatalog.js`, `opsCatalog.js`.
* **i18n gate.** `scripts/check-phase9-i18n.mjs`.

## Phase 10 — leftover historical i18n

* **Catalog.** `resources/js/i18n/histCatalog.js` (`histSchoolRows`, `histProfileRows`, `histPlatRows`, `histPlatRows2`) merged last into `appCatalog`.
* **PHP keys.** `Admin\DashboardController` quick actions and the fallback `DashboardController` home links emit stable `key`s; JS displays `t('dash.admin.quick.' + key)` / `t('dash.home.link.' + key)`.
* **Surfaces.** Admin dashboard, generic home, reports, exports, profile/2FA, platform dashboard/schools/backups/ops/scale/monitoring/capacity/marketing.
* **i18n gate.** `scripts/check-phase10-i18n.mjs`.
* **Out of scope.** School-entered content, notification mail bodies, Laravel validation text, live Wave/Stripe.

## Phase 11 — CI and production ops

* **`/metrics`.** `ProtectMetrics` middleware. Token (`Authorization: Bearer` or `X-Metrics-Token`) and/or IP allow-list. Production fail-closed when both empty. `/health` and `/ready` stay public.
* **CI.** `.github/workflows/ci.yml` (PHPUnit, phase 6–10 i18n gates, `npm run build`). Local: `npm run ci:i18n`, `php artisan test`.
* **Deploy.** `docs/deployment-guide.md` production cutover. `SCHOOLOS_METRICS_TOKEN` / `SCHOOLOS_METRICS_ALLOW_IPS` in `.env.example`.
* **Capacity.** `schoolos.capacity.two_million_certified` remains **false**. k6 Gate 1–3 are scripts, not a passed staging run in this environment.
