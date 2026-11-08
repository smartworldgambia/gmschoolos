<?php

namespace App\Support;

use App\Models\User;

class SchoolNav
{
    public static function for(User $user): array
    {
        if ($user->can('portal.admin')) {
            return self::visible($user, [
                ['key' => 'dashboard', 'name' => 'Dashboard', 'href' => route('admin.dashboard'), 'current' => request()->routeIs('admin.dashboard')],
                ['key' => 'setup', 'name' => 'School setup', 'href' => route('setup.index'), 'current' => request()->routeIs('setup.*')],
                ['key' => 'website', 'name' => 'Website', 'href' => route('website.edit'), 'current' => request()->routeIs('website.*')],
                ['key' => 'domain', 'name' => 'Domain', 'href' => route('school.domains.edit'), 'current' => request()->routeIs('school.domains.*')],
                ['key' => 'admissions', 'name' => 'Admissions', 'href' => route('admissions.index'), 'current' => request()->routeIs('admissions.*')],
                ['key' => 'students', 'name' => 'Students', 'href' => route('students.index'), 'current' => request()->routeIs('students.*')],
                ['key' => 'academics', 'name' => 'Academics', 'href' => route('academics.index'), 'current' => request()->routeIs('academics.index')],
                ['key' => 'attendance', 'name' => 'Attendance', 'href' => route('attendance.index'), 'current' => request()->routeIs('attendance.*')],
                ['key' => 'timetable', 'name' => 'Timetable', 'href' => route('timetable.index'), 'current' => request()->routeIs('timetable.*')],
                ['key' => 'exams', 'name' => 'Exams', 'href' => route('examinations.index'), 'current' => request()->routeIs('examinations.*')],
                ['key' => 'reportCards', 'name' => 'Report cards', 'href' => route('reporting.index'), 'current' => request()->routeIs('reporting.*')],
                ['key' => 'externalExams', 'name' => 'GABECE & WASSCE', 'href' => route('external-exams.index'), 'current' => request()->routeIs('external-exams.*')],
                ['key' => 'seniorSecondary', 'name' => 'Senior Secondary', 'href' => route('senior-secondary.rounds.index'), 'current' => request()->routeIs('senior-secondary.*')],
                ['key' => 'records', 'name' => 'Records Office', 'href' => route('records.index'), 'current' => request()->routeIs('records.*')],
                ['key' => 'fees', 'name' => 'Fees', 'href' => route('fees.index'), 'current' => request()->routeIs('fees.*') && ! request()->routeIs('fees.reconciliation.*')],
                ['key' => 'reconciliation', 'name' => 'Reconciliation', 'href' => route('fees.reconciliation.index'), 'current' => request()->routeIs('fees.reconciliation.*')],
                ['key' => 'lms', 'name' => 'LMS', 'href' => route('lms.courses.index'), 'current' => request()->routeIs('lms.*') && ! request()->routeIs('lms.external.*')],
                ['key' => 'externalContent', 'name' => 'External content', 'href' => route('lms.external.index'), 'current' => request()->routeIs('lms.external.*')],
                ['key' => 'hr', 'name' => 'HR', 'href' => route('hr.index'), 'current' => request()->routeIs('hr.*')],
                ['key' => 'myHr', 'name' => 'My HR', 'href' => route('my.leave.index'), 'current' => request()->routeIs('my.*')],
                ['key' => 'library', 'name' => 'Library', 'href' => route('library.index'), 'current' => request()->routeIs('library.*')],
                ['key' => 'inventory', 'name' => 'Inventory', 'href' => route('inventory.items.index'), 'current' => request()->routeIs('inventory.*')],
                ['key' => 'procurement', 'name' => 'Procurement', 'href' => route('procurement.requests.index'), 'current' => request()->routeIs('procurement.*')],
                ['key' => 'transport', 'name' => 'Transport', 'href' => route('transport.index'), 'current' => request()->routeIs('transport.*')],
                ['key' => 'boarding', 'name' => 'Boarding', 'href' => route('boarding.index'), 'current' => request()->routeIs('boarding.*')],
                ['key' => 'safeguarding', 'name' => 'Safeguarding', 'href' => route('safeguarding.index'), 'current' => request()->routeIs('safeguarding.*') && ! request()->routeIs('safeguarding.report')],
                ['key' => 'events', 'name' => 'Events', 'href' => route('events.index'), 'current' => request()->routeIs('events.*')],
                ['key' => 'reports', 'name' => 'Reports', 'href' => route('reports.index'), 'current' => request()->routeIs('reports.*')],
                ['key' => 'exports', 'name' => 'Exports', 'href' => route('exports.index'), 'current' => request()->routeIs('exports.*')],
                ['key' => 'billing', 'name' => 'Billing', 'href' => route('billing.index'), 'current' => request()->routeIs('billing.*')],
                ['key' => 'messages', 'name' => 'Messages', 'href' => route('messages.index'), 'current' => request()->routeIs('messages.*')],
                ['key' => 'announcements', 'name' => 'Announcements', 'href' => route('announcements.index'), 'current' => request()->routeIs('announcements.*')],
                ['key' => 'accounts', 'name' => 'User accounts', 'href' => route('accounts.index'), 'current' => request()->routeIs('accounts.*')],
                ['key' => 'communications', 'name' => 'Communications', 'href' => route('communications.index'), 'current' => request()->routeIs('communications.*')],
                ['key' => 'notifications', 'name' => 'Notifications', 'href' => route('notifications.index'), 'current' => request()->routeIs('notifications.*')],
            ]);
        }

        if ($user->can('portal.teacher')) {
            return [
                ['key' => 'dashboard', 'name' => 'Dashboard', 'href' => route('teacher.dashboard'), 'current' => request()->routeIs('teacher.dashboard')],
                ['key' => 'attendance', 'name' => 'Attendance', 'href' => route('attendance.index'), 'current' => request()->routeIs('attendance.*')],
                ['key' => 'timetable', 'name' => 'Timetable', 'href' => route('timetable.index'), 'current' => request()->routeIs('timetable.*')],
                ['key' => 'exams', 'name' => 'Exams', 'href' => route('examinations.index'), 'current' => request()->routeIs('examinations.*')],
                ['key' => 'reportCards', 'name' => 'Report cards', 'href' => route('reporting.index'), 'current' => request()->routeIs('reporting.*')],
                ['key' => 'lms', 'name' => 'LMS', 'href' => route('lms.courses.index'), 'current' => request()->routeIs('lms.*') && ! request()->routeIs('lms.external.*')],
                ['key' => 'students', 'name' => 'Students', 'href' => route('students.index'), 'current' => request()->routeIs('students.*')],
                ['key' => 'academics', 'name' => 'Academics', 'href' => route('academics.index'), 'current' => request()->routeIs('academics.*')],
                ['key' => 'messages', 'name' => 'Messages', 'href' => route('messages.index'), 'current' => request()->routeIs('messages.*')],
                ['key' => 'announcements', 'name' => 'Announcements', 'href' => route('announcements.index'), 'current' => request()->routeIs('announcements.*')],
                ['key' => 'myHr', 'name' => 'My HR', 'href' => route('my.leave.index'), 'current' => request()->routeIs('my.*')],
                ['key' => 'events', 'name' => 'Events', 'href' => route('events.index'), 'current' => request()->routeIs('events.*')],
                ['key' => 'reportConcern', 'name' => 'Report a concern', 'href' => route('safeguarding.report'), 'current' => request()->routeIs('safeguarding.report')],
                ['key' => 'notifications', 'name' => 'Notifications', 'href' => route('notifications.index'), 'current' => request()->routeIs('notifications.*')],
            ];
        }

        if ($user->can('portal.parent')) {
            return [
                ['key' => 'dashboard', 'name' => 'Dashboard', 'href' => route('parent.dashboard'), 'current' => request()->routeIs('parent.*')],
                ['key' => 'children', 'name' => 'Children', 'href' => route('students.index'), 'current' => request()->routeIs('students.*')],
                ['key' => 'timetable', 'name' => 'Timetable', 'href' => route('timetable.index'), 'current' => request()->routeIs('timetable.*')],
                ['key' => 'reportCards', 'name' => 'Report cards', 'href' => route('reporting.index'), 'current' => request()->routeIs('reporting.*')],
                ['key' => 'fees', 'name' => 'Fees', 'href' => route('fees.index'), 'current' => request()->routeIs('fees.*')],
                ['key' => 'messages', 'name' => 'Messages', 'href' => route('messages.index'), 'current' => request()->routeIs('messages.*')],
                ['key' => 'announcements', 'name' => 'Announcements', 'href' => route('announcements.index'), 'current' => request()->routeIs('announcements.*')],
                ['key' => 'events', 'name' => 'Events', 'href' => route('events.index'), 'current' => request()->routeIs('events.*')],
                ['key' => 'notifications', 'name' => 'Notifications', 'href' => route('notifications.index'), 'current' => request()->routeIs('notifications.*')],
            ];
        }

        return [
            ['key' => 'dashboard', 'name' => 'Dashboard', 'href' => route('student.dashboard'), 'current' => request()->routeIs('student.dashboard')],
            ['key' => 'timetable', 'name' => 'Timetable', 'href' => route('timetable.index'), 'current' => request()->routeIs('timetable.*')],
            ['key' => 'reportCards', 'name' => 'Report cards', 'href' => route('reporting.index'), 'current' => request()->routeIs('reporting.*')],
            ['key' => 'myCourses', 'name' => 'My courses', 'href' => route('lms.courses.index'), 'current' => request()->routeIs('lms.*')],
            ['key' => 'fees', 'name' => 'Fees', 'href' => route('fees.index'), 'current' => request()->routeIs('fees.*')],
            ['key' => 'messages', 'name' => 'Messages', 'href' => route('messages.index'), 'current' => request()->routeIs('messages.*')],
            ['key' => 'announcements', 'name' => 'Announcements', 'href' => route('announcements.index'), 'current' => request()->routeIs('announcements.*')],
            ['key' => 'events', 'name' => 'Events', 'href' => route('events.index'), 'current' => request()->routeIs('events.*')],
            ['key' => 'notifications', 'name' => 'Notifications', 'href' => route('notifications.index'), 'current' => request()->routeIs('notifications.*')],
        ];
    }

