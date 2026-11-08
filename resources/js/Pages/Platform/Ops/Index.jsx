import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head } from '@inertiajs/react';

export default function Index({
    errorReporting = {},
    recentErrors = [],
    security = {},
    nav = [],
    user,
    school,
}) {
    const { t } = useLocale();
    const columns = [
        {
            key: 'exception_class',
            label: t('plat.ops.col.exception'),
            render: (row) => (
                <div>
                    <p className="font-medium text-navy">{row.exception_class}</p>
                    <p className="line-clamp-2 text-xs text-slate-500">{row.message}</p>
                </div>
            ),
            cellClassName: 'whitespace-normal',
        },
        { key: 'occurrences', label: t('plat.ops.col.count') },
        { key: 'url', label: t('plat.ops.col.url'), render: (row) => row.url || '—' },
        { key: 'last_seen_at', label: t('plat.ops.col.lastSeen') },
    ];

    return (
        <AppShell title={t('plat.ops.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.ops.headTitle')} />

            <PageHeader title={t('plat.ops.heading')} subtitle={t('plat.ops.subtitle')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title={t('plat.ops.stat.reporting')}
                    value={errorReporting.enabled ? t('plat.on') : t('plat.off')}
                    tone={errorReporting.enabled ? 'teal' : 'orange'}
                    trend={errorReporting.webhook_configured ? t('plat.ops.webhookOn') : t('plat.ops.webhookOff')}
                />
                <StatCard title={t('plat.ops.stat.recent')} value={errorReporting.recent_count ?? 0} tone="navy" />
                <StatCard
                    title={t('plat.ops.stat.headers')}
                    value={security.headers_enabled ? t('plat.on') : t('plat.off')}
                    tone="blue"
                    trend={security.hsts_enabled ? t('plat.ops.hstsOn') : t('plat.ops.hstsOff')}
                />
                <StatCard title={t('plat.ops.stat.mfa')} value={security.mfa_available ? t('plat.ops.mfaAvailable') : '—'} tone="purple" />
            </div>

            <section className="mt-8">
                <h2 className="console-section-title inline-flex items-center gap-2">{t('plat.ops.fingerprints')}</h2>
                <DataTable columns={columns} rows={recentErrors} emptyMessage={t('plat.ops.empty')} />
            </section>
        </AppShell>
    );
}
