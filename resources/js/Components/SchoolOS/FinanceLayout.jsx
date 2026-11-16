import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

const TAB_ROUTES = {
    overview: 'fees.index',
    invoices: 'fees.invoices.index',
    payments: 'fees.payments.index',
    adjustments: 'fees.adjustments.index',
    reconciliation: 'fees.reconciliation.index',
    reports: 'fees.reports.index',
    setup: 'fees.setup.index',
};

/** Sub-navigation of the fees area. Only the tabs the signed-in person may open are sent by the server. */
export function FeesTabs({ current, tabs = [] }) {
    const { t } = useLocale();
    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav className="module-tabs" aria-label={t('fin.tabs.label')}>
            {tabs.map((key) => (
                <Link
                    key={key}
                    href={route(TAB_ROUTES[key])}
                    className={`module-tab ${key === current ? 'module-tab-active' : ''}`}
                    aria-current={key === current ? 'page' : undefined}
                >
                    {t(`fin.tab.${key}`)}
                </Link>
            ))}
        </nav>
    );
}

/** App shell + heading + fees tab bar, so every finance page looks and behaves the same. */
export default function FinanceLayout({ title, subtitle, actions, current, shell = {}, children }) {
    return (
        <AppShell title={title} nav={shell.nav} user={shell.user} school={shell.school} academicYear={shell.academicYear}>
            <Head title={title} />
            <PageHeader title={title} subtitle={subtitle} actions={actions} />
            <FeesTabs current={current} tabs={shell.finTabs} />
            {children}
        </AppShell>
    );
}
