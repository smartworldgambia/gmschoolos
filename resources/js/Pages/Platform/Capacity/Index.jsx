import ConsoleBanner from '@/Components/SchoolOS/ConsoleBanner';
import ConsoleSection from '@/Components/SchoolOS/ConsoleSection';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';

export default function Index({
    models = [],
    certification = {},
    isolation = {},
    schools = [],
    twoMillionCertified = false,
    urls = {},
    nav = [],
    user,
    school,
}) {
    const { t } = useLocale();
    const recordForm = useForm({
        gate: 'gate2',
        profile: 'A',
        environment: 'staging',
        p95_ms: '',
        error_rate_pct: '',
        rps: '',
        vus: '',
        duration_seconds: '',
        notes: '',
    });

    const isoForm = useForm({
        school_id: schools[0]?.id ?? '',
        isolation_tier: 'shared',
        plan_dedicated: false,
        host: '',
        database: '',
    });

    const gates = certification.gates ?? {};
    const certified = twoMillionCertified || certification.two_million_certified;

    return (
        <AppShell title={t('plat.cap.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.cap.headTitle')} />

            <PageHeader
                title={t('plat.cap.heading')}
                subtitle={t('plat.cap.subtitle')}
                actions={
                    <button type="button" className="btn-navy whitespace-nowrap text-sm" onClick={() => router.post(urls.remodel)}>
                        {t('plat.cap.refresh')}
                    </button>
                }
            />

            <ConsoleBanner tone="warn">
                {t('plat.cap.certified', { value: certified ? t('plat.yes') : t('plat.no') })}
            </ConsoleBanner>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {['gate1', 'gate2', 'gate3', 'gate4'].map((g) => (
                    <StatCard
                        key={g}
                        title={g.toUpperCase()}
                        value={gates[g]?.status ?? t('plat.cap.notRun')}
                        tone={gates[g]?.status === 'passed' ? 'teal' : 'orange'}
                        trend={gates[g]?.ran_at ? `${gates[g].profile} · ${gates[g].ran_at}` : undefined}
                    />
                ))}
            </div>

            <ConsoleSection className="mb-8" title={t('plat.cap.models')} icon="capacity">
                <DataTable
                    columns={[
                        { key: 'profile', label: t('plat.cap.col.profile') },
                        { key: 'label', label: t('plat.cap.col.label') },
                        { key: 'assumed_active_users', label: t('plat.cap.col.users') },
                        { key: 'estimated_rps', label: t('plat.cap.col.rps') },
                        { key: 'estimated_db_connections', label: t('plat.cap.col.db') },
                        { key: 'recommended_app_replicas', label: t('plat.cap.col.app') },
                        { key: 'recommended_workers', label: t('plat.cap.col.workers') },
                    ]}
                    rows={models}
                    emptyMessage={t('plat.cap.emptyModels')}
                />
            </ConsoleSection>

            <div className="grid gap-6 lg:grid-cols-2">
                <ConsoleSection title={t('plat.cap.record')} icon="ops">
                    <form
                        className="console-card space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            recordForm.post(urls.record, { preserveScroll: true });
                        }}
                    >
                        <div className="grid grid-cols-2 gap-2">
                            <select className="ui-input" value={recordForm.data.gate} onChange={(e) => recordForm.setData('gate', e.target.value)}>
                                <option value="gate1">gate1</option>
                                <option value="gate2">gate2</option>
                                <option value="gate3">gate3</option>
                                <option value="gate4">gate4</option>
                            </select>
                            <select className="ui-input" value={recordForm.data.profile} onChange={(e) => recordForm.setData('profile', e.target.value)}>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                            </select>
                        </div>
                        <input className="w-full ui-input" placeholder={t('plat.cap.p95')} value={recordForm.data.p95_ms} onChange={(e) => recordForm.setData('p95_ms', e.target.value)} required />
                        <input className="w-full ui-input" placeholder={t('plat.cap.errorRate')} value={recordForm.data.error_rate_pct} onChange={(e) => recordForm.setData('error_rate_pct', e.target.value)} required />
                        <input className="w-full ui-input" placeholder={t('plat.cap.rps')} value={recordForm.data.rps} onChange={(e) => recordForm.setData('rps', e.target.value)} required />
                        <button type="submit" className="btn-teal whitespace-nowrap text-sm">
                            {t('plat.cap.recordRun')}
                        </button>
                    </form>

                    <ConsoleSection className="mt-8" title={t('plat.cap.recent')} icon="monitoring">
                    <DataTable
                        columns={[
                            { key: 'gate', label: t('plat.cap.col.gate') },
                            { key: 'profile', label: t('plat.cap.col.profile') },
                            { key: 'status', label: t('plat.col.status'), render: (r) => <StatusBadge status={r.status} /> },
                            { key: 'p95_ms', label: t('plat.cap.col.p95') },
                            { key: 'ran_at', label: t('plat.col.when') },
                        ]}
                        rows={certification.latest ?? []}
                        emptyMessage={t('plat.cap.emptyRuns')}
                    />
                    </ConsoleSection>
                </ConsoleSection>

                <ConsoleSection title={t('plat.cap.tiers')} icon="schools">
                    <div className="mb-4 grid grid-cols-3 gap-3">
                        <StatCard title={t('plat.cap.shared')} value={isolation.counts?.shared ?? 0} tone="navy" />
                        <StatCard title={t('plat.cap.premium')} value={isolation.counts?.premium_isolated ?? 0} tone="teal" />
                        <StatCard title={t('plat.cap.dedicated')} value={isolation.counts?.dedicated_db ?? 0} tone="purple" />
                    </div>
                    <DataTable
                        columns={[
                            { key: 'name', label: t('plat.col.school') },
                            { key: 'isolation_tier', label: t('plat.cap.col.tier'), render: (r) => <StatusBadge status={r.isolation_tier} label={t(`plat.cap.tier.${r.isolation_tier}`)} /> },
                            { key: 'partition_key', label: t('plat.cap.col.partition') },
                        ]}
                        rows={schools}
                        emptyMessage={t('plat.cap.emptySchools')}
                    />
                    <form
                        className="console-card mt-4 space-y-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            isoForm.post(urls.isolation, { preserveScroll: true });
                        }}
                    >
                        <select className="w-full ui-input" value={isoForm.data.school_id} onChange={(e) => isoForm.setData('school_id', e.target.value)}>
                            {schools.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                        <select className="w-full ui-input" value={isoForm.data.isolation_tier} onChange={(e) => isoForm.setData('isolation_tier', e.target.value)}>
                            <option value="shared">{t('plat.cap.tier.shared')}</option>
                            <option value="premium_isolated">{t('plat.cap.tier.premium_isolated')}</option>
                            <option value="dedicated_db">{t('plat.cap.tier.dedicated_db')}</option>
                        </select>
                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                checked={isoForm.data.plan_dedicated}
                                onChange={(e) => isoForm.setData('plan_dedicated', e.target.checked)}
                            />
                            {t('plat.cap.planDedicated')}
                        </label>
                        <button type="submit" className="btn-navy whitespace-nowrap text-sm">
                            {t('plat.cap.updateTier')}
                        </button>
                    </form>
                </ConsoleSection>
            </div>
        </AppShell>
    );
}
