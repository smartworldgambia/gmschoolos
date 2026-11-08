import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, todayIso } from '@/lib/academic';
import { money } from '@/lib/fees';
import { router, useForm } from '@inertiajs/react';

export default function Invoices(props) {
    const { invoices, filter = '', suppliers = [], orders = [], can = {} } = props;
    const { t } = useLocale();
    const f = useForm({ supplier_id: '', purchase_order_id: '', invoice_number: '', invoice_date: todayIso(), due_on: '', amount: '' });
    f.transform((d) => ({ ...d, purchase_order_id: d.purchase_order_id || null, due_on: d.due_on || null, amount: Number(d.amount) }));
    const pay = useForm({ paid_on: todayIso(), payment_reference: '' });

    return (
        <ProcurementLayout title={t('proc.inv.title')} subtitle={t('proc.inv.subtitle')} current="invoices" shell={props} actions={<a href={route('procurement.export')} className="btn-outline">{t('proc.export')}</a>}>
            <div className="mb-4 flex flex-wrap gap-2">
                {['', 'recorded', 'approved', 'paid', 'cancelled'].map((s) => (
                    <button key={s || 'all'} type="button" className={filter === s ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('procurement.invoices.index'), s ? { status: s } : {}, { preserveState: true, replace: true })}>
                        {s ? t(`proc.status.${s}`) : t('acad.common.all')}
                    </button>
                ))}
            </div>
            <DataTable
                columns={[
                    { key: 'invoice_number', label: t('proc.col.number') },
                    { key: 'supplier', label: t('proc.col.supplier') },
                    { key: 'order', label: t('proc.col.order') },
                    { key: 'amount', label: t('proc.col.total'), render: (r) => money(r.amount) },
                    { key: 'status', label: t('proc.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'proc.status', r.status)} /> },
                    {
                        key: 'actions',
                        label: '',
                        cellClassName: 'whitespace-normal',
                        render: (r) => (
                            <div className="flex flex-wrap gap-2">
                                {r.status === 'recorded' && can.approve ? <button type="button" className="btn-teal" onClick={() => router.post(route('procurement.invoices.approve', r.id), {}, { preserveScroll: true })}>{t('acad.common.approve')}</button> : null}
                                {r.status === 'approved' && can.manage ? (
                                    <form onSubmit={(e) => { e.preventDefault(); pay.transform(() => ({ paid_on: pay.data.paid_on, payment_reference: pay.data.payment_reference })); pay.post(route('procurement.invoices.pay', r.id), { preserveScroll: true }); }} className="flex flex-wrap items-end gap-2">
                                        <Input placeholder={t('proc.payRef')} value={pay.data.payment_reference} onChange={(e) => pay.setData('payment_reference', e.target.value)} required />
                                        <button type="submit" className="btn-teal">{t('proc.markPaid')}</button>
                                    </form>
                                ) : null}
                                {['recorded', 'approved'].includes(r.status) && can.manage ? <button type="button" className="btn-outline !text-red-700" onClick={() => router.post(route('procurement.invoices.cancel', r.id), {}, { preserveScroll: true })}>{t('acad.common.cancel')}</button> : null}
                            </div>
                        ),
                    },
                ]}
                rows={invoices?.data ?? []}
                emptyMessage={t('proc.inv.empty')}
            />
            <Pager links={invoices?.links} />
            {can.manage ? (
                <Card title={t('proc.inv.add')} className="mt-8 max-w-3xl">
                    <p className="mb-3 text-sm text-slate-600">{t('proc.inv.note')}</p>
                    <form onSubmit={(e) => { e.preventDefault(); f.post(route('procurement.invoices.store'), { preserveScroll: true, onSuccess: () => f.reset() }); }} className="space-y-3">
                        <ErrorSummary errors={f.errors} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('proc.col.supplier')}>
                                <Select value={f.data.supplier_id} onChange={(e) => f.setData('supplier_id', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('proc.col.order')}>
                                <Select value={f.data.purchase_order_id} onChange={(e) => f.setData('purchase_order_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {orders.filter((o) => !f.data.supplier_id || String(o.supplier_id) === String(f.data.supplier_id)).map((o) => <option key={o.id} value={o.id}>{o.number}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('proc.col.number')}><Input value={f.data.invoice_number} onChange={(e) => f.setData('invoice_number', e.target.value)} required /></Field>
                            <Field label={t('proc.col.total')}><Input type="number" min="1" value={f.data.amount} onChange={(e) => f.setData('amount', e.target.value)} required /></Field>
                            <Field label={t('acad.common.date')}><Input type="date" value={f.data.invoice_date} onChange={(e) => f.setData('invoice_date', e.target.value)} required /></Field>
                        </div>
                        <button type="submit" className="btn-teal" disabled={f.processing}>{t('proc.inv.save')}</button>
                    </form>
                </Card>
            ) : null}
        </ProcurementLayout>
    );
}
