# Known Limitations (as of end of Phase 1)

1. **Billing is still monthly in the data model** (`amount_monthly`, `billing_cycle='monthly'`, plan `price_monthly`, "/mo" label on the sign-up page).
   Replacing this with annual-only billing, a 30-day trial and the four payment methods is **Phase 2** — not started.
2. **No git remote in this environment:** `.github/workflows/ci.yml` is in the tree; GitHub has not executed it here.
3. **Mail:** driver is `log`; verification and welcome emails are not delivered anywhere real. SMS only logs. (Requires External Configuration.)
4. **Scale:** k6 scripts and capacity recording exist. The two-million-user target is **not claimed or certified**.
5. **CSP:** `style-src 'unsafe-inline'`, `img-src https:` and `frame-src https:` are intentionally permissive (inline brand colours, remote photos, maps/LTI embeds). Tighten per deployment with `SCHOOLOS_CSP_*`.
6. **Authenticated UI** was verified through tests; only public pages and the sign-up redirect were exercised in a browser this phase.
7. **No platform "emergency" actions** for 2FA recovery/forced password reset yet.
8. **Legacy role names** (`school_admin`, `teacher`, `student`, `parent`) still exist alongside the new granular roles; migrating real users to granular roles is a school-admin task (UI for role assignment is Phase 3+).
9. **`/metrics`** is token/IP protected in production (Phase 11). Local/testing stays open when unconfigured.
10. Single-database multi-tenancy: strong logical isolation (tested), but no per-tenant encryption keys or physical separation.


## Phase 2 additions

* **Placeholder prices.** `SchoolTypePriceSeeder` only fills school types that have no price; the amounts are development values, not approved prices.
* **No gateway / no automatic payment confirmation** until Phase 3. Manual recording relies on the verifying human and on RBAC (`platform.payments.verify`).
* **Lifecycle e-mails are English-only** and sent on the configured mail driver (`log` locally).
* **Scheduler dependency.** Expiry/suspension is calendar-correct at access time (the gate reads `ends_at`), but status columns, e-mails and dashboards depend on `schoolos:billing:process` running hourly.
* **One currency per price row.** Mixed-currency revenue is shown per currency; there is no FX conversion.
* **Tax** is an optional percentage on a price; no fiscal invoicing/tax-authority integration exists.
* **Custom domains** are no longer plan-gated (no plans exist); only the per-school limit and DNS ownership proof apply.
* **No proration / refunds / credit notes.** Renewals extend from the existing end; refunds are outside Phase 2.


## Phase 3 additions

* **Wave and Stripe are not Verified.** They were exercised only with faked HTTP and locally signed webhooks. Real sandbox runs, a real webhook delivery and a reconciliation are still required.
* **FX / currency is a configuration decision.** Stripe does not list GMD; Wave documents XOF. Each gateway needs an approved currency and fixed rate or it stays unavailable. Confirm with Wave whether GMD is supported.
* **Refunds are detect-and-flag.** Stripe `charge.refunded` marks the payment `refunded` and surfaces it in reconciliation; Wave has no refund webhook; there is no refund-initiation UI; access is not withdrawn automatically.
* **Rejected payments are terminal.** The school starts a new payment; there is no re-open.
* **Single-step approval.** One authorised finance user confirms cash/bank payments (no four-eyes). Every action is audited.
* **Late payments are honoured.** A provider-confirmed payment arriving after cancel/expiry still activates and is flagged `late`.
* **Controller-generated flash/error messages and e-mails are English-only**; all page copy is EN/FR/AR.
* **Receipts are print-to-PDF HTML**, not generated PDF files.
* **Local webhook testing needs an HTTPS tunnel.** Wave source-IP allow-listing is not implemented (signature / shared secret only).
* **Bank evidence is stored on the local private disk.** Production should use encrypted, backed-up storage (set `SCHOOLOS_EVIDENCE_DISK`).

