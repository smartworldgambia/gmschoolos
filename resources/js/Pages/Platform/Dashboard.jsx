import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({
    stats = {},
    health = {},
    recentSchools = [],
    lastBackup,
    nav = [],
    user,
    school,
}) {
    const l = useBillingLabels();
    const { t } = l;
    const columns = [
        {
            key: 'name',
            label: t('plat.col.school'),
            render: (row) => (
                <Link href={route('platform.schools.show', row.id)} className="font-medium text-teal hover:underline">
                    {row.name}
                </Link>
            ),
        },
        { key: 'school_type', label: t('platformSchools.col.type'), render: (row) => (row.school_type ? l.schoolType(row.school_type) : null) },
        {
            key: 'subscription_status',
            label: t('platformSchools.col.subscription'),
            render: (row) =>
                row.subscription_status ? (
                    <StatusBadge status={row.subscription_status} label={l.status(row.subscription_status)} />
                ) : null,
        },
        {
            key: 'public_url',
            label: t('plat.dash.col.publicUrl'),
            render: (row) =>
                row.public_url ? (
                    <a href={row.public_url} className="font-medium text-teal hover:underline" target="_blank" rel="noreferrer">
                        {String(row.public_url).replace(/^https?:\/\//, '')}
                    </a>
                ) : (
                    row.domain ?? '—'
                ),
        },
        {
            key: 'status',
            label: t('plat.col.status'),
            render: (row) => <StatusBadge status={row.status} />,
        },
        { key: 'created_at', label: t('plat.dash.col.created'), render: (row) => row.created_at ?? '—' },
    ];

    return (
        <AppShell title={t('plat.dash.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.dash.headTitle')} />

            <PageHeader
                title={t('plat.dash.heading')}
                subtitle={t('plat.dash.subtitle')}
                actions={
                    <Link href={route('platform.schools.index')} className="btn-navy whitespace-nowrap text-sm">
                        {t('plat.dash.manageSchools')}
                    </Link>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <StatCard title={t('plat.dash.stat.schools')} value={stats.schools} tone="navy" trend={t('plat.dash.stat.active', { n: stats.active ?? 0 })} />
                <StatCard title={t('plat.dash.stat.suspended')} value={stats.suspended} tone="orange" />
                <StatCard title={t('plat.dash.stat.alerts')} value={stats.open_alerts} tone="purple" />
                <StatCard title={t('platformDashboard.stat.revenue')} value={stats.annual_revenue} tone="teal" />
                <StatCard title={t('platformDashboard.stat.trial')} value={stats.on_trial} tone="blue" />
                <StatCard title={t('platformDashboard.stat.paid')} value={stats.paid_active} tone="teal" />
                <StatCard title={t('plat.dash.stat.students')} value={stats.students_today} tone="blue" />
                <StatCard
                    title={t('plat.dash.stat.health')}
                    value={health.status ?? '—'}
                    tone={health.status === 'ok' ? 'teal' : 'orange'}
                    trend={health.database?.detail}
                />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-5">
                <section className="lg:col-span-3">
                    <h2 className="console-section-title inline-flex items-center gap-2">{t('plat.dash.recent')}</h2>
                    <DataTable columns={columns} rows={recentSchools} emptyMessage={t('plat.dash.emptySchools')} />
                </section>

                <section className="space-y-4 lg:col-span-2">
                    <div className="console-card">
                        <h2 className="text-base font-semibold text-navy">{t('plat.dash.lastBackup')}</h2>
                        {lastBackup ? (
                            <div className="mt-3 space-y-2 text-sm text-slate-600">
                                <StatusBadge status={lastBackup.status} />
                                <p>
                                    {lastBackup.trigger} · {t('plat.backups.durationMs', { n: lastBackup.duration_ms })} · {lastBackup.created_at}
                                </p>
                                <Link href={route('platform.backups.index')} className="font-medium text-teal hover:underline">
                                    {t('plat.dash.viewRuns')}
                                </Link>
                            </div>
                        ) : (
                            <p className="mt-3 text-sm text-slate-500">{t('plat.dash.noBackup')}</p>
                        )}
                    </div>
                    <div className="console-card">
                        <h2 className="text-base font-semibold text-navy">{t('plat.dash.quickLinks')}</h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            <li>
                                <Link href={route('home')} className="text-teal hover:underline">
                                    {t('plat.dash.link.landing')}
                                </Link>
                            </li>
                            <li>
                                <Link href={route('platform.pricing.index')} className="text-teal hover:underline">
                                    {t('platformDashboard.link.pricing')}
                                </Link>
                            </li>
                            <li>
                                <Link href={route('platform.billing.index')} className="text-teal hover:underline">
                                    {t('platformDashboard.link.billing')}
                                </Link>
                            </li>
                            <li>
                                <Link href={route('platform.monitoring.index')} className="text-teal hover:underline">
                                    {t('plat.dash.link.monitoring')}
                                </Link>
                            </li>
                            <li>
                                <Link href={route('platform.backups.index')} className="text-teal hover:underline">
                                    {t('plat.dash.link.backups')}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
        </AppShell>
    );
}
