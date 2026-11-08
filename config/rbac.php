<?php

/*
|--------------------------------------------------------------------------
| Role & permission matrix (single source of truth)
|--------------------------------------------------------------------------
| Synced into spatie/laravel-permission by App\Support\Rbac (called from RolesAndPlansSeeder
| and `php artisan schoolos:sync-rbac`). Routes and controllers authorise by PERMISSION,
| never by role name. Legacy role names (school_admin, teacher, student, parent, platform_admin)
| are kept so existing users and data keep working.
|
| Permissions are grouped by scope:
|   - 'platform' : only meaningful for platform staff (users with no school).
|   - 'school'   : tenant-level, always evaluated inside a school context.
*/

return [
    'permissions' => [
        'platform' => [
            'platform.access',
            'platform.schools.view',
            'platform.schools.manage',
            'platform.pricing.manage',
            'platform.subscriptions.manage',
            'platform.payments.verify',
            'platform.payments.configure',
            'platform.integrations.configure',   // mail / SMS credentials: deliberately NOT part of any narrower platform role
            'platform.finance.view',
            'platform.operations.view',
            'platform.operations.manage',
            'platform.support.manage',
            'platform.audit.view',
            'platform.users.manage',
            'platform.marketing.manage',
        ],

        'school' => [
            // Portals
            'portal.admin', 'portal.teacher', 'portal.student', 'portal.parent',
            // Students & admissions
            'students.view', 'students.create', 'students.update', 'students.delete',
            'students.import', 'students.export', 'students.view_sensitive',
            'admissions.view', 'admissions.manage',
            // Academics
            'academics.view', 'academics.manage',
            'attendance.view', 'attendance.record', 'attendance.approve', 'attendance.staff',
            'timetable.view', 'timetable.manage',
            // Examinations
            'exams.view', 'exams.manage', 'exams.mark', 'results.publish',
            'records.manage', 'records.issue',
            // External examinations (GABECE / WASSCE), Senior Secondary admissions (Phase 6)
            'external_exams.view', 'external_exams.manage', 'external_exams.verify',
            'senior_admissions.view', 'senior_admissions.manage',
            // Finance
            'fees.view', 'fees.manage', 'payments.record', 'payments.verify',
            // School subscription (SaaS billing)
            'subscriptions.view', 'subscriptions.manage',
            // LMS
            'lms.view', 'lms.teach', 'lms.manage',
            // Operations
            'hr.view', 'hr.manage', 'hr.performance', 'payroll.manage',
            'library.manage', 'transport.manage', 'boarding.manage', 'inventory.manage',
            'safeguarding.manage', 'safeguarding.report',
            // Phase 9: fee approvals (a second person), payroll approval, procurement, events, staff self-service
            'fees.approve', 'payroll.approve',
            'procurement.request', 'procurement.manage', 'procurement.approve',
            'events.view', 'events.manage', 'staff.self',
            // Reports & administration
            'reports.view', 'reports.export',
            'users.manage', 'school.settings.manage', 'website.manage', 'domains.manage',
            // Communication (Phase 8). messages.office = parents may contact this person; messages.moderate = review reported
            // messages; announcements.class = announce to the classes you teach; communications.manage = SMS settings and history.
            'messages.use', 'messages.moderate', 'messages.office',
            'announcements.manage', 'announcements.class', 'communications.manage',
        ],
    ],

    /*
    | Role => permissions. Use '*school' / '*platform' as shorthand for every permission of a scope.
    */
    'roles' => [
        // ------------------------------------------------------------ platform level
        'super_admin' => ['*platform', '*school'],
        'platform_admin' => ['*platform'],
        'platform_finance' => [
            'platform.access', 'platform.schools.view', 'platform.finance.view',
            'platform.payments.verify', 'platform.subscriptions.manage',
        ],
        'platform_support' => [
            'platform.access', 'platform.schools.view', 'platform.schools.manage', 'platform.support.manage',
        ],
        'platform_operations' => [
            'platform.access', 'platform.schools.view', 'platform.operations.view', 'platform.operations.manage',
        ],
        'platform_auditor' => [
            'platform.access', 'platform.schools.view', 'platform.finance.view',
            'platform.operations.view', 'platform.audit.view',
        ],

        // ------------------------------------------------------------ school level
        'school_owner' => ['*school'],
        'school_admin' => ['*school'],
        'headteacher' => [
            'portal.admin',
            'students.view', 'students.update', 'students.export', 'students.view_sensitive',
            'admissions.view', 'admissions.manage',
            'academics.view', 'academics.manage', 'attendance.view', 'attendance.approve', 'attendance.staff',
            'timetable.view', 'timetable.manage',
            'exams.view', 'exams.manage', 'results.publish', 'records.manage', 'records.issue',
            'external_exams.view', 'external_exams.manage', 'external_exams.verify',
            'senior_admissions.view', 'senior_admissions.manage',
            'fees.view', 'fees.approve', 'subscriptions.view', 'lms.view', 'lms.manage',
            'hr.view', 'hr.performance', 'payroll.approve', 'safeguarding.manage', 'safeguarding.report', 'reports.view', 'reports.export', 'website.manage',
            'procurement.request', 'procurement.approve', 'events.view', 'events.manage', 'staff.self',
            'messages.use', 'messages.moderate', 'messages.office', 'announcements.manage', 'communications.manage',
        ],
        'deputy_headteacher' => [
            'portal.admin',
            'students.view', 'students.update', 'students.view_sensitive',
            'admissions.view', 'academics.view', 'academics.manage',
            'attendance.view', 'attendance.approve', 'timetable.view', 'timetable.manage',
            'exams.view', 'exams.manage', 'lms.view', 'reports.view', 'safeguarding.manage', 'safeguarding.report',
            'messages.use', 'messages.moderate', 'messages.office', 'announcements.manage',
            'procurement.request', 'events.view', 'events.manage', 'staff.self',
        ],
        'academic_coordinator' => [
            'portal.admin',
            'students.view', 'academics.view', 'academics.manage', 'attendance.view',
            'timetable.view', 'timetable.manage', 'exams.view', 'exams.manage',
            'lms.view', 'lms.manage', 'reports.view',
            'messages.use', 'messages.office', 'announcements.manage',
            'procurement.request', 'events.view', 'events.manage', 'staff.self',
        ],
        'head_of_department' => [
            'portal.teacher',
            'students.view', 'academics.view', 'attendance.view', 'timetable.view',
            'exams.view', 'exams.mark', 'lms.view', 'lms.teach', 'reports.view',
            'messages.use', 'announcements.class',
            'procurement.request', 'safeguarding.report', 'events.view', 'staff.self',
        ],
        'teacher' => [
            'portal.teacher',
            'students.view', 'academics.view',
            'attendance.view', 'attendance.record',
            'timetable.view', 'exams.view', 'exams.mark',
            'lms.view', 'lms.teach',
            'messages.use', 'announcements.class',
            'safeguarding.report', 'events.view', 'staff.self',
        ],
        'class_teacher' => [
            'portal.teacher',
            'students.view', 'students.view_sensitive', 'academics.view',
            'attendance.view', 'attendance.record', 'timetable.view',
            'exams.view', 'exams.mark', 'lms.view', 'lms.teach',
            'messages.use', 'announcements.class',
            'safeguarding.report', 'events.view', 'staff.self',
        ],
        'exam_officer' => [
            'portal.admin',
            'students.view', 'academics.view', 'exams.view', 'exams.manage', 'exams.mark',
            'results.publish', 'records.manage', 'external_exams.view', 'external_exams.manage',
            'reports.view', 'reports.export',
            'messages.use',
            'safeguarding.report', 'events.view', 'staff.self',
        ],
        'admissions_officer' => [
            'portal.admin',
            'students.view', 'students.create', 'students.update', 'students.import',
            'admissions.view', 'admissions.manage', 'academics.view',
            'senior_admissions.view', 'senior_admissions.manage',
            'messages.use', 'messages.office',
            'safeguarding.report', 'events.view', 'staff.self',
        ],
        'finance_officer' => [
            'portal.admin',
            'students.view',
            'fees.view', 'fees.manage', 'payments.record', 'payments.verify',
            'subscriptions.view', 'subscriptions.manage',
            'reports.view', 'reports.export',
            'messages.use', 'messages.office',
            'procurement.request', 'procurement.manage', 'events.view', 'staff.self',
        ],
        'accountant' => [
            'portal.admin',
            'fees.view', 'payments.record', 'subscriptions.view',
            'payroll.manage', 'reports.view', 'reports.export',
            'procurement.manage', 'events.view', 'staff.self',
        ],
        'records_officer' => [
            'portal.admin',
            'students.view', 'students.create', 'students.update', 'students.import',
            'students.export', 'students.view_sensitive', 'academics.view',
            'exams.view', 'records.manage', 'external_exams.view', 'external_exams.manage',
            'reports.view', 'reports.export',
            'messages.use', 'messages.office',
            'safeguarding.report', 'events.view', 'staff.self',
        ],
        'librarian' => ['portal.admin', 'students.view', 'library.manage', 'procurement.request', 'safeguarding.report', 'events.view', 'staff.self'],
        'hr_officer' => ['portal.admin', 'hr.view', 'hr.manage', 'hr.performance', 'payroll.manage', 'attendance.staff', 'reports.view', 'procurement.request', 'events.view', 'staff.self'],
        'storekeeper' => ['portal.admin', 'inventory.manage', 'procurement.request', 'procurement.manage', 'events.view', 'staff.self'],
        'transport_officer' => ['portal.admin', 'students.view', 'transport.manage', 'procurement.request', 'safeguarding.report', 'events.view', 'staff.self'],
        'parent' => [
            'portal.parent',
            'students.view', 'fees.view', 'attendance.view', 'timetable.view', 'exams.view',
            'lms.view', 'messages.use', 'events.view',
        ],
        'student' => [
            'portal.student',
            'students.view', 'fees.view', 'attendance.view', 'timetable.view', 'exams.view',
            'lms.view', 'messages.use', 'events.view',
        ],
    ],

    /*
    | Roles that count as platform staff (school_id must be null).
    */
    'platform_roles' => [
        'super_admin', 'platform_admin', 'platform_finance', 'platform_support',
        'platform_operations', 'platform_auditor',
    ],

    /*
    | Privileged roles that must use two-factor authentication when enforcement is on.
    */
    'two_factor_roles' => [
        'super_admin', 'platform_admin', 'platform_finance', 'platform_support',
        'platform_operations', 'platform_auditor',
        'school_owner', 'school_admin', 'finance_officer', 'headteacher',
    ],
];
