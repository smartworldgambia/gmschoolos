import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

const TAB_ROUTES = {
    requests: 'procurement.requests.index',
    orders: 'procurement.orders.index',
    invoices: 'procurement.invoices.index',
    suppliers: 'procurement.suppliers.index',
    reports: 'procurement.reports.index',
    setup: 'procurement.setup.index',
};

export default function ProcurementLayout({ title, subtitle, actions, current, shell = {}, children }) {
    const { t } = useLocale();
    const tabs = shell.procTabs ?? [];

    return (
        <AppShell title={title} nav={shell.nav} user={shell.user} school={shell.school} academicYear={shell.academicYear}>
            <Head title={title} />
            <PageHeader title={title} subtitle={subtitle} actions={actions} />
            {tabs.length > 1 ? (
                <nav className="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label={t('proc.tabs.label')}>
                    {tabs.map((key) => (
                        <Link
                            key={key}
                            href={route(TAB_ROUTES[key])}
                            className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${
                                key === current ? 'border-teal-600 text-navy' : 'border-transparent text-slate-500 hover:text-navy'
                            }`}
                            aria-current={key === current ? 'page' : undefined}
                        >
                            {t(`proc.tab.${key}`)}
                        </Link>
                    ))}
                </nav>
            ) : null}
            {children}
        </AppShell>
    );
}
