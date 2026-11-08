import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';

const STATUS_FILTERS = ['trial', 'active', 'expired', 'suspended'];

export default function Index({
    stats = {},
    schools,
    filters = {},
    types = [],
    upcomingTrials = [],
    upcomingRenewals = [],
    recentPayments = [],
    exportUrl,
    nav = [],
    user,
    school,
}) {
    const l = useBillingLabels();
    const { t } = l;

    function applyFilters(next) {
        router.get(route('platform.billing.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    }

    const schoolCell = (row) =>
        row.school_url ? (
            <Link href={row.school_url} className="font-medium text-teal hover:underline">
                {row.school}
            </Link>
        ) : (
            row.school
        );

    const columns = [
        { key: 'school', label: t('platformBilling.col.school'), render: schoolCell },
        { key: 'school_type', label: t('platformBilling.col.type'), render: (r) => l.schoolType(r.school_type) },
        {
            key: 'status',
            label: t('platformBilling.col.status'),
            render: (r) => <StatusBadge status={r.status} label={l.status(r.status)} />,
        },
        {
            key: 'payment_status',
            label: t('platformBilling.col.payment'),
            render: (r) => <StatusBadge status={r.payment_status} label={l.payStatus(r.payment_status)} />,
        },
        { key: 'annual_label', label: t('platformBilling.col.amount'), render: (r) => r.annual_label ?? t('billing.notSet') },
        { key: 'ends_at', label: t('platformBilling.col.ends'), render: (r) => l.date(r.ends_at) },
        { key: 'days_remaining', label: t('platformBilling.col.days') },
    ];

    const upcomingColumns = columns.filter((c) => ['school', 'school_type', 'ends_at', 'days_remaining'].includes(c.key));

    const paymentColumns = [
        { key: 'school', label: t('platformBilling.col.school') },
        { key: 'receipt_number', label: t('billing.col.receipt'), render: (r) => r.receipt_number ?? r.reference },
        { key: 'method', label: t('platformBilling.col.method'), render: (r) => l.method(r.method) },
        { key: 'total_label', label: t('platformBilling.col.amount') },
        { key: 'paid_at', label: t('platformBilling.col.paid'), render: (r) => l.date(r.paid_at) },
        { key: 'period_end', label: t('platformBilling.col.coveredUntil'), render: (r) => l.date(r.period_end) },
        {
            key: 'status',
            label: t('platformBilling.col.status'),
            render: (r) => <StatusBadge status={r.status} label={l.paymentStatus(r.status)} />,
        },
    ];

    const revenue = (stats.revenue_year ?? []).map((r) => r.label).join(' \u00b7 ') || t('platformBilling.revenueNone');

    return (
        <AppShell title={t('platformBilling.title')} nav={nav} user={user} school={school}>
            <Head title={t('platformBilling.title')} />

            <PageHeader
                title={t('platformBilling.title')}
                subtitle={t('platformBilling.subtitle')}
                actions={
                    <>
                        <Link href={route('platform.payments.index')} className="btn-navy whitespace-nowrap text-sm">
                            {t('platformPayments.title')}
                        </Link>
                        <a href={exportUrl} className="btn-outline whitespace-nowrap text-sm">
                            {t('platformBilling.export')}
                        </a>
                    </>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title={t('platformBilling.stat.schools')} value={stats.schools} tone="navy" />
                <StatCard title={t('platformBilling.stat.trial')} value={stats.trial} tone="blue" />
                <StatCard title={t('platformBilling.stat.active')} value={stats.active} tone="teal" />
                <StatCard title={t('platformBilling.stat.expired')} value={stats.expired} tone="orange" />
                <StatCard title={t('platformBilling.stat.suspended')} value={stats.suspended} tone="orange" />
                <StatCard title={t('platformBilling.stat.pending')} value={stats.pending_payments} tone="purple" />
                <StatCard title={t('platformBilling.stat.failed')} value={stats.failed_payments} tone="purple" />
                <StatCard title={t('platformBilling.stat.revenue', { year: stats.year })} value={revenue} tone="teal" />
            </div>

            {(stats.revenue_by_type?.length || stats.revenue_by_method?.length) > 0 ? (
                <div className="mt-6 grid gap-4 md:grid-cols-2">
                    <section className="console-card">
                        <h2 className="text-sm font-semibold text-navy">{t('platformBilling.byType')}</h2>
                        <ul className="mt-3 space-y-1.5 text-sm">
                            {(stats.revenue_by_type ?? []).map((r) => (
                                <li key={r.key} className="flex justify-between gap-3">
                                    <span className="text-slate-600">{l.schoolType(r.key)}</span>
                                    <span className="font-semibold text-navy">{r.label}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                    <section className="console-card">
                        <h2 className="text-sm font-semibold text-navy">{t('platformBilling.byMethod')}</h2>
                        <ul className="mt-3 space-y-1.5 text-sm">
                            {(stats.revenue_by_method ?? []).map((r) => (
                                <li key={r.key} className="flex justify-between gap-3">
                                    <span className="text-slate-600">{l.method(r.key)}</span>
                                    <span className="font-semibold text-navy">{r.label}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            ) : null}

            <section className="mt-8">
                <h2 className="console-section-title inline-flex items-center gap-2">
                    {t('platformBilling.subscriptions')}
                </h2>
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        type="search"
                        defaultValue={filters.q ?? ''}
                        placeholder={t('platformBilling.search')}
                        className="ui-input max-w-sm"
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') applyFilters({ q: e.currentTarget.value });
                        }}
                    />
                    <select
                        className="ui-input w-auto"
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilters({ status: e.target.value })}
                    >
                        <option value="">{t('platformBilling.allStatuses')}</option>
                        {STATUS_FILTERS.map((s) => (
                            <option key={s} value={s}>
                                {l.status(s)}
                            </option>
                        ))}
                    </select>
                    <select
                        className="ui-input w-auto"
                        value={filters.type ?? ''}
                        onChange={(e) => applyFilters({ type: e.target.value })}
                    >
                        <option value="">{t('platformBilling.allTypes')}</option>
                        {types.map((ty) => (
                            <option key={ty} value={ty}>
                                {l.schoolType(ty)}
                            </option>
                        ))}
                    </select>
                </div>
                <DataTable columns={columns} rows={schools?.data ?? []} keyField="subscription_id" emptyMessage={t('platformBilling.empty')} />
                {schools?.prev_page_url || schools?.next_page_url ? (
                    <div className="mt-3 flex justify-between text-sm">
                        {schools.prev_page_url ? (
                            <Link href={schools.prev_page_url} className="font-medium text-teal hover:underline">
                                {'\u2039'}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {schools.next_page_url ? (
                            <Link href={schools.next_page_url} className="font-medium text-teal hover:underline">
                                {'\u203a'}
                            </Link>
                        ) : null}
                    </div>
                ) : null}
            </section>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <section>
                    <h2 className="console-section-title">
                        {t('platformBilling.upcomingTrials')}
                    </h2>
                    <DataTable
                        columns={upcomingColumns}
                        rows={upcomingTrials}
                        keyField="subscription_id"
                        emptyMessage={t('platformBilling.upcomingEmpty')}
                    />
                </section>
                <section>
                    <h2 className="console-section-title">
                        {t('platformBilling.upcomingRenewals')}
                    </h2>
                    <DataTable
                        columns={upcomingColumns}
                        rows={upcomingRenewals}
                        keyField="subscription_id"
                        emptyMessage={t('platformBilling.upcomingEmpty')}
                    />
                </section>
            </div>

            <section className="mt-8">
                <h2 className="console-section-title">
                    {t('platformBilling.recentPayments')}
                </h2>
                <DataTable columns={paymentColumns} rows={recentPayments} emptyMessage={t('platformBilling.paymentsEmpty')} />
            </section>
        </AppShell>
    );
}
