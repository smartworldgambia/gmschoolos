<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Central application domain
    |--------------------------------------------------------------------------
    | Hostnames that serve the SaaS marketing site and school registration.
    | Do not hard-code production domains elsewhere â€” use this config / env.
    */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SCHOOLOS_CENTRAL_DOMAINS', 'localhost,127.0.0.1'))
    ))),

    'base_domain' => env('SCHOOLOS_BASE_DOMAIN', 'gambiaschoolos.com'),

    /*
    | CNAME target shown to schools connecting a custom domain.
    | Defaults to the school's platform host ({slug}.{base_domain}).
    */
    'custom_domain_cname_target' => env('SCHOOLOS_CUSTOM_DOMAIN_CNAME_TARGET'),

    /*
    | Public IPv4 of the edge/load balancer. Shown as an A record for apex domains
    | (e.g. myschool.edu.gm) where a CNAME is not allowed by DNS.
    */
    'custom_domain_ip_target' => env('SCHOOLOS_CUSTOM_DOMAIN_IP'),

    /*
    | true  = custom domains verify without a DNS lookup (dev only).
    | false = always require real DNS verification.
    | null  = auto: skip DNS in local/testing environments only.
    */
    'custom_domain_auto_verify' => env('SCHOOLOS_CUSTOM_DOMAIN_AUTO_VERIFY') === null
        ? null
        : filter_var(env('SCHOOLOS_CUSTOM_DOMAIN_AUTO_VERIFY'), FILTER_VALIDATE_BOOLEAN),

    /* Max custom domains one school may connect (platform admins can exceed this). */
    'custom_domain_limit' => (int) env('SCHOOLOS_CUSTOM_DOMAIN_LIMIT', 3),

    /* Unverified domain claims are released after this many days. */
    'custom_domain_pending_ttl_days' => (int) env('SCHOOLOS_CUSTOM_DOMAIN_PENDING_TTL_DAYS', 14),

    /*
    | Comma-separated plan codes allowed to connect custom domains (e.g. "standard,premium").
    | Empty = every plan may connect a custom domain.
    */
    'custom_domain_plan_codes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SCHOOLOS_CUSTOM_DOMAIN_PLAN_CODES', ''))
    ))),

    /*
    | Redirect public pages (/, /privacy, /terms) from the platform subdomain to the
    | school's primary custom domain once its TLS certificate is active.
    | Login and /admin are never redirected so admins can always sign in.
    */
    'redirect_to_primary_domain' => (bool) env('SCHOOLOS_REDIRECT_TO_PRIMARY_DOMAIN', true),

    /*
    | Shared secret for the on-demand TLS "ask" endpoint (Caddy / Traefik):
    |   GET /_internal/domains/allow?domain=host&token=...
    | Returns 200 only for verified hosts. Leave empty to disable token checks.
    */
    'tls_ask_token' => env('SCHOOLOS_TLS_ASK_TOKEN'),

    /*
    | Subdomains that can never be claimed by a school at registration.
    */
    'reserved_subdomains' => [
        'www', 'app', 'api', 'admin', 'administrator', 'root', 'mail', 'email', 'smtp', 'imap', 'pop', 'ftp',
        'ns', 'ns1', 'ns2', 'mx', 'cdn', 'static', 'assets', 'media', 'files', 'img', 'images',
        'support', 'help', 'docs', 'status', 'blog', 'news', 'billing', 'payments', 'pay',
        'platform', 'dashboard', 'login', 'logout', 'register', 'signup', 'signin', 'auth', 'oauth', 'sso',
        'demo', 'test', 'testing', 'staging', 'stage', 'dev', 'develop', 'beta', 'sandbox', 'preview',
        'schoolos', 'gmschoolos', 'gambiaschoolos', 'secure', 'ssl', 'vpn', 'webmail', 'cpanel', 'whm', 'localhost',
        'internal', 'system', 'sites', 'metrics', 'health', 'ready', 'up', 'xn',
    ],

    /*
    | When true and SESSION_DOMAIN is shared (e.g. .gambiaschoolos.com), after
    | school registration the admin is sent to https://{slug}.{base_domain}/admin.
    | Localhost / path installs stay on the central host admin dashboard.
    */
    'redirect_to_tenant_after_register' => (bool) env('SCHOOLOS_REDIRECT_TENANT_AFTER_REGISTER', false),

    'trial_days' => (int) env('SCHOOLOS_TRIAL_DAYS', 30),

    /*
    | Phase 4 - school onboarding.
    | terms_version: recorded with each registration so a later change of the legal text is traceable.
    | invitable_roles: the ONLY roles the setup wizard can invite. Administrator, owner and every platform role are
    |   deliberately absent: an invitation can never create a privileged account.
    */
    'onboarding' => [
        'terms_version' => env('SCHOOLOS_TERMS_VERSION', '2026-11'),
        'invitation_ttl_days' => (int) env('SCHOOLOS_INVITATION_TTL_DAYS', 7),
        'invitable_roles' => [
            'headteacher', 'deputy_headteacher', 'academic_coordinator', 'head_of_department', 'teacher', 'class_teacher',
            'exam_officer', 'admissions_officer', 'finance_officer', 'accountant', 'records_officer', 'librarian',
            'hr_officer', 'storekeeper', 'transport_officer',
        ],
        'defaults' => [
            'timezone' => 'Africa/Banjul',
            'locale' => env('SCHOOLOS_DEFAULT_LOCALE', 'en'),
            'school_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'attendance_mode' => 'daily',
        ],
    ],

    /*
    | Phase 5 - academic and student management.
    | students.number_prefix: permanent student numbers are PREFIX-YEAR-00001 (per-school sequence, never reused).
    | attendance.edit_window_days: a teacher may re-mark a register for this many days after the date; older records
    |   change only through a correction request approved by someone holding attendance.approve.
    | attendance.guardian_alert_statuses: statuses that notify guardians who have a portal account.
    | documents: private-disk uploads (content-sniffed, size capped).
    | results.default_*: the scale / categories created ON DEMAND for a school that has not configured its own.
    |   They are EDITABLE STARTING POINTS, not an official or ministry grading scheme.
    */
    'students' => [
        'number_prefix' => env('SCHOOLOS_STUDENT_PREFIX', 'STU'),
        'document_max_kb' => (int) env('SCHOOLOS_STUDENT_DOC_MAX_KB', 5120),
        'document_disk' => env('SCHOOLOS_STUDENT_DOC_DISK', 'local'),
    ],

    'attendance' => [
        'edit_window_days' => (int) env('SCHOOLOS_ATTENDANCE_EDIT_DAYS', 1),
        'guardian_alert_statuses' => ['absent', 'late', 'left_early'],
    ],

    /*
    | Phase 6 - external examinations (GABECE / WASSCE), Senior Secondary admissions, Records Office.
    | external_exams.grades: the grade symbols accepted when results are entered or imported. WASSCE uses the
    |   published nine-point WAEC symbols. The GABECE list is an EDITABLE STARTING POINT, not an official scheme:
    |   each school can override either list in Records settings. Unknown symbols are always rejected, never guessed.
    | external_exams.reminder_*: DEFAULTS for outstanding result / certificate collection reminders (per-school
    |   override in Records settings). certificate_estimate_text is an estimate shown to staff only - never a promise.
    */
    'external_exams' => [
        'grades' => [
            'wassce' => ['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'],
            'gabece' => ['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'],
        ],
        'import_max_rows' => (int) env('SCHOOLOS_EXTERNAL_IMPORT_MAX_ROWS', 5000),
        'import_max_kb' => (int) env('SCHOOLOS_EXTERNAL_IMPORT_MAX_KB', 2048),
        'reminder_days' => (int) env('SCHOOLOS_COLLECTION_REMINDER_DAYS', 14),
        'reminder_max' => (int) env('SCHOOLOS_COLLECTION_REMINDER_MAX', 6),
        'certificate_estimate_text' => 'about 2-3 years (estimate only)',
    ],

    'lms' => [
        'disk' => env('SCHOOLOS_LMS_DISK', 'local'),
        'file_max_kb' => (int) env('SCHOOLOS_LMS_FILE_MAX_KB', 10240),
        'max_submission_files' => (int) env('SCHOOLOS_LMS_MAX_SUBMISSION_FILES', 5),
        'inactive_days' => (int) env('SCHOOLOS_LMS_INACTIVE_DAYS', 14),
    ],

    'records' => [
        'document_disk' => env('SCHOOLOS_RECORDS_DISK', 'local'),
        'stamp_max_kb' => (int) env('SCHOOLOS_RECORDS_STAMP_MAX_KB', 1024),
        'verify_throttle' => env('SCHOOLOS_RECORDS_VERIFY_THROTTLE', '60,1'),
    ],
    'academic_reports' => [
        'show_position' => false,
        'show_photo' => true,
        'show_dob' => false,
        'term_weights' => [],
    ],

    'results' => [
        'default_scale' => [
            ['letter' => 'A', 'min' => 80, 'remark' => 'Excellent'],
            ['letter' => 'B+', 'min' => 70, 'remark' => 'Very good'],
            ['letter' => 'B', 'min' => 60, 'remark' => 'Good'],
            ['letter' => 'C', 'min' => 50, 'remark' => 'Credit'],
            ['letter' => 'D', 'min' => 40, 'remark' => 'Pass'],
            ['letter' => 'F', 'min' => 0, 'remark' => 'Fail'],
        ],
        'default_categories' => [
            ['code' => 'CA', 'name' => 'Continuous assessment', 'type' => 'ca', 'weight' => 40, 'max_score' => 40],
            ['code' => 'EXAM', 'name' => 'End-of-term examination', 'type' => 'exam', 'weight' => 60, 'max_score' => 60],
        ],
    ],

    'currency' => [
        'code' => 'GMD',
        'symbol' => 'D',
        'name' => 'Gambian Dalasi',
    ],

    'supported_locales' => ['en', 'fr', 'ar'],

    'default_locale' => env('SCHOOLOS_DEFAULT_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Platform outgoing mail identity
    |--------------------------------------------------------------------------
    | Used as MAIL_FROM_* when those env values are still Laravel placeholders,
    | and as the empty-form defaults on Platform → Integrations. SMTP host and
    | password are never invented here — set MAIL_* on Laravel Cloud or save
    | them in the console.
    */
    'mail' => [
        'from_address' => env('SCHOOLOS_MAIL_FROM_ADDRESS', 'noreply@gambiaschoolos.com'),
        'from_name' => env('SCHOOLOS_MAIL_FROM_NAME', env('APP_NAME', 'Gambia SchoolOS')),
        'smtp' => [
            'port' => (int) env('SCHOOLOS_MAIL_SMTP_PORT', 587),
            'encryption' => env('SCHOOLOS_MAIL_SMTP_ENCRYPTION', 'tls'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketing / SaaS landing
    |--------------------------------------------------------------------------
    | Public landing content. The stats strip uses live database counts.
    */
    'marketing' => [
        'tagline' => 'Simplifying School Management and Learning.',
        'contact_email' => env('SCHOOLOS_CONTACT_EMAIL', 'info@gambiaschoolos.com'),
        'demo_email' => env('SCHOOLOS_DEMO_EMAIL', env('SCHOOLOS_CONTACT_EMAIL', 'info@gambiaschoolos.com')),
        'support_email' => env('SCHOOLOS_SUPPORT_EMAIL', env('SCHOOLOS_CONTACT_EMAIL', 'info@gambiaschoolos.com')),
        'phone' => env('SCHOOLOS_CONTACT_PHONE', '+220 877031891'),
        'phone_secondary' => env('SCHOOLOS_CONTACT_PHONE_2'),
        'address' => env('SCHOOLOS_CONTACT_ADDRESS', 'Smartworld, Opposite Netpage, Kairaba Avenue, The Gambia'),
        'website' => env('SCHOOLOS_CONTACT_WEBSITE', 'https://gambiaschoolos.com'),
        'social' => [
            'facebook' => env('SCHOOLOS_SOCIAL_FACEBOOK'),
            'twitter' => env('SCHOOLOS_SOCIAL_TWITTER'),
            'linkedin' => env('SCHOOLOS_SOCIAL_LINKEDIN'),
            'youtube' => env('SCHOOLOS_SOCIAL_YOUTUBE'),
        ],
        'popular_plan_code' => env('SCHOOLOS_POPULAR_PLAN', 'standard'),
        'illustrative_stats' => [],
        'stats_mode' => env('SCHOOLOS_LANDING_STATS_MODE', 'live'),
        'testimonial' => null, // set approved quote array later: quote, name, role, school, photo_url, rating
    ],

    'sms' => [
        // log|null â€” real gateway providers can be added without changing callers
        'driver' => env('SCHOOLOS_SMS_DRIVER', 'log'),
        // Phase 8: log | null | twilio | africastalking. `log` only writes to the log and is recorded as "logged", never as "sent".
        // Each school switches SMS on for itself; nothing is operational until a real provider has delivered a real message.
        'country_code' => env('SCHOOLOS_SMS_COUNTRY_CODE', '220'),   // The Gambia: 7-digit local numbers get this prefix
        'timeout' => (int) env('SCHOOLOS_SMS_TIMEOUT', 15),
        'max_attempts' => (int) env('SCHOOLOS_SMS_MAX_ATTEMPTS', 3),
        'backoff' => [60, 300, 900],
        'default_daily_cap' => (int) env('SCHOOLOS_SMS_DAILY_CAP', 500),
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
            'status_callback' => env('TWILIO_STATUS_CALLBACK'),      // exact public URL of /webhooks/sms/twilio
        ],
        'africastalking' => [
            'username' => env('AT_USERNAME'),
            'api_key' => env('AT_API_KEY'),
            'from' => env('AT_SENDER_ID'),
            'sandbox' => (bool) env('AT_SANDBOX', false),
            'webhook_secret' => env('AT_WEBHOOK_SECRET'),            // path secret of /webhooks/sms/africastalking/{secret}
        ],
        // Starting texts. A school can override any of them per language (sms_templates).
        'templates' => [
            'attendance_alert' => [
                'en' => '{school}: {student} was marked {status} on {date}.',
                'fr' => '{school} : {student} a été marqué(e) {status} le {date}.',
                'ar' => '{school}: تم تسجيل {student} {status} بتاريخ {date}.',
            ],
            'exam_published' => [
                'en' => '{school}: results for {exam} are now available in your portal.',
                'fr' => '{school} : les résultats de {exam} sont disponibles sur votre portail.',
                'ar' => '{school}: نتائج {exam} متاحة الآن في بوابتك.',
            ],
            'announcement' => [
                'en' => '{school}: {title}',
                'fr' => '{school} : {title}',
                'ar' => '{school}: {title}',
            ],
            'message_received' => [
                'en' => '{school}: new message from {sender}. Open SchoolOS to read it.',
                'fr' => '{school} : nouveau message de {sender}. Ouvrez SchoolOS pour le lire.',
                'ar' => '{school}: رسالة جديدة من {sender}. افتح SchoolOS لقراءتها.',
            ],
            'test' => [
                'en' => '{school}: SMS test. If you can read this, delivery works.',
                'fr' => '{school} : test SMS. Si vous lisez ce message, l’envoi fonctionne.',
                'ar' => '{school}: اختبار رسالة نصية. إذا كنت تقرأ هذا فالإرسال يعمل.',
            ],
        ],
        'status_words' => [
            'en' => ['present' => 'present', 'absent' => 'absent', 'late' => 'late', 'excused' => 'excused', 'left_early' => 'left early'],
            'fr' => ['present' => 'présent', 'absent' => 'absent', 'late' => 'en retard', 'excused' => 'excusé', 'left_early' => 'parti tôt'],
            'ar' => ['present' => 'حاضراً', 'absent' => 'غائباً', 'late' => 'متأخراً', 'excused' => 'معذوراً', 'left_early' => 'مغادراً مبكراً'],
        ],
    ],

    /*
    | Phase 8 - notification categories. `mandatory` categories always reach the in-app inbox and e-mail (account and
    | security messages). `sms_default` is the starting preference for SMS; SMS is only used when the school has switched
    | it on and the person has a phone number. Users can change every non-mandatory preference.
    */
    'notifications' => [
        'categories' => [
            'announcement' => ['mandatory' => false, 'sms' => true, 'sms_default' => false],
            'attendance' => ['mandatory' => false, 'sms' => true, 'sms_default' => true],
            'exam' => ['mandatory' => false, 'sms' => true, 'sms_default' => false],
            'fees' => ['mandatory' => false, 'sms' => true, 'sms_default' => false],
            'message' => ['mandatory' => false, 'sms' => true, 'sms_default' => false],
            'lms' => ['mandatory' => false, 'sms' => false, 'sms_default' => false],
            'account' => ['mandatory' => true, 'sms' => false, 'sms_default' => false],
        ],
    ],

    /*
    | Phase 8 - internal messaging. Learners may write to school staff and to classmates (same class only).
    | Set `students_can_start` off to keep learners reply-only. Families never message each other.
    */
    'messaging' => [
        'disk' => env('SCHOOLOS_MESSAGING_DISK', 'local'),
        'attachment_max_kb' => (int) env('SCHOOLOS_MESSAGING_ATTACHMENT_KB', 5120),
        'max_attachments' => (int) env('SCHOOLOS_MESSAGING_MAX_ATTACHMENTS', 3),
        'allowed_mimes' => 'pdf,jpg,jpeg,png,doc,docx',
        'body_max' => 4000,
        'students_can_start' => (bool) env('SCHOOLOS_MESSAGING_STUDENTS_START', true),
        'group_max_members' => 400,
    ],

    /*
    | Phase 9 - school finance and operations. These are per-school DEFAULTS (a school changes them in its own screens).
    | They are starting points, not legal, tax or ministry rules: fee amounts, fines, leave entitlements and payroll
    | deductions are never pre-filled by the platform.
    |  self_approval: when false (the default) the person who requested something cannot also approve it.
    */
    'ops' => [
        'finance' => [
            'invoice_prefix' => 'INV',
            'due_days' => 30,
            'allow_installments' => false,
            'installments_max' => 4,
            'parent_submissions' => true,     // parents may report a Wave / bank / other payment for staff to confirm
            'self_approval' => false,
            'reminder_days' => 7,             // minimum days between two reminders for the same invoice
            'reminder_max' => 3,
            'receipt_footer' => '',
        ],
        'library' => [
            'loan_days' => 14,
            'fine_per_day' => 0,              // 0 = no overdue fines
            'max_loans_student' => 3,
            'max_loans_staff' => 5,
            'max_renewals' => 1,
            'block_if_overdue' => true,
        ],
        'hr' => [
            'leave_annual_days' => null,      // null = no entitlement limit is enforced
            'leave_sick_days' => null,
            'self_approval' => false,
        ],
        'payroll' => [
            'self_approval' => false,
        ],
        'procurement' => [
            'self_approval' => false,
        ],
        'events' => [
            'announce_on_publish' => false,
        ],
    ],

    'exports' => [
        'ttl_hours' => (int) env('SCHOOLOS_EXPORT_TTL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query profiling
    |--------------------------------------------------------------------------
    */
    'query_profile' => [
        'enabled' => (bool) env('SCHOOLOS_QUERY_PROFILE', false),
        'slow_ms' => (float) env('SCHOOLOS_SLOW_QUERY_MS', 100),
        'sample_limit' => (int) env('SCHOOLOS_SLOW_QUERY_SAMPLE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => [
        'public_site' => (int) env('SCHOOLOS_CACHE_PUBLIC_SITE', 300),
        'admin_dashboard' => (int) env('SCHOOLOS_CACHE_ADMIN_DASHBOARD', 60),
        'plans_landing' => (int) env('SCHOOLOS_CACHE_PLANS', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Independent worker pools (queue names)
    |--------------------------------------------------------------------------
    | Run separate workers per pool so imports/exports cannot starve critical work.
    | Example: php artisan queue:work --queue=critical,default
    |          php artisan queue:work --queue=imports
    |          php artisan queue:work --queue=exports,notifications,reports
    */
    'queues' => [
        'critical' => env('SCHOOLOS_QUEUE_CRITICAL', 'critical'),
        'default' => env('SCHOOLOS_QUEUE_DEFAULT', 'default'),
        'imports' => env('SCHOOLOS_QUEUE_IMPORTS', 'imports'),
        'exports' => env('SCHOOLOS_QUEUE_EXPORTS', 'exports'),
        'notifications' => env('SCHOOLOS_QUEUE_NOTIFICATIONS', 'notifications'),
        'reports' => env('SCHOOLOS_QUEUE_REPORTS', 'reports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoscaling / capacity caps (honest ceilings â€” not validated at 2M users)
    |--------------------------------------------------------------------------
    */
    'autoscaling' => [
        'app_min' => (int) env('SCHOOLOS_AS_APP_MIN', 1),
        'app_max' => (int) env('SCHOOLOS_AS_APP_MAX', 4),
        'worker_min' => (int) env('SCHOOLOS_AS_WORKER_MIN', 1),
        'worker_max' => (int) env('SCHOOLOS_AS_WORKER_MAX', 6),
        'target_cpu_pct' => (int) env('SCHOOLOS_AS_TARGET_CPU', 65),
        'scale_out_queue_depth' => (int) env('SCHOOLOS_AS_QUEUE_SCALE_OUT', 100),
        'db_connection_budget' => (int) env('SCHOOLOS_DB_CONNECTION_BUDGET', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost attribution weights (relative units, not currency)
    |--------------------------------------------------------------------------
    */
    'cost' => [
        'weights' => [
            'students' => (float) env('SCHOOLOS_COST_W_STUDENTS', 1.0),
            'requests' => (float) env('SCHOOLOS_COST_W_REQUESTS', 0.01),
            'exports' => (float) env('SCHOOLOS_COST_W_EXPORTS', 5.0),
            'imports' => (float) env('SCHOOLOS_COST_W_IMPORTS', 8.0),
            'courses' => (float) env('SCHOOLOS_COST_W_COURSES', 0.5),
        ],
        'daily_budget_units' => (float) env('SCHOOLOS_COST_DAILY_BUDGET', 100000),
        'alert_pct' => (float) env('SCHOOLOS_COST_ALERT_PCT', 80),
        'tags' => [
            'project' => 'schoolos',
            'env' => env('APP_ENV', 'local'),
            'service' => env('SCHOOLOS_SERVICE_TAG', 'app'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workload modelling (Phase 7) â€” estimates only, not certifications
    |--------------------------------------------------------------------------
    */
    'workload' => [
        'target_rps_per_replica' => (float) env('SCHOOLOS_WL_RPS_PER_REPLICA', 40),
        'target_rps_per_worker' => (float) env('SCHOOLOS_WL_RPS_PER_WORKER', 80),
        'db_connections_per_app_replica' => (int) env('SCHOOLOS_WL_DB_CONN_PER_REPLICA', 15),
        'profiles' => [
            'A' => [
                'label' => 'Ordinary school day',
                'active_users' => (int) env('SCHOOLOS_WL_A_USERS', 5000),
                'schools' => (int) env('SCHOOLOS_WL_A_SCHOOLS', 50),
                'requests_per_user_per_min' => (float) env('SCHOOLOS_WL_A_RPM', 0.4),
            ],
            'B' => [
                'label' => 'Peak education',
                'active_users' => (int) env('SCHOOLOS_WL_B_USERS', 25000),
                'schools' => (int) env('SCHOOLOS_WL_B_SCHOOLS', 80),
                'requests_per_user_per_min' => (float) env('SCHOOLOS_WL_B_RPM', 1.2),
            ],
            'C' => [
                'label' => 'Extreme burst (model)',
                'active_users' => (int) env('SCHOOLOS_WL_C_USERS', 100000),
                'schools' => (int) env('SCHOOLOS_WL_C_SCHOOLS', 120),
                'requests_per_user_per_min' => (float) env('SCHOOLOS_WL_C_RPM', 2.0),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error reporting (production hardening)
    |--------------------------------------------------------------------------
    | Persists fingerprints to error_reports, logs, optional webhook + platform alerts.
    | Not a substitute for a full APM (Sentry/etc.) â€” wire webhook or swap reporter later.
    */
    'errors' => [
        'enabled' => (bool) env('SCHOOLOS_ERRORS_ENABLED', true),
        'log_channel' => env('SCHOOLOS_ERRORS_LOG_CHANNEL', 'stack'),
        'webhook_url' => env('SCHOOLOS_ERRORS_WEBHOOK_URL'),
        'raise_platform_alerts' => (bool) env('SCHOOLOS_ERRORS_PLATFORM_ALERTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security hardening
    |--------------------------------------------------------------------------
    */
    'security' => [
        'headers_enabled' => (bool) env('SCHOOLOS_SECURITY_HEADERS', true),
        'hsts_enabled' => (bool) env('SCHOOLOS_SECURITY_HSTS', false),
        'force_https' => (bool) env('SCHOOLOS_FORCE_HTTPS', false),

        // Content-Security-Policy: enforce | report-only | off
        'csp_mode' => env('SCHOOLOS_CSP_MODE', 'enforce'),
        // Extra origins appended to the CSP (comma-separated), e.g. a Vite dev server or CDN.
        'csp_extra_script_src' => env('SCHOOLOS_CSP_EXTRA_SCRIPT_SRC', ''),
        'csp_extra_connect_src' => env('SCHOOLOS_CSP_EXTRA_CONNECT_SRC', ''),
        'csp_report_uri' => env('SCHOOLOS_CSP_REPORT_URI'),

        // Privileged roles (config/rbac.php 'two_factor_roles') must enrol in 2FA before using the app.
        // Defaults to ON in production; off elsewhere so local demo accounts keep working.
        'require_two_factor_privileged' => env('SCHOOLOS_REQUIRE_2FA_PRIVILEGED') === null
            ? env('APP_ENV') === 'production'
            : filter_var(env('SCHOOLOS_REQUIRE_2FA_PRIVILEGED'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | School access enforcement (suspension / trial / subscription expiry)
    |--------------------------------------------------------------------------
    | Default grace period is ZERO days: access stops the moment a trial or paid period ends,
    | unless the platform explicitly configures a grace period. School data is never deleted.
    */
    /*
    |--------------------------------------------------------------------------
    | Annual subscription billing (Phase 2)
    |--------------------------------------------------------------------------
    | Annual-only. There is no monthly cycle, no tiers and no per-feature pricing: one configurable
    | annual price per school type, managed by the Platform Administrator (school_type_prices table).
    */
    // Platform-wide quota defaults (no tiers). null = unlimited; operators may override per school.
    'quota_defaults' => [
        'max_students' => env('SCHOOLOS_DEFAULT_MAX_STUDENTS') !== null ? (int) env('SCHOOLOS_DEFAULT_MAX_STUDENTS') : null,
        'max_staff' => env('SCHOOLOS_DEFAULT_MAX_STAFF') !== null ? (int) env('SCHOOLOS_DEFAULT_MAX_STAFF') : null,
    ],

    'billing' => [
        'currency' => env('SCHOOLOS_BILLING_CURRENCY', 'GMD'),
        // Days before expiry on which reminders are sent (configurable per environment).
        'trial_reminder_days' => array_map('intval', array_filter(explode(',', (string) env('SCHOOLOS_TRIAL_REMINDER_DAYS', '14,7,3,1')), 'strlen')),
        'paid_reminder_days' => array_map('intval', array_filter(explode(',', (string) env('SCHOOLOS_PAID_REMINDER_DAYS', '30,14,7,3,1')), 'strlen')),
        // Payment methods the platform supports (Phase 3). `enabled` is the operator's switch; a method is
        // only OFFERED to schools when it is also configured (bank accounts exist / gateway keys + webhook
        // secret present / a currency strategy exists). Wave and Stripe stay off until merchant accounts exist.
        'payment_methods' => [
            'cash' => ['enabled' => (bool) env('SCHOOLOS_PAY_CASH', true)],
            'bank_transfer' => ['enabled' => (bool) env('SCHOOLOS_PAY_BANK', true)],
            'wave' => ['enabled' => (bool) env('SCHOOLOS_PAY_WAVE', false)],
            'stripe' => ['enabled' => (bool) env('SCHOOLOS_PAY_STRIPE', false)],
        ],

        // Manual (cash / bank) payments that are never completed are expired after this many days.
        'open_payment_ttl_days' => (int) env('SCHOOLOS_OPEN_PAYMENT_TTL_DAYS', 30),
        // Bank evidence waiting longer than this appears in the reconciliation "needs attention" list.
        'verification_sla_days' => (int) env('SCHOOLOS_VERIFICATION_SLA_DAYS', 3),
        // Gateway sessions older than this (minutes) with no webhook are re-checked server-side by reconciliation.
        'reconcile_after_minutes' => (int) env('SCHOOLOS_RECONCILE_AFTER_MINUTES', 10),

        'cash' => [
            // Where the school pays. Falls back to the platform office address.
            'office' => env('SCHOOLOS_CASH_OFFICE', env('SCHOOLOS_CONTACT_ADDRESS', 'Smartworld, Opposite Netpage, Kairaba Avenue, The Gambia')),
            'instructions' => env('SCHOOLOS_CASH_INSTRUCTIONS'),
        ],

        'evidence' => [
            'disk' => env('SCHOOLOS_EVIDENCE_DISK', 'local'), // private disk - never public
            'max_kb' => (int) env('SCHOOLOS_EVIDENCE_MAX_KB', 5120),
            'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        ],

        /*
        | Gateways. Secrets come ONLY from the environment (never committed, never stored in the database,
        | never shown in the UI). A gateway is offered only when its keys and webhook secret are present and a
        | transparent currency strategy exists: the charge currency must equal the price currency (GMD), or an
        | explicit fx_rate must be configured. Nothing is ever converted silently.
        */
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com'),
            // Stripe does not list GMD as a presentment currency: charge in an approved alternative and show it.
            'currency' => env('SCHOOLOS_STRIPE_CURRENCY', 'USD'),
            'fx_rate' => env('SCHOOLOS_STRIPE_FX_RATE') !== null ? (float) env('SCHOOLOS_STRIPE_FX_RATE') : null, // charge-currency units per 1 GMD
            'session_minutes' => (int) env('SCHOOLOS_STRIPE_SESSION_MINUTES', 60),
            'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],

        'wave' => [
            'api_key' => env('WAVE_API_KEY'),
            'request_signing_secret' => env('WAVE_REQUEST_SIGNING_SECRET'), // optional: signs outbound API requests
            'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
            'webhook_strategy' => env('WAVE_WEBHOOK_STRATEGY', 'signing'), // signing | shared
            'api_base' => env('WAVE_API_BASE', 'https://api.wave.com'),
            // Wave's published Checkout API documents XOF only. Use GMD only once Wave enables it for your merchant.
            'currency' => env('SCHOOLOS_WAVE_CURRENCY', 'XOF'),
            'fx_rate' => env('SCHOOLOS_WAVE_FX_RATE') !== null ? (float) env('SCHOOLOS_WAVE_FX_RATE') : null,
            'session_minutes' => 30, // Wave's default session lifetime
            'webhook_tolerance' => (int) env('WAVE_WEBHOOK_TOLERANCE', 300),
        ],
    ],
    'access' => [
        'grace_days' => (int) env('SCHOOLOS_GRACE_DAYS', 0),
        // Route name patterns that stay reachable for a blocked school (billing / payment pages, added in Phase 2).
        'always_allowed_routes' => ['subscription.*', 'billing.*', 'logout'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics probe (/metrics)
    |--------------------------------------------------------------------------
    | JSON for autoscaling — not Prometheus, not public in production.
    | Set SCHOOLOS_METRICS_TOKEN and/or SCHOOLOS_METRICS_ALLOW_IPS.
    | Production refuses the route when both are empty.
    */
    'metrics' => [
        'token' => env('SCHOOLOS_METRICS_TOKEN'),
        'allow_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SCHOOLOS_METRICS_ALLOW_IPS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Capacity certification thresholds (Phase 7)
    |--------------------------------------------------------------------------
    | Passing Gate 1–3 does NOT certify 2,000,000 concurrent users.
    */
    'capacity' => [
        'two_million_certified' => false,
        'gates' => [
            'gate1' => [
                'max_p95_ms' => 1500,
                'max_error_rate_pct' => 5,
                'min_rps' => 0,
            ],
            'gate2' => [
                'max_p95_ms' => 800,
                'max_error_rate_pct' => 1,
                'min_rps' => 20,
            ],
            'gate3' => [
                'max_p95_ms' => 1200,
                'max_error_rate_pct' => 2,
                'min_rps' => 50,
            ],
            'gate4' => [
                'max_p95_ms' => 1000,
                'max_error_rate_pct' => 1,
                'min_rps' => 100,
            ],
        ],
    ],
];


