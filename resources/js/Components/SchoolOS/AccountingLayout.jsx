import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

const TAB_ROUTES = {
    overview: 'accounting.index',
    journal: 'accounting.journals',
    ledger: 'accounting.ledger',
    trialBalance: 'accounting.trial-balance',
    income: 'accounting.income',
    balance: 'accounting.balance',
    cashbook: 'accounting.cashbook',
    setup: 'accounting.setup',
};

export function AisTabs({ current, tabs = [] }) {
    const { t } = useLocale();
    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav className="module-tabs" aria-label={t('ais.tabs.label')}>
            {tabs.map((key) => (
                <Link
                    key={key}
                    href={route(TAB_ROUTES[key])}
                    className={`module-tab ${key === current ? 'module-tab-active' : ''}`}
                    aria-current={key === current ? 'page' : undefined}
                >
                    {t(`ais.tab.${key}`)}
                </Link>
            ))}
        </nav>
    );
}

export default function AccountingLayout({ title, subtitle, actions, current, shell = {}, children, breadcrumbs, icon = 'ais' }) {
    return (
        <AppShell title={title} nav={shell.nav} user={shell.user} school={shell.school} academicYear={shell.academicYear}>
            <Head title={title} />
            <PageHeader title={title} subtitle={subtitle} actions={actions} breadcrumbs={breadcrumbs} icon={icon} />
            <AisTabs current={current} tabs={shell.aisTabs} />
            {children}
        </AppShell>
    );
}
