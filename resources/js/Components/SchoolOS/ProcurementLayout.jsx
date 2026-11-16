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
                <nav className="module-tabs" aria-label={t('proc.tabs.label')}>
                    {tabs.map((key) => (
                        <Link
                            key={key}
                            href={route(TAB_ROUTES[key])}
                            className={`module-tab ${key === current ? 'module-tab-active' : ''}`}
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
