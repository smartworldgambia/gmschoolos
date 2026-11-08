import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Reconciliation({ report, canVerify = false, nav = [], user, school }) {
    const l = useBillingLabels();
    const { t } = l;
    const [running, setRunning] = useState(false);

    const totalColumns = [
        { key: 'method', label: t('platformPayments.col.method'), render: (r) => l.method(r.method) },
        {
            key: 'status',
            label: t('platformPayments.col.status'),
            render: (r) => <StatusBadge status={r.status} label={l.paymentStatus(r.status)} />,
        },
        { key: 'count', label: t('platformPayments.rec.col.count') },
        { key: 'total_label', label: t('platformPayments.rec.col.total') },
    ];

    const attentionColumns = [
        {
            key: 'reason',
            label: t('platformPayments.rec.col.reason'),
            render: (r) => t(`platformPayments.rec.reason.${r.reason}`),
        },
        { key: 'school', label: t('platformPayments.col.school') },
        { key: 'reference', label: t('platformPayments.col.reference'), render: (r) => <span dir="ltr">{r.reference}</span> },
        { key: 'method', label: t('platformPayments.col.method'), render: (r) => l.method(r.method) },
        { key: 'total_label', label: t('platformPayments.col.amount') },
        { key: 'since', label: t('platformPayments.rec.col.since'), render: (r) => l.dateTime(r.since) },
        {
            key: 'url',
            label: t('platformPayments.rec.col.open'),
            render: (r) => (
                <Link href={r.url} className="font-medium text-teal hover:underline">
                    {t('platformPayments.rec.open')}
                </Link>
            ),
        },
    ];

    function run() {
        router.post(route('platform.payments.reconcile'), {}, { preserveScroll: true, onStart: () => setRunning(true), onFinish: () => setRunning(false) });
    }

    return (
        <AppShell title={t('platformPayments.rec.title')} nav={nav} user={user} school={school}>
            <Head title={t('platformPayments.rec.title')} />

            <PageHeader
                title={t('platformPayments.rec.title')}
                subtitle={t('platformPayments.rec.subtitle')}
                actions={
                    <>
                        <Link href={route('platform.payments.index')} className="btn-outline whitespace-nowrap text-sm">
                            {t('platformPayments.show.back')}
                        </Link>
                        {canVerify ? (
                            <button type="button" onClick={run} disabled={running} className="btn-teal whitespace-nowrap text-sm">
                                {t('platformPayments.rec.run')}
                            </button>
                        ) : null}
                    </>
                }
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard title={t('platformPayments.rec.processed')} value={report.webhooks?.processed ?? 0} tone="teal" />
                <StatCard title={t('platformPayments.rec.ignored')} value={report.webhooks?.ignored ?? 0} tone="blue" />
                <StatCard title={t('platformPayments.rec.failed')} value={report.webhooks?.failed ?? 0} tone="orange" />
            </div>

            <section className="mt-8">
                <h2 className="console-section-title">{t('platformPayments.rec.attention')}</h2>
                <DataTable columns={attentionColumns} rows={(report.attention ?? []).map((r, i) => ({ ...r, key: `${r.reason}-${r.id}-${i}` }))} emptyMessage={t('platformPayments.rec.attentionEmpty')} />
            </section>

            <section className="mt-8">
                <h2 className="console-section-title">{t('platformPayments.rec.totals')}</h2>
                <DataTable
                    columns={totalColumns}
                    rows={(report.totals ?? []).map((r, i) => ({ ...r, key: `${r.method}-${r.status}-${r.currency}-${i}` }))}
                    emptyMessage={t('platformPayments.empty')}
                />
            </section>
        </AppShell>
    );
}