## Phase 4 additions

* **Duplicate detection is heuristic** (normalised names, last 7 digits of phone, address, email aliases). It flags for human review; it cannot prove two registrations are the same institution.
* **Controller flash/error messages and emails are English-only** (including the generic duplicate error and the welcome and invitation emails); page copy is EN/FR/AR.
* **Trial confirmation is merged into the welcome email**; there is no separate trial email.
* **School type cannot be changed in the wizard** (it sets the annual price); changes go through support or the platform.
* **Parent-access setting is only recorded**; enforcement belongs to the parent portal phase.
* **Optional wizard steps can be marked done with zero items.** Data-driven steps (departments, classes, subjects, staff, students) complete only with real records.
* **Invitable roles include `finance_officer` and `headteacher`,** which hold billing/website permissions under the existing RBAC design.
* **Registered schools' websites are published** with disclosed sample copy until the admin edits or unpublishes them (wizard step 12).
* Registration academic year/term dates are placeholders until the admin confirms them in the wizard.

## Phase 5 additions

* **Application fees are not implemented**; they belong to the payments phase.
* **SMS is not implemented.** Guardian absence alerts are queued database + mail notifications, are not delivery-verified, and reach only guardians who have a portal account.
* **Class ranking is optional and off by default.** Termly report cards can show class position when the school enables it on the report template. Report cards are A4 PDFs (Dompdf) with a browser preview; see Report Cards & Transcripts below.
* **No timetable auto-generation and no room-capacity check.** Conflicts are detected and block publication; the timetable is built by hand.
* **Default grading scale and weights are editable starting points,** not official national or WAEC grading. Gambian external-examination workflows are Phase 6.
* **Parent and student account provisioning** from admissions belongs to Phase 8; enrolment creates the student and guardian records only.
* **Server-side validation text** produced by Laravel itself and a few older English-only messages are shown as received; business refusals and confirmations are translated (EN/FR/AR) on the client. Default assessment category names are English until renamed.
* **The sync queue wipes the tenant after a job** by design (existing behaviour); the import job restores the previous tenant, but code that dispatches synchronously must not assume the tenant survives.
* **Stricter than before:** enrolment only from `accepted`; admissions need gender, date of birth, guardian name and phone; teachers without an allocation cannot mark; publishing requires approval.
* **Duplicate detection is by name + date of birth** and needs human confirmation; it cannot prove two records are the same child.

## Phase 6 additions

* **No WAEC integration.** Index numbers, results, collection and certificate status are entered or imported by the school; nothing is fetched from or sent to WAEC. The system never fabricates an index number.
* **Unmapped levels are not guessed.** A level without a grade number can register for either exam; the school should map its levels (Grade 9 / Grade 12) so registration is restricted correctly.
* **Reminders are database + mail only**, not delivery-verified; no SMS. A queue worker and the scheduler (`schedule:run`) are required.
* **Certificate waiting time (2-3 years) is an estimate** shown for planning only.
* **Senior Secondary engine recommends, a human decides.** Thresholds are entered by each school; there are no built-in school-specific rules, and no automatic selection or notification of applicants.
* **Transcripts show only terms with data.** Missing terms are flagged, never invented; Grade 12 Term 3 needs an explicit authorised exception.
* **PDFs use dompdf** (no custom fonts beyond the bundled ones); Arabic names in PDFs depend on the font available to dompdf and should be checked per school before issuing.
* **Verification page is deliberately minimal** (authenticity, type, reference, school, issue date, valid/revoked, holder initials). It cannot prove the physical paper is genuine.
* **School transcripts are not WAEC documents** and say so on every page.
* **Behaviour change:** route-model binding now happens after the tenant is bound; the middleware priority is set in `bootstrap/app.php`.


## Phase 7 additions

