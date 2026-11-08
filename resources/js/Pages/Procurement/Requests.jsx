import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select, Textarea } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';

export default function Requests(props) {
    const { requests, filter = '', stock = [], can = {} } = props;
    const { t } = useLocale();
    const f = useForm({ title: '', justification: '', needed_by: '', items: [{ description: '', quantity: 1, estimated_unit_cost: '', inventory_item_id: '' }] });
    f.transform((d) => ({ ...d, needed_by: d.needed_by || null, items: d.items.map((i) => ({ ...i, quantity: Number(i.quantity), estimated_unit_cost: i.estimated_unit_cost === '' ? 0 : Number(i.estimated_unit_cost), inventory_item_id: i.inventory_item_id || null })) }));
    const setItem = (i, patch) => f.setData('items', f.data.items.map((row, idx) => (idx === i ? { ...row, ...patch } : row)));

    return (
        <ProcurementLayout title={t('proc.req.title')} subtitle={t('proc.req.subtitle')} current="requests" shell={props}>
            <div className="mb-4 flex flex-wrap gap-2">
                {['', 'draft', 'submitted', 'approved', 'rejected', 'cancelled', 'ordered'].map((s) => (
                    <button key={s || 'all'} type="button" className={filter === s ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('procurement.requests.index'), s ? { status: s } : {}, { preserveState: true, replace: true })}>
                        {s ? t(`proc.status.${s}`) : t('acad.common.all')}
                    </button>
                ))}
            </div>
            <DataTable
                columns={[
                    { key: 'number', label: t('proc.col.number'), render: (r) => <Link href={route('procurement.requests.show', r.id)} className="font-medium text-teal-700 hover:underline">{r.number}</Link> },
                    { key: 'title', label: t('proc.col.title') },
                    { key: 'requester', label: t('proc.col.requester') },
                    { key: 'estimated_total', label: t('proc.col.total'), render: (r) => money(r.estimated_total) },
                    { key: 'status', label: t('proc.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'proc.status', r.status)} /> },
                ]}
                rows={requests?.data ?? []}
                emptyMessage={t('proc.req.empty')}
            />
            <Pager links={requests?.links} />
            {can.request || can.manage ? (
                <Card title={t('proc.req.add')} className="mt-8 max-w-3xl">
                    <form onSubmit={(e) => { e.preventDefault(); f.post(route('procurement.requests.store'), { preserveScroll: true }); }} className="space-y-3">
                        <ErrorSummary errors={f.errors} />
                        <Field label={t('proc.col.title')}><Input value={f.data.title} onChange={(e) => f.setData('title', e.target.value)} required /></Field>
                        <Field label={t('proc.req.why')}><Textarea rows={2} value={f.data.justification} onChange={(e) => f.setData('justification', e.target.value)} /></Field>
                        {f.data.items.map((row, i) => (
                            <div key={i} className="grid gap-2 sm:grid-cols-4">
                                <Field label={t('proc.col.item')} className="sm:col-span-2"><Input value={row.description} onChange={(e) => setItem(i, { description: e.target.value })} required /></Field>
                                <Field label={t('proc.col.qty')}><Input type="number" min="1" value={row.quantity} onChange={(e) => setItem(i, { quantity: e.target.value })} required /></Field>
                                <Field label={t('proc.col.est')}><Input type="number" min="0" value={row.estimated_unit_cost} onChange={(e) => setItem(i, { estimated_unit_cost: e.target.value })} /></Field>
                            </div>
                        ))}
                        <button type="button" className="btn-outline" onClick={() => f.setData('items', [...f.data.items, { description: '', quantity: 1, estimated_unit_cost: '', inventory_item_id: '' }])}>{t('proc.addLine')}</button>
                        <button type="submit" className="btn-teal ms-2" disabled={f.processing}>{t('proc.req.save')}</button>
                    </form>
                </Card>
            ) : null}
        </ProcurementLayout>
    );
}
