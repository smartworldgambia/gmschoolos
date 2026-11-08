import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Card, ErrorSummary, Field, Input, Textarea } from '@/Components/SchoolOS/Form';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { useForm } from '@inertiajs/react';

export default function Reconciliation(props) {
    const { summary = {}, unreconciledPayments = [], byMethod = [], reconciliations = [], storeUrl } = props;
    const { t, locale } = useLocale();
    const f = useForm({ period_starts_on: '', period_ends_on: '', payment_ids: [], statement_amount: '', notes: '' });
    f.transform((d) => ({ ...d, statement_amount: d.statement_amount === '' ? null : Number(d.statement_amount) }));

    const picked = unreconciledPayments.filter((p) => f.data.payment_ids.includes(p.id));
    const pickedTotal = picked.reduce((sum, p) => sum + p.amount, 0);
    const diff = f.data.statement_amount === '' ? null : Math.abs(Number(f.data.statement_amount) - pickedTotal);

    const toggle = (id) => f.setData('payment_ids', f.data.payment_ids.includes(id) ? f.data.payment_ids.filter((x) => x !== id) : [...f.data.payment_ids, id]);
    const allSelected = unreconciledPayments.length > 0 && f.data.payment_ids.length === unreconciledPayments.length;

    const paymentColumns = [
        {
            key: 'select',
            label: (
                <input
                    type="checkbox"
                    aria-label={t('fin.recon.selectAll')}
                    checked={allSelected}
                    onChange={() => f.setData('payment_ids', allSelected ? [] : unreconciledPayments.map((p) => p.id))}
                />
            ),
            render: (r) => <input type="checkbox" checked={f.data.payment_ids.includes(r.id)} onChange={() => toggle(r.id)} aria-label={r.receipt_number} />,
        },
        { key: 'receipt_number', label: t('fin.col.receipt') },
        { key: 'student_name', label: t('fin.col.student') },
        { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
        { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
        { key: 'external_reference', label: t('fin.col.reference') },
        { key: 'paid_at', label: t('fin.col.date'), render: (r) => fmtDay(r.paid_at, locale) },
    ];

    const runColumns = [
        { key: 'reference', label: t('fin.col.reference') },
        { key: 'period', label: t('fin.recon.period'), render: (r) => `${fmtDay(r.period_starts_on, locale)} – ${fmtDay(r.period_ends_on, locale)}` },
        { key: 'collected_amount', label: t('fin.recon.recorded'), render: (r) => money(r.collected_amount) },
        { key: 'statement_amount', label: t('fin.recon.statement'), render: (r) => money(r.statement_amount) },
        { key: 'variance_amount', label: t('fin.recon.difference'), render: (r) => money(r.variance_amount) },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status === 'matched' ? 'ok' : 'warning'} label={enumLabel(t, 'fin.status', r.status)} /> },
    ];

    return (
        <FinanceLayout title={t('fin.recon.title')} subtitle={t('fin.recon.subtitle')} current="reconciliation" shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <StatCard title={t('fin.recon.unreconciled')} value={money(summary.unreconciled)} tone="orange" />
                <StatCard title={t('fin.recon.pending')} value={summary.pending ?? 0} tone="blue" />
                {byMethod.map((m) => (
                    <StatCard key={m.method} title={`${enumLabel(t, 'fin.method', m.method)} · ${m.count}`} value={money(m.amount)} tone="teal" />
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card title={t('fin.recon.unreconciledList')}>
                        <p className="mb-3 text-sm text-slate-600">{t('fin.recon.intro')}</p>
                        <DataTable columns={paymentColumns} rows={unreconciledPayments} emptyMessage={t('fin.recon.allDone')} />
                    </Card>
                    <Card title={t('fin.recon.runs')}>
                        <DataTable columns={runColumns} rows={reconciliations} emptyMessage={t('fin.recon.noRuns')} />
                    </Card>
                </div>

                <Card title={t('fin.recon.new')} className="h-fit">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(storeUrl, { preserveScroll: true, onSuccess: () => f.reset() });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={f.errors} />
                        <Field label={t('fin.recon.periodStart')}>
                            <Input type="date" value={f.data.period_starts_on} onChange={(e) => f.setData('period_starts_on', e.target.value)} required />
                        </Field>
                        <Field label={t('fin.recon.periodEnd')}>
                            <Input type="date" value={f.data.period_ends_on} onChange={(e) => f.setData('period_ends_on', e.target.value)} required />
                        </Field>
                        <Field label={t('fin.recon.statementAmount')} hint={t('fin.recon.statementHint')}>
                            <Input type="number" min="0" step="1" value={f.data.statement_amount} onChange={(e) => f.setData('statement_amount', e.target.value)} />
                        </Field>
                        <Field label={t('acad.common.notes')}>
                            <Textarea rows={2} maxLength={1000} value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
                        </Field>
                        <p className="text-sm text-slate-600">{t('fin.recon.selected', { n: picked.length, amount: money(pickedTotal) })}</p>
                        {diff !== null && diff > 0 ? <p className="rounded-lg bg-amber-50 p-2 text-sm text-amber-900">{t('fin.recon.differenceNote', { amount: money(diff) })}</p> : null}
                        <button type="submit" className="btn-teal w-full" disabled={f.processing || picked.length === 0}>
                            {t('fin.recon.submit')}
                        </button>
                    </form>
                </Card>
            </div>
        </FinanceLayout>
    );
}
