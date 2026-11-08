import ConsoleSection from '@/Components/SchoolOS/ConsoleSection';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';

export default function Index({
    health = {},
    alerts = [],
    usageTotals = [],
    nav = [],
    user,
    school,
}) {
    const { t } = useLocale();
    const form = useForm({
        severity: 'warning',
        title: '',
        message: '',
    });

    const columns = [
        {
            key: 'severity',
            label: t('plat.mon.col.severity'),
            render: (row) => <StatusBadge status={row.severity} />,
        },
        { key: 'source', label: t('plat.mon.col.source') },
        {
            key: 'title',
            label: t('plat.mon.col.alert'),
            render: (row) => (
                <div>
                    <p className="font-medium text-navy">{row.title}</p>
                    <p className="text-xs text-slate-500">{row.message}</p>
                </div>
            ),
            cellClassName: 'whitespace-normal',
        },
        { key: 'school', label: t('plat.col.school'), render: (row) => row.school ?? '—' },
        { key: 'created_at', label: t('plat.col.when') },
        {
            key: 'actions',
            label: '',
            render: (row) =>
                row.is_open ? (
                    <div className="flex gap-2">
                        <button type="button" className="text-xs font-semibold text-teal hover:underline" onClick={() => router.post(route('platform.alerts.acknowledge', row.id))}>
                            {t('plat.mon.ack')}
                        </button>
                        <button type="button" className="text-xs font-semibold text-navy hover:underline" onClick={() => router.post(route('platform.alerts.resolve', row.id))}>
                            {t('plat.mon.resolve')}
                        </button>
                    </div>
                ) : (
                    <span className="text-xs text-slate-400">{t('plat.mon.resolved')}</span>
                ),
        },
    ];

    return (
        <AppShell title={t('plat.mon.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.mon.headTitle')} />

            <PageHeader title={t('plat.mon.title')} subtitle={t('plat.mon.subtitle')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title={t('plat.mon.stat.status')} value={health.status} tone={health.status === 'ok' ? 'teal' : 'orange'} />
                <StatCard title={t('plat.mon.stat.database')} value={health.database?.status} tone="navy" trend={health.database?.detail} />
                <StatCard
                    title={t('plat.mon.stat.queue')}
                    value={health.queue?.driver}
                    tone="blue"
                    trend={health.queue?.pending_jobs != null ? t('plat.mon.pendingJobs', { n: health.queue.pending_jobs }) : undefined}
                />
                <StatCard title={t('plat.mon.stat.alerts')} value={health.alerts?.open} tone="purple" />
            </div>

            <ConsoleSection className="mt-8" title={t('plat.mon.usage7')} icon="monitoring">
                <DataTable
                    columns={[
                        { key: 'date', label: t('plat.col.date') },
                        { key: 'students', label: t('plat.col.students') },
                        { key: 'requests', label: t('plat.col.requests') },
                        { key: 'exports', label: t('plat.col.exports') },
                    ]}
                    rows={usageTotals}
                    keyField="date"
                    emptyMessage={t('plat.mon.noUsage')}
                />
            </ConsoleSection>

            <div className="mt-8 grid gap-6 lg:grid-cols-5">
                <ConsoleSection className="lg:col-span-3" title={t('plat.mon.alerts')} icon="ops">
                    <DataTable columns={columns} rows={alerts} emptyMessage={t('plat.mon.empty')} />
                </ConsoleSection>
                <section className="lg:col-span-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('platform.alerts.store'), {
                                preserveScroll: true,
                                onSuccess: () => form.reset('title', 'message'),
                            });
                        }}
                        className="console-card"
                    >
                        <h2 className="text-base font-semibold text-navy">{t('plat.mon.raise')}</h2>
                        <div className="mt-3 space-y-3">
                            <select className="ui-input" value={form.data.severity} onChange={(e) => form.setData('severity', e.target.value)}>
                                <option value="info">{t('plat.mon.severity.info')}</option>
                                <option value="warning">{t('plat.mon.severity.warning')}</option>
                                <option value="critical">{t('plat.mon.severity.critical')}</option>
                            </select>
                            <input className="ui-input" placeholder={t('plat.mon.field.title')} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required />
                            <textarea className="ui-input" rows={3} placeholder={t('plat.mon.field.message')} value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} required />
                            <button type="submit" className="btn-navy w-full whitespace-nowrap text-sm">
                                {t('plat.mon.create')}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </AppShell>
    );
}
