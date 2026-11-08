import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';

export default function Index({ facts = [], today = {}, refreshUrl, nav, user, school, academicYear }) {
    const { t } = useLocale();

    return (
        <AppShell title={t('reports.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('reports.headTitle')} />
            <PageHeader
                title={t('reports.heading')}
                subtitle={t('reports.subtitle')}
                actions={
                    <button type="button" className="btn-teal whitespace-nowrap" onClick={() => router.post(refreshUrl)}>
                        {t('reports.refresh')}
                    </button>
                }
            />
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <StatCard title={t('reports.stat.students')} value={today.students_active ?? '—'} tone="navy" />
                <StatCard title={t('reports.stat.fees')} value={today.fees_collected ?? '—'} tone="teal" />
                <StatCard title={t('reports.stat.attendance')} value={today.attendance_rate ?? '—'} tone="blue" />
            </div>
            <DataTable
                columns={[
                    { key: 'fact_key', label: t('reports.col.fact') },
                    { key: 'captured_on', label: t('reports.col.date') },
                    { key: 'value_numeric', label: t('reports.col.value') },
                    { key: 'refreshed_at', label: t('reports.col.refreshed') },
                ]}
                rows={facts}
                emptyMessage={t('reports.empty')}
            />
        </AppShell>
    );
}