* **SCORM and LTI are not implemented.** There is no SCORM runtime, no LTI launch and no grade passback. The "External content" screen only stores names and http(s) URLs and opens them; it must not be marketed as SCORM or LTI support.
* **Short-answer marking is limited:** exact match after Unicode, case, whitespace, trailing-punctuation and number normalisation. No synonyms, spelling tolerance or semantic marking. Review borderline answers manually.
* **Gradebook percentages are a teaching aid**, not official marks. Official results come from the examination workflow.
* **Announcement email is not delivery-verified**; there is no SMS. Scheduled announcements need the scheduler.
* **No proctoring or anti-cheating** beyond the server-side time limit, attempt limit, shuffling and random bank draws.
* **No video hosting or transcoding.** Link a video URL or upload a file within the size limit.
* **No rubric template library**; rubrics are entered per assignment.
* **Parents have no LMS view yet** (Phase 8), and there is no parent-teacher course messaging.
* A quiz that has attempts is locked for editing and deletion by design; to change questions, create a new quiz.
* Course percentages ignore unreleased quiz marks and unpublished exams, so a learner's number can rise when marks are released.

## Phase 8 additions

* **SMS delivery is not verified.** The Twilio and Africa's Talking gateways were tested only against faked HTTP responses and signed callbacks. The default `log` driver writes to the log, and the UI records those messages as "logged", never "sent". Needs a real sandbox test (Requires External Configuration).
* **E-mail delivery is not verified.** Tests use the `array` mailer; development uses `log`. There is no bounce or read tracking, so the delivery history can say "sent" or "logged" but never "delivered". Notification e-mail wording is English only.
* **One login per e-mail address**, shared across the platform. A guardian with children in two schools needs a different address in each.
* **Learners cannot start conversations** unless `SCHOOLOS_MESSAGING_STUDENTS_START=true`; they can answer staff who write to them (school switch `student_messaging`).
* **Families never message each other**, and parents can write only to teachers of their own children's classes or office staff. There is no parent-to-parent or student-to-student chat by design.
* **Moderation is report-driven.** There is no automated keyword filtering and moderators cannot browse unreported conversations. Messages are plain text with limited attachment types.
* **Teachers' announcements** reach learners and guardians of their own classes only; school-wide notices need `announcements.manage`.
* **Parents have no LMS view** (course content, assignments) yet; they see attendance, released results, fees, timetable, announcements and messages.
* **Same-second read marks:** a message created in the same second as the reader's last read time is treated as read; at human speed this is not noticeable.
* **No push notifications, WhatsApp or voice.** SMS is plain text; Arabic and French SMS count as Unicode segments (70 characters) and cost more.
* **Scheduling** needs the Laravel scheduler (every minute) and a queue worker; without them announcements stay scheduled and notifications are not sent.
* Sidebar count badges refresh on page navigation, not live.

## Phase 8 follow-up (platform credentials console)

* **Credentials are one set for the whole platform.** Schools cannot bring their own SMTP account, SMS provider or sender ID; a school can only switch SMS on, set a cap and a sender name. Per-school credentials are not built.
* **"Test accepted" is not "delivered".** The console can only prove that the SMTP server or SMS provider accepted a message. Delivery is "Verified" only after an administrator confirms it arrived; until then mail and SMS stay *Requires External Configuration - NOT Verified*. The console was exercised against a local SMTP sink and (for SMS) the real Twilio API with fake credentials; nothing real was delivered.
* **The application key protects the stored secrets.** Back up `APP_KEY` with the database. If it changes, saved credentials become unreadable (the card says so) and the `.env` defaults apply until they are entered again. A stolen database backup alone does not reveal them; a stolen backup plus the key does.
* **A stolen platform-administrator session could replace the mail server** (and read password-reset e-mails). Mitigations: separate permission, password re-entry for every change, test-before-activate, audit trail and an e-mail to the other administrators; keep the platform-administrator list short, and keep 2FA required for privileged roles in production.
* Changing a live value switches it **off** until retested (by design); a short outage of the console settings falls back to `.env`, not to silence.
* `MAIL_*` / `SCHOOLOS_SMS_*` in `.env` still work and remain the fallback; the console cannot read them, only report the driver and SMTP host.
* The mail "From" name is the platform's, not each school's. The test e-mail and the security notices are English only.
* Wave and Stripe keys are still `.env`-only.

