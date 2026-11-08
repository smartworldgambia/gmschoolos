import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Card, Field, Input, Select } from '@/Components/SchoolOS/Form';
import StatCard from '@/Components/SchoolOS/StatCard';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const BUCKETS = ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'];

export default function Reports(props) {
    const { period, classId, classes = [], summary = {}, byMethod = [], byCategory = { fees: [], reductions: [] }, outstanding = { buckets: {}, classes: [], rows: [] }, canExport } = props;
    const { t, locale } = useLocale();
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [cls, setCls] = useState(classId ?? '');

    const go = (e) => {
        e.preventDefault();
        router.get(route('fees.reports.index'), { from, to, class: cls || undefined }, { preserveState: true, replace: true });
    };
    const exportUrl = (type) => route('fees.reports.export', { type, from: period.from, to: period.to, class: classId || undefined });

    return (
        <FinanceLayout
            title={t('fin.reports.title')}
            subtitle={t('fin.reports.subtitle')}
            current="reports"
            shell={props}
            actions={
                canExport ? (
                    <>
                        <a href={exportUrl('outstanding')} className="btn-outline">
                            {t('fin.reports.exportOutstanding')}
                        </a>
                        <a href={exportUrl('payments')} className="btn-outline">
                            {t('fin.reports.exportPayments')}
                        </a>
                    </>
                ) : null
            }
        >
            <form onSubmit={go} className="mb-6 flex flex-wrap items-end gap-3">
                <Field label={t('fin.reports.from')}>
                    <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                </Field>
                <Field label={t('fin.reports.to')}>
                    <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                </Field>
                <Field label={t('fin.col.class')} hint={t('fin.reports.classHint')}>
                    <Select value={cls} onChange={(e) => setCls(e.target.value)}>
                        <option value="">{t('acad.common.all')}</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <button type="submit" className="btn-navy">
                    {t('fin.reports.apply')}
                </button>
            </form>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title={t('fin.reports.billedGross')} value={money(summary.billed)} tone="navy" />
                <StatCard title={t('fin.reports.adjustments')} value={money(summary.adjustments)} tone="purple" />
                <StatCard title={t('fin.stat.collected')} value={money(summary.collected)} tone="teal" />
                <StatCard title={t('fin.stat.rate')} value={`${summary.collection_rate ?? 0}%`} tone="blue" />
                <StatCard title={t('fin.stat.outstanding')} value={money(summary.outstanding)} tone="orange" />
                <StatCard title={t('fin.stat.overdue')} value={money(summary.overdue)} tone="orange" />
                <StatCard title={t('fin.reports.pendingPayments')} value={summary.pending_payments ?? 0} tone="blue" />
            </div>
            <p className="mt-2 text-xs text-slate-500">{t('fin.reports.periodNote')}</p>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card title={t('fin.reports.byMethod')}>
                    <DataTable
                        columns={[
                            { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
                            { key: 'count', label: t('fin.reports.count') },
                            { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
                        ]}
                        rows={byMethod}
                        keyField="method"
                        emptyMessage={t('fin.reports.none')}
                    />
                </Card>
                <Card title={t('fin.reports.byCategory')}>
                    <DataTable
                        columns={[
                            { key: 'category', label: t('fin.cat.name'), render: (r) => r.category || t('fin.reports.uncategorised') },
                            { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
                        ]}
                        rows={byCategory.fees}
                        keyField="category"
                        emptyMessage={t('fin.reports.none')}
                    />
                    {byCategory.reductions.length > 0 ? (
                        <div className="mt-3">
                            <DataTable
                                columns={[
                                    { key: 'kind', label: t('fin.col.kind'), render: (r) => enumLabel(t, 'fin.kind', r.kind) },
                                    { key: 'amount', label: t('fin.col.amount'), render: (r) => `− ${money(r.amount)}` },
                                ]}
                                rows={byCategory.reductions}
                                keyField="kind"
                            />
                        </div>
                    ) : null}
                </Card>
            </div>

            <Card title={t('fin.reports.ageing')} className="mt-6">
                <div className="mb-4 grid gap-3 sm:grid-cols-5">
                    {BUCKETS.map((b) => (
                        <div key={b} className="rounded-lg bg-slate-50 p-3">
                            <p className="text-xs text-slate-500">{t(`fin.bucket.${b}`)}</p>
                            <p className="mt-1 text-lg font-semibold text-navy">{money(outstanding.buckets[b] ?? 0)}</p>
                        </div>
                    ))}
                </div>
                <h3 className="mb-2 text-sm font-semibold text-slate-600">{t('fin.reports.perClass')}</h3>
                <DataTable
                    columns={[
                        { key: 'class', label: t('fin.col.class'), render: (r) => r.class || t('fin.reports.noClass') },
                        { key: 'invoices', label: t('fin.reports.openInvoices') },
                        { key: 'balance', label: t('fin.col.balance'), render: (r) => money(r.balance) },
                    ]}
                    rows={outstanding.classes}
                    keyField="class_id"
                    emptyMessage={t('fin.reports.none')}
                />
                <h3 className="mb-2 mt-6 text-sm font-semibold text-slate-600">{t('fin.reports.debtors')}</h3>
                <DataTable
                    columns={[
                        {
                            key: 'invoice_number',
                            label: t('fin.col.invoice'),
                            render: (r) => (
                                <Link href={route('fees.invoices.show', r.invoice_id)} className="text-teal-700 hover:underline">
                                    {r.invoice_number}
                                </Link>
                            ),
                        },
                        { key: 'student', label: t('fin.col.student') },
                        { key: 'class', label: t('fin.col.class') },
                        { key: 'balance', label: t('fin.col.balance'), render: (r) => money(r.balance) },
                        { key: 'due_on', label: t('fin.col.due'), render: (r) => fmtDay(r.due_on, locale) || '—' },
                        { key: 'days_overdue', label: t('fin.reports.daysOverdue') },
                    ]}
                    rows={outstanding.rows}
                    keyField="invoice_id"
                    emptyMessage={t('fin.reports.none')}
                />
            </Card>
        </FinanceLayout>
    );
}
