import ConsoleBanner from '@/Components/SchoolOS/ConsoleBanner';
import ConsoleSection from '@/Components/SchoolOS/ConsoleSection';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';

export default function Index({
    pools = [],
    autoscaling = {},
    cost = {},
    slowQueries = [],
    queryProfileEnabled = false,
    cacheWarmUrl,
    nav = [],
    user,
    school,
}) {
    const { t } = useLocale();
    const poolColumns = [
        { key: 'name', label: t('plat.scale.col.pool') },
        { key: 'queue', label: t('plat.scale.col.queue') },
        { key: 'pending', label: t('plat.scale.col.pending'), render: (row) => row.pending ?? '—' },
        {
            key: 'sheddable',
            label: t('plat.scale.col.shed'),
            render: (row) => (row.sheddable ? t('plat.yes') : t('plat.no')),
        },
        {
            key: 'status',
            label: t('plat.col.status'),
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const costColumns = [
        { key: 'school', label: t('plat.col.school') },
        { key: 'units', label: t('plat.scale.col.units') },
        { key: 'students', label: t('plat.scale.col.studentsApprox') },
        { key: 'requests', label: t('plat.col.requests') },
        { key: 'exports', label: t('plat.col.exports') },
        { key: 'imports', label: t('plat.col.imports') },
    ];

    const slowColumns = [
        { key: 'ms', label: t('plat.scale.col.ms') },
        { key: 'sql', label: t('plat.scale.col.sql'), cellClassName: 'max-w-xl whitespace-normal font-mono text-xs' },
        { key: 'at', label: t('plat.col.when') },
    ];

    return (
        <AppShell title={t('plat.scale.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.scale.headTitle')} />

            <PageHeader
                title={t('plat.scale.heading')}
                subtitle={t('plat.scale.subtitle')}
                actions={
                    <button type="button" className="btn-teal whitespace-nowrap text-sm" onClick={() => router.post(cacheWarmUrl)}>
                        {t('plat.scale.warm')}
                    </button>
                }
            />

            <ConsoleBanner>
                <p className="break-words">
                    <span className="font-medium text-navy">{t('plat.scale.metrics')}</span>
                    {' — '}
                    {t('plat.scale.metricsHelp')}
                </p>
            </ConsoleBanner>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title={t('plat.scale.stat.queue')} value={autoscaling.current_queue_depth} tone="navy" />
                <StatCard
                    title={t('plat.scale.stat.workers')}
                    value={`${autoscaling.worker_min}–${autoscaling.worker_max}`}
                    tone="teal"
                    trend={autoscaling.recommend_scale_workers ? t('plat.scale.scaleOut') : t('plat.scale.within')}
                />
                <StatCard
                    title={t('plat.scale.stat.app')}
                    value={`${autoscaling.app_min}–${autoscaling.app_max}`}
                    tone="blue"
                    trend={t('plat.scale.cpuTarget', { n: autoscaling.target_cpu_pct })}
                />
                <StatCard
                    title={t('plat.scale.stat.cost')}
                    value={`${cost.budget_used_pct ?? 0}%`}
                    tone="purple"
                    trend={t('plat.scale.units', { used: cost.total_units ?? 0, budget: cost.period_budget_units ?? 0 })}
                />
            </div>

            <ConsoleSection className="mt-8" title={t('plat.scale.pools')} icon="scale">
                <DataTable columns={poolColumns} rows={pools} emptyMessage={t('plat.scale.emptyPools')} />
            </ConsoleSection>

            <div className="mt-8 grid gap-6 xl:grid-cols-2">
                <ConsoleSection title={t('plat.scale.costTitle')} icon="pricing" help={t('plat.scale.costHelp', { from: cost.from, to: cost.to })}>
                    <DataTable columns={costColumns} rows={cost.top ?? []} emptyMessage={t('plat.scale.emptyCost')} />
                </ConsoleSection>
                <ConsoleSection title={queryProfileEnabled ? t('plat.scale.slowOn') : t('plat.scale.slowOff')} icon="ops">
                    <DataTable columns={slowColumns} rows={slowQueries} emptyMessage={t('plat.scale.emptySlow')} />
                </ConsoleSection>
            </div>
        </AppShell>
    );
}
