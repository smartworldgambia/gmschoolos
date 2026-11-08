import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';

export default function Orders(props) {
    const { orders, filter = '', suppliers = [], stock = [], approvedRequests = [], can = {} } = props;
    const { t } = useLocale();
    const f = useForm({ supplier_id: '', purchase_request_id: '', expected_on: '', notes: '', items: [{ description: '', quantity: 1, unit_cost: '', inventory_item_id: '' }] });
    f.transform((d) => ({ ...d, purchase_request_id: d.purchase_request_id || null, expected_on: d.expected_on || null, items: d.items.map((i) => ({ ...i, quantity: Number(i.quantity), unit_cost: i.unit_cost === '' ? 0 : Number(i.unit_cost), inventory_item_id: i.inventory_item_id || null })) }));
    const setItem = (i, patch) => f.setData('items', f.data.items.map((row, idx) => (idx === i ? { ...row, ...patch } : row)));

    return (
        <ProcurementLayout title={t('proc.order.title')} subtitle={t('proc.order.subtitle')} current="orders" shell={props}>
            <div className="mb-4 flex flex-wrap gap-2">
                {['', 'draft', 'issued', 'partially_received', 'received', 'cancelled'].map((s) => (
                    <button key={s || 'all'} type="button" className={filter === s ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('procurement.orders.index'), s ? { status: s } : {}, { preserveState: true, replace: true })}>
                        {s ? t(`proc.status.${s}`) : t('acad.common.all')}
                    </button>
                ))}
            </div>
            <DataTable
                columns={[
                    { key: 'number', label: t('proc.col.number'), render: (r) => <Link href={route('procurement.orders.show', r.id)} className="font-medium text-teal-700 hover:underline">{r.number}</Link> },
                    { key: 'supplier', label: t('proc.col.supplier') },
                    { key: 'total_amount', label: t('proc.col.total'), render: (r) => money(r.total_amount) },
                    { key: 'status', label: t('proc.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'proc.status', r.status)} /> },
                ]}
                rows={orders?.data ?? []}
                emptyMessage={t('proc.order.empty')}
            />
            <Pager links={orders?.links} />
            {can.manage ? (
                <Card title={t('proc.order.add')} className="mt-8 max-w-3xl">
                    <form onSubmit={(e) => { e.preventDefault(); f.post(route('procurement.orders.store')); }} className="space-y-3">
                        <ErrorSummary errors={f.errors} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('proc.col.supplier')}>
                                <Select value={f.data.supplier_id} onChange={(e) => f.setData('supplier_id', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('proc.col.request')} hint={t('proc.order.requestHint')}>
                                <Select value={f.data.purchase_request_id} onChange={(e) => f.setData('purchase_request_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {approvedRequests.map((r) => <option key={r.id} value={r.id}>{r.number} — {r.title}</option>)}
                                </Select>
                            </Field>
                        </div>
                        {f.data.items.map((row, i) => (
                            <div key={i} className="grid gap-2 sm:grid-cols-4">
                                <Field label={t('proc.col.item')} className="sm:col-span-2"><Input value={row.description} onChange={(e) => setItem(i, { description: e.target.value })} required /></Field>
                                <Field label={t('proc.col.qty')}><Input type="number" min="1" value={row.quantity} onChange={(e) => setItem(i, { quantity: e.target.value })} required /></Field>
                                <Field label={t('proc.col.unitCost')}><Input type="number" min="0" value={row.unit_cost} onChange={(e) => setItem(i, { unit_cost: e.target.value })} /></Field>
                            </div>
                        ))}
                        <button type="button" className="btn-outline" onClick={() => f.setData('items', [...f.data.items, { description: '', quantity: 1, unit_cost: '', inventory_item_id: '' }])}>{t('proc.addLine')}</button>
                        <button type="submit" className="btn-teal ms-2" disabled={f.processing}>{t('proc.order.create')}</button>
                    </form>
                </Card>
            ) : null}
        </ProcurementLayout>
    );
}
