import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

const TAB_ROUTES = {
    overview: 'hr.index',
    staff: 'hr.staff.index',
    leave: 'hr.leave.index',
    payroll: 'hr.payroll.index',
    setup: 'hr.setup.index',
    myLeave: 'my.leave.index',
    myPayslips: 'my.payslips.index',
};

/** Sub-navigation of the HR area. Only the tabs the signed-in person may open are sent by the server. */
export function HrTabs({ current, tabs = [] }) {
    const { t } = useLocale();
    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav className="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label={t('hr.tabs.label')}>
            {tabs.map((key) => (
                <Link
                    key={key}
                    href={route(TAB_ROUTES[key])}
                    className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${
                        key === current ? 'border-teal-600 text-navy' : 'border-transparent text-slate-500 hover:text-navy'
                    }`}
                    aria-current={key === current ? 'page' : undefined}
                >
                    {t(`hr.tab.${key}`)}
                </Link>
            ))}
        </nav>
    );
}

/** App shell + heading + HR tab bar, so every HR page looks and behaves the same. */
export default function HrLayout({ title, subtitle, actions, current, shell = {}, children }) {
    return (
        <AppShell title={title} nav={shell.nav} user={shell.user} school={shell.school} academicYear={shell.academicYear}>
            <Head title={title} />
            <PageHeader title={title} subtitle={subtitle} actions={actions} />
            <HrTabs current={current} tabs={shell.hrTabs} />
            {children}
        </AppShell>
    );
}
