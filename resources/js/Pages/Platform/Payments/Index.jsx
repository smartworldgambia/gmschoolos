import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({
    payments,
    filters = {},
    statuses = [],
    methods = [],
    stats = {},
    exportUrl,
    nav = [],
    user,
    school,
}) {
    const l = useBillingLabels();
    const { t } = l;

    function applyFilters(next) {
        router.get(route('platform.payments.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    }

    const columns = [
        {
            key: 'school',
            label: t('platformPayments.col.school'),
            render: (r) => (
                <Link href={r.url} className="font-medium text-teal hover:underline">
                    {r.school}
                </Link>
            ),
        },
        { key: 'reference', label: t('platformPayments.col.reference'), render: (r) => <span dir="ltr">{r.reference}</span> },
        { key: 'method', label: t('platformPayments.col.method'), render: (r) => l.method(r.method) },
        {
            key: 'status',
            label: t('platformPayments.col.status'),
            render: (r) => <StatusBadge status={r.status} label={l.paymentStatus(r.status)} />,
        },
        { key: 'total_label', label: t('platformPayments.col.amount') },
        { key: 'charged_label', label: t('platformPayments.col.charged'), render: (r) => r.charged_label ?? t('platformPayments.no') },
        {
            key: 'has_evidence',
            label: t('platformPayments.col.evidence'),
            render: (r) => (r.method === 'bank_transfer' ? (r.has_evidence ? t('platformPayments.yes') : t('platformPayments.no')) : t('platformPayments.no')),
        },
        { key: 'created_at', label: t('platformPayments.col.created'), render: (r) => l.date(r.created_at) },
    ];

    return (
        <AppShell title={t('platformPayments.title')} nav={nav} user={user} school={school}>
            <Head title={t('platformPayments.title')} />

            <PageHeader
                title={t('platformPayments.title')}
                subtitle={t('platformPayments.subtitle')}
                actions={
                    <>
                        <Link href={route('platform.payments.reconciliation')} className="btn-outline whitespace-nowrap text-sm">
                            {t('platformPayments.reconciliation')}
                        </Link>
                        <Link href={route('platform.payments.settings')} className="btn-outline whitespace-nowrap text-sm">
                            {t('platformPayments.settings')}
                        </Link>
                        <a href={exportUrl} className="btn-navy whitespace-nowrap text-sm">
                            {t('platformPayments.export')}
                        </a>
                    </>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard title={t('platformPayments.stat.awaiting')} value={stats.awaiting_verification} tone="orange" />
                <StatCard title={t('platformPayments.stat.openCash')} value={stats.open_cash} tone="blue" />
                <StatCard title={t('platformPayments.stat.openGateway')} value={stats.open_gateway} tone="teal" />
                <StatCard title={t('platformPayments.stat.failed')} value={stats.failed} tone="purple" />
                <StatCard title={t('platformPayments.stat.refunded')} value={stats.refunded} tone="navy" />
            </div>

            <div className="mb-4 mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    type="search"
                    defaultValue={filters.q ?? ''}
                    placeholder={t('platformPayments.search')}
                    className="ui-input max-w-sm"
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') applyFilters({ q: e.currentTarget.value });
                    }}
                />
                <select className="ui-input w-auto" value={filters.status ?? ''} onChange={(e) => applyFilters({ status: e.target.value })}>
                    <option value="">{t('platformPayments.allStatuses')}</option>
                    {statuses.map((s) => (
                        <option key={s} value={s}>
                            {l.paymentStatus(s)}
                        </option>
                    ))}
                </select>
                <select className="ui-input w-auto" value={filters.method ?? ''} onChange={(e) => applyFilters({ method: e.target.value })}>
                    <option value="">{t('platformPayments.allMethods')}</option>
                    {methods.map((m) => (
                        <option key={m} value={m}>
                            {l.method(m)}
                        </option>
                    ))}
                </select>
            </div>

            <DataTable columns={columns} rows={payments?.data ?? []} emptyMessage={t('platformPayments.empty')} />

            {payments?.prev_page_url || payments?.next_page_url ? (
                <div className="mt-3 flex justify-between text-sm">
                    {payments.prev_page_url ? (
                        <Link href={payments.prev_page_url} className="font-medium text-teal hover:underline">
                            {'\u2039'}
                        </Link>
                    ) : (
                        <span />
                    )}
                    {payments.next_page_url ? (
                        <Link href={payments.next_page_url} className="font-medium text-teal hover:underline">
                            {'\u203a'}
                        </Link>
                    ) : null}
                </div>
            ) : null}
        </AppShell>
    );
}