## Phase 9 additions

* **School fees are not SaaS billing.** Cash/Wave/Bank on the Fees screens are school collection methods. Platform Wave/Stripe for subscriptions stay unconfigured and unverified.
* **No monthly SaaS price.** Subscriptions remain annual-only (Phase 2).
* **E-mail and SMS for operations modules are not delivery-verified.** Fee reminders (`schoolos:fees:remind`), low-stock, critical safeguarding and event publish notices were tested with the array/in-app path only. Notification bodies are English.
* **Safeguarding lists never include the summary.** Opening a case is audited; the summary is encrypted at rest (`SensitiveText`) and is not written into audit properties.
* **Procurement self-approval is off by default.** The same person cannot approve their own request or the invoice they recorded unless the school turns the switch on.
* **Transport and boarding charges are pending `student_fees` rows** keyed by source (`transport:{route}:{term}`, `boarding:{hostel}:{term}`). Already-invoiced rows are not rewritten.
* **Event venue clashes** treat any non-cancelled event as occupying the venue (including drafts).
* `/metrics` is protected in production as of Phase 11.
* No git remote in this environment; CI files exist but have not run on GitHub.

## Phase 10 additions

* **This phase is i18n only.** No new school modules, no CI, no scale evidence, no 2M-user claim.
* **Notification e-mail bodies, Laravel validation text, and some older controller flash messages remain English.** Page chrome for the leftover dashboards, reports, exports, profile/2FA and platform console is EN/FR/AR.
* **School-entered content is not translated** (school names, activity-log descriptions, alert titles the operator types).
* **Role chips still show Spatie role names** (`platform admin`, `school_admin`) rather than catalog labels.
* **`Welcome.jsx` is unused** and still English.
* Wave/Stripe SaaS and mail/SMS delivery stay **Requires External Configuration, NOT Verified**. No monthly SaaS price.

## Phase 11 additions

* **CI is files + a local PHPUnit/i18n run, not a GitHub-verified pipeline.** This machine has no git remote.
* **k6 Gate 1–3 have not been run against production-like staging.** Do not claim 2M concurrent users. `two_million_certified` stays false.
* **`/metrics` is fail-closed in production** without a token or IP allow-list. Query-string tokens are rejected (they leak in logs). Local/testing remains open when unconfigured.
* Autoscaling YAML under `deploy/autoscaling/` is still a sketch. Host wiring is an operations task.
* Mail/SMS and Wave/Stripe stay **Requires External Configuration, NOT Verified**. No monthly SaaS price.

## Report Cards & Transcripts

* **No new Spatie permissions.** Access reuses `exams.view` / `exams.mark` / `exams.manage`, `results.publish`, `records.manage`, and `students.view`. Family portals see published reports for linked children only.
* **Transcripts are still Records Office documents.** The reporting module lists Grade 9 (nine terms) and Grade 12 (eight terms, no automatic Grade 12 Term 3) transcripts; drafting and issuance stay in Records Office. A school report or transcript is never a WAEC result or certificate.
* **Missing term results are never treated as zero.** Annual averages use school term weights only when every term in the year is present; otherwise the mean of available terms. Incomplete records cannot be published unless an authorised user sets the exception flag.
* **Published snapshots are immutable.** Corrections create a revision and mark the previous version superseded.
* **Class position is off by default.** Enable it per template; ranking uses students who have an average for that class and term.
* **Batch ZIP download of PDFs is not implemented.** Download is per report (or generate a class, then download each).
* Parent and student access is covered by PHPUnit, not a live portal login in this pass.
