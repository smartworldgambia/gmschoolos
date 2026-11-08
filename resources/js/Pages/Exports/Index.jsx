import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Index({
    batches = [],
    storeUrl,
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t } = useLocale();
    const { post, processing } = useForm({ type: 'students' });

    useEffect(() => {
        const pending = batches.some((b) => b.status === 'pending' || b.status === 'processing');
        if (!pending) return undefined;
        const id = setInterval(() => router.reload({ only: ['batches'] }), 3000);
        return () => clearInterval(id);
    }, [batches]);

    const columns = [
        { key: 'type', label: t('exports.col.type') },
        { key: 'status', label: t('exports.col.status'), render: (r) => <StatusBadge status={r.status} /> },
        { key: 'total_rows', label: t('exports.col.rows') },
        { key: 'created_at', label: t('exports.col.requested') },
        { key: 'finished_at', label: t('exports.col.finished'), render: (r) => r.finished_at ?? '—' },
        {
            key: 'download',
            label: t('exports.col.download'),
            render: (r) =>
                r.download_url ? (
                    <a href={r.download_url} className="text-teal hover:underline">{t('exports.downloadCsv')}</a>
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <AppShell title={t('exports.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('exports.title')} />
            <PageHeader
                title={t('exports.heading')}
                subtitle={t('exports.subtitle')}
                actions={
                    <button type="button" className="btn-teal whitespace-nowrap" disabled={processing} onClick={() => post(storeUrl)}>
                        {t('exports.students')}
                    </button>
                }
            />
            <div className="rounded-xl bg-white p-4 shadow-sm">
                <DataTable columns={columns} rows={batches} emptyMessage={t('exports.empty')} />
            </div>
        </AppShell>
    );
}
