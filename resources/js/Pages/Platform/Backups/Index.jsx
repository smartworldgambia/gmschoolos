import ConsoleBanner from '@/Components/SchoolOS/ConsoleBanner';
import ConsoleSection from '@/Components/SchoolOS/ConsoleSection';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';

export default function Index({ runs = [], runUrl, nav = [], user, school }) {
    const { t } = useLocale();
    const latest = runs[0];
    const columns = [
        {
            key: 'status',
            label: t('plat.col.status'),
            render: (row) => <StatusBadge status={row.status} />,
        },
        { key: 'trigger', label: t('plat.backups.col.trigger') },
        { key: 'duration_ms', label: t('plat.backups.col.duration'), render: (row) => t('plat.backups.durationMs', { n: row.duration_ms ?? '—' }) },
        { key: 'runner', label: t('plat.backups.col.by'), render: (row) => row.runner ?? t('plat.backups.system') },
        { key: 'created_at', label: t('plat.col.when') },
        {
            key: 'notes',
            label: t('plat.backups.col.notes'),
            render: (row) => row.notes ?? '—',
            cellClassName: 'whitespace-normal max-w-sm',
        },
    ];

    return (
        <AppShell title={t('plat.backups.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.backups.headTitle')} />

            <PageHeader
                title={t('plat.backups.heading')}
                subtitle={t('plat.backups.subtitle')}
                actions={
                    <button type="button" className="btn-teal whitespace-nowrap" onClick={() => router.post(runUrl)}>
                        {t('plat.backups.run')}
                    </button>
                }
            />

            <ConsoleBanner>
                <p>{t('plat.backups.help')}</p>
            </ConsoleBanner>

            <DataTable columns={columns} rows={runs} emptyMessage={t('plat.backups.empty')} />

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                {latest?.checks?.length ? (
                    <ConsoleSection className="lg:col-span-2" title={t('plat.backups.latest')} icon="backups">
                        <ul className="console-card space-y-3 text-sm">
                            {latest.checks.map((check) => (
                                <li key={check.name} className="flex items-start justify-between gap-3 border-b border-surface-muted pb-3 last:border-0 last:pb-0">
                                    <span className="inline-flex min-w-0 items-start gap-2 font-medium text-navy">
                                        <span
                                            className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${check.ok ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'}`}
                                            aria-hidden="true"
                                        >
                                            {check.ok ? (
                                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M5 13l4 4L19 7" />
                                                </svg>
                                            ) : (
                                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            )}
                                        </span>
                                        {check.name}
                                    </span>
                                    <span className={`text-end ${check.ok ? 'text-teal' : 'text-red-600'}`}>
                                        {check.ok ? t('plat.ok') : t('plat.fail')} — {check.detail}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </ConsoleSection>
                ) : (
                    <div className="lg:col-span-2" />
                )}

                <section className="console-card">
                    <h2 className="flex items-center gap-2 text-base font-semibold text-navy">
                        <span className="flex h-9 w-9 items-center justify-center rounded-2xl bg-teal/10 text-teal" aria-hidden="true">
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M4 7a8 4 0 0116 0v10a8 4 0 01-16 0V7zm0 5a8 4 0 0016 0" />
                            </svg>
                        </span>
                        {t('plat.backups.health')}
                    </h2>
                    {latest ? (
                        <dl className="mt-4 space-y-3 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-slate-500">{t('plat.col.status')}</dt>
                                <dd>
                                    <StatusBadge status={latest.status === 'passed' ? 'healthy' : latest.status} label={latest.status} />
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-slate-500">{t('plat.backups.lastRun')}</dt>
                                <dd className="text-navy">{latest.created_at}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-slate-500">{t('plat.backups.col.duration')}</dt>
                                <dd className="text-navy">{t('plat.backups.durationMs', { n: latest.duration_ms ?? '—' })}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-slate-500">{t('plat.backups.result')}</dt>
                                <dd>
                                    <StatusBadge status={latest.status} />
                                </dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="mt-3 text-sm text-slate-500">{t('plat.backups.empty')}</p>
                    )}
                </section>
            </div>
        </AppShell>
    );
}
