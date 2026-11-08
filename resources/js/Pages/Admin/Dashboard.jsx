import DataTable from '@/Components/SchoolOS/DataTable';
import SubscriptionBanner from '@/Components/Billing/SubscriptionBanner';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import QuickAction from '@/Components/SchoolOS/QuickAction';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

function UsersIcon() {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M9 10a4 4 0 100-8 4 4 0 000 8zm12 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
        </svg>
    );
}

function GlobeIcon() {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M12 21a9 9 0 100-18 9 9 0 000 18zM3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18" />
        </svg>
    );
}

function LinkIcon() {
    return (
        <svg className="h-4 w-4 shrink-0 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5" />
        </svg>
    );
}

export default function Dashboard({
    stats = {},
    billing = null,
    billingUrl = null,
    recentActivity = [],
    quickActions = [],
    schoolUrls = null,
    domainSettingsUrl,
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t } = useLocale();
    const page = usePage();
    const flashSchoolUrl = page.props.flash?.school_url;
    const [copied, setCopied] = useState(false);

    async function copyUrl(value) {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1600);
        } catch {
            /* ignore */
        }
    }

    const activityColumns = [
        {
            key: 'description',
            label: t('dash.admin.col.activity'),
            render: (row) => row.description ?? row.message ?? '—',
            cellClassName: 'whitespace-normal',
        },
        {
            key: 'status',
            label: t('dash.admin.col.status'),
            render: (row) => (row.status ? <StatusBadge status={row.status} /> : '—'),
        },
        {
            key: 'created_at',
            label: t('dash.admin.col.when'),
            render: (row) => row.created_at ?? row.time ?? '—',
        },
    ];

    const students = stats.students ?? stats.total_students ?? 0;
    const classes = stats.classes ?? stats.total_classes ?? 0;

    return (
        <AppShell title={t('dash.admin.headTitle')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('dash.admin.headTitle')} />

            <PageHeader title={t('dash.admin.title')} subtitle={t('dash.admin.subtitle')} />

            <SubscriptionBanner billing={billing} billingUrl={billingUrl} />

            {schoolUrls ? (
                <section className="console-hero" aria-label={t('domainAdmin.dashEyebrow')}>
                    <div className="relative z-10">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="console-title-icon">
                                    <GlobeIcon />
                                </span>
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
                                        {t('domainAdmin.dashEyebrow')}
                                    </p>
                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-teal/10 px-2 py-0.5 text-[11px] font-semibold text-teal">
                                        <span className="h-1.5 w-1.5 rounded-full bg-teal" aria-hidden="true" />
                                        {t('domainAdmin.dashLive')}
                                    </span>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <a
                                    href={flashSchoolUrl || schoolUrls.primary_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="btn-teal text-sm"
                                >
                                    {t('domainAdmin.openSite')}
                                </a>
                                <button
                                    type="button"
                                    className="btn-outline text-sm"
                                    onClick={() => copyUrl(flashSchoolUrl || schoolUrls.primary_url || schoolUrls.platform_host)}
                                >
                                    {copied ? t('domainAdmin.copied') : t('domainAdmin.copyHost')}
                                </button>
                                {domainSettingsUrl ? (
                                    <Link href={domainSettingsUrl} className="btn-navy text-sm">
                                        {t('domainAdmin.manageDomain')}
                                    </Link>
                                ) : null}
                            </div>
                        </div>
                        <div className="mt-4 flex items-center gap-2.5 rounded-xl border border-surface-muted bg-white px-3.5 py-3 shadow-sm">
                            <LinkIcon />
                            <p
                                dir="ltr"
                                className="min-w-0 flex-1 break-all text-start font-mono text-sm font-semibold text-navy sm:text-base"
                            >
                                {schoolUrls.primary_host || schoolUrls.platform_host}
                            </p>
                        </div>
                        <p className="mt-2 text-sm leading-relaxed text-slate-500">{t('domainAdmin.dashHint')}</p>
                    </div>
                </section>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title={t('dash.admin.stat.students')} value={students} trend={stats.students_trend} tone="teal" icon={<UsersIcon />} />
                <StatCard title={t('dash.admin.stat.teachers')} value={stats.teachers ?? stats.total_teachers} trend={stats.teachers_trend} tone="blue" icon={<UsersIcon />} />
                <StatCard title={t('dash.admin.stat.classes')} value={classes} trend={stats.classes_trend} tone="purple" icon={<UsersIcon />} />
                <StatCard title={t('dash.admin.stat.fees')} value={stats.fees_collected ?? stats.feesCollected} trend={stats.fees_trend} tone="orange" icon={<UsersIcon />} />
            </div>

            {quickActions.length > 0 ? (
                <section className="mt-8">
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        {t('dash.admin.quick.title')}
                    </h2>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {quickActions.map((action, i) => (
                            <QuickAction
                                key={action.href ?? action.key}
                                href={action.href}
                                label={t(`dash.admin.quick.${action.key}`)}
                                tone={['teal', 'blue', 'purple', 'orange'][i % 4]}
                            />
                        ))}
                    </div>
                </section>
            ) : null}

            <div className="mt-8 grid gap-6 lg:grid-cols-5">
                <section className="lg:col-span-3">
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        {t('dash.admin.activity')}
                    </h2>
                    <DataTable columns={activityColumns} rows={recentActivity} emptyMessage={t('dash.admin.noActivity')} />
                </section>

                <section className="lg:col-span-2">
                    <div className="rounded-xl bg-white p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-navy">{t('dash.admin.enrolmentTrend')}</h2>
                        <p className="mt-3 text-sm leading-relaxed text-slate-500">
                            {students || classes
                                ? t('dash.admin.enrolmentNote', { students, classes })
                                : t('dash.admin.enrolmentFallback')}
                        </p>
                        {stats.enrolment_series?.length ? (
                            <ul className="mt-4 space-y-2">
                                {stats.enrolment_series.map((point) => (
                                    <li key={point.label} className="flex items-center justify-between text-sm">
                                        <span className="text-slate-500">{point.label}</span>
                                        <span className="font-semibold text-navy">{point.value}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                </section>
            </div>
        </AppShell>
    );
}