    /** Navigation items an admin-portal user may actually open, filtered by permission. */
    protected static function visible(User $user, array $items): array
    {
        $required = [
            'website' => 'website.manage',
            'domain' => 'domains.manage',
            'admissions' => 'admissions.view',
            'students' => 'students.view',
            'academics' => 'academics.view',
            'attendance' => 'attendance.record|attendance.approve|attendance.staff',
            'timetable' => 'timetable.manage',
            'exams' => 'exams.manage',
            'reportCards' => 'exams.view|exams.mark|exams.manage|students.view',
            'externalExams' => 'external_exams.view',
            'seniorSecondary' => 'senior_admissions.view',
            'records' => 'records.manage|senior_admissions.view',
            'fees' => 'fees.view',
            'reconciliation' => 'payments.verify',
            'lms' => 'lms.view',
            'externalContent' => 'lms.manage',
            'hr' => 'hr.view',
            'myHr' => 'staff.self',
            'library' => 'library.manage',
            'inventory' => 'inventory.manage',
            'procurement' => 'procurement.request|procurement.manage|procurement.approve',
            'transport' => 'transport.manage',
            'boarding' => 'boarding.manage',
            'safeguarding' => 'safeguarding.manage',
            'events' => 'events.view|events.manage',
            'reports' => 'reports.view',
            'exports' => 'reports.export',
            'billing' => 'subscriptions.view',
            'messages' => 'messages.use',
            'accounts' => 'users.manage',
            'communications' => 'communications.manage',
        ];

        // "School setup" is offered only to schools that registered through the wizard and have not finished it.
        $setupOpen = $user->school_id
            && $user->school?->terms_accepted_at !== null
            && $user->school?->setup_completed_at === null
            && $user->can('school.settings.manage');

        return array_values(array_filter(
            $items,
            function (array $item) use ($required, $user, $setupOpen) {
                if ($item['key'] === 'setup') {
                    return $setupOpen;
                }
                // people who run HR already reach their own leave from the HR tabs
                if ($item['key'] === 'myHr' && $user->can('hr.view')) {
                    return false;
                }

                return ! isset($required[$item['key']]) || $user->canAny(explode('|', $required[$item['key']]));
            }
        ));
    }
}