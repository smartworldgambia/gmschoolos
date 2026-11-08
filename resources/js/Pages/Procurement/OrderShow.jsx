import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, todayIso } from '@/lib/academic';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';

export default function OrderShow(props) {
    const { order, locations = [], can = {} } = props;
    const { t } = useLocale();
    const rec = useForm({
        received_on: todayIso(),
        inventory_location_id: locations[0]?.id ?? '',
        note: '',
        lines: order.items.map((i) => ({ item_id: i.id, quantity: Math.max(0, i.quantity - i.received_quantity) })),
    });
    rec.transform((d) => ({ ...d, inventory_location_id: d.inventory_location_id || null, lines: d.lines.map((l) => ({ item_id: l.item_id, quantity: Number(l.quantity) })) }));
    const setQty = (id, q) => rec.setData('lines', rec.data.lines.map((l) => (l.item_id === id ? { ...l, quantity: q } : l)));
    const receivable = ['issued', 'partially_received'].includes(order.status);

    return (
        <ProcurementLayout title={order.number} subtitle={order.supplier} current="orders" shell={props} actions={<Link href={route('procurement.orders.index')} className="btn-outline">{t('acad.common.back')}</Link>}>
            <p className="mb-4"><StatusBadge status={order.status} label={enumLabel(t, 'proc.status', order.status)} /> · {money(order.total_amount)} · {t('proc.receivedValue')}: {money(order.received_value)}</p>
            <DataTable
                columns={[
                    { key: 'description', label: t('proc.col.item') },
                    { key: 'quantity', label: t('proc.col.qty') },
                    { key: 'received_quantity', label: t('proc.col.received') },
                    { key: 'unit_cost', label: t('proc.col.unitCost'), render: (r) => money(r.unit_cost) },
                ]}
                rows={order.items}
                emptyMessage={t('proc.noLines')}
            />
            <div className="mt-4 flex flex-wrap gap-2">
                {order.status === 'draft' && can.manage ? (
                    <>
                        <button type="button" className="btn-teal" onClick={() => router.post(route('procurement.orders.issue', order.id))}>{t('proc.issue')}</button>
                        <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('proc.cancelConfirm')) && router.post(route('procurement.orders.cancel', order.id))}>{t('acad.common.cancel')}</button>
                    </>
                ) : null}
            </div>
            {receivable && can.manage ? (
                <Card title={t('proc.receive')} className="mt-8 max-w-3xl">
                    <form onSubmit={(e) => { e.preventDefault(); rec.post(route('procurement.orders.receive', order.id), { preserveScroll: true }); }} className="space-y-3">
                        <ErrorSummary errors={rec.errors} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('proc.receivedOn')}><Input type="date" max={todayIso()} value={rec.data.received_on} onChange={(e) => rec.setData('received_on', e.target.value)} required /></Field>
                            <Field label={t('inv.col.location')} hint={t('proc.locationHint')}>
                                <Select value={rec.data.inventory_location_id} onChange={(e) => rec.setData('inventory_location_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                                </Select>
                            </Field>
                        </div>
                        {order.items.map((i) => (
                            <Field key={i.id} label={`${i.description} (${t('proc.outstanding')}: ${i.quantity - i.received_quantity})`}>
                                <Input type="number" min="0" max={i.quantity - i.received_quantity} value={rec.data.lines.find((l) => l.item_id === i.id)?.quantity ?? 0} onChange={(e) => setQty(i.id, e.target.value)} />
                            </Field>
                        ))}
                        <button type="submit" className="btn-teal" disabled={rec.processing}>{t('proc.receive')}</button>
                    </form>
                </Card>
            ) : null}
        </ProcurementLayout>
    );
}
