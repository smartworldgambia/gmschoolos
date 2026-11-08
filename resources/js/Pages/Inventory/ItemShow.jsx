import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Select, Stat, Textarea } from '@/Components/SchoolOS/Form';
import InventoryLayout from '@/Components/SchoolOS/InventoryLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, useForm } from '@inertiajs/react';

export default function ItemShow(props) {
    const { item, stock = [], movements = [], categories = [], locations = [], moveTypes = [] } = props;
    const { t } = useLocale();
    const edit = useForm({ name: item.name, sku: item.sku, unit: item.unit, reorder_level: item.reorder_level, inventory_category_id: item.inventory_category_id ?? '', notes: item.notes ?? '', is_active: item.is_active });
    edit.transform((d) => ({ ...d, inventory_category_id: d.inventory_category_id || null, reorder_level: Number(d.reorder_level) }));
    const mv = useForm({ type: 'receipt', inventory_location_id: locations[0]?.id ?? '', to_location_id: '', quantity: 1, reason: '', reference: '' });
    mv.transform((d) => ({ ...d, quantity: Number(d.quantity), to_location_id: d.to_location_id || null, inventory_location_id: Number(d.inventory_location_id) }));

    return (
        <InventoryLayout title={item.name} subtitle={item.sku} current="items" shell={props} actions={<Link href={route('inventory.items.index')} className="btn-outline">{t('acad.common.back')}</Link>}>
            <div className="mb-6 grid gap-4 sm:grid-cols-2">
                <Stat label={t('inv.col.qty')} value={item.total} />
                <Stat label={t('inv.col.reorder')} value={item.reorder_level} />
            </div>
            <DataTable
                columns={[
                    { key: 'location', label: t('inv.col.location') },
                    { key: 'quantity', label: t('inv.col.qty') },
                ]}
                rows={stock}
                emptyMessage={t('inv.noStock')}
            />
            <Card title={t('inv.move')} className="mt-6 max-w-3xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        mv.post(route('inventory.stock.move', item.id), { preserveScroll: true, onSuccess: () => mv.reset('quantity', 'reason', 'reference') });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={mv.errors} />
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field label={t('inv.col.type')}>
                            <Select value={mv.data.type} onChange={(e) => mv.setData('type', e.target.value)}>
                                {moveTypes.map((k) => <option key={k} value={k}>{t(`inv.move.${k}`)}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('inv.col.location')}>
                            <Select value={mv.data.inventory_location_id} onChange={(e) => mv.setData('inventory_location_id', e.target.value)} required>
                                {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                            </Select>
                        </Field>
                        {mv.data.type === 'transfer' ? (
                            <Field label={t('inv.move.to')}>
                                <Select value={mv.data.to_location_id} onChange={(e) => mv.setData('to_location_id', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                                </Select>
                            </Field>
                        ) : null}
                        <Field label={t('inv.col.qty')} hint={mv.data.type === 'adjustment' ? t('inv.adjustHint') : undefined}>
                            <Input type="number" value={mv.data.quantity} onChange={(e) => mv.setData('quantity', e.target.value)} required />
                        </Field>
                    </div>
                    <Field label={t('acad.common.reason')}><Textarea rows={2} maxLength={300} value={mv.data.reason} onChange={(e) => mv.setData('reason', e.target.value)} /></Field>
                    <button type="submit" className="btn-teal" disabled={mv.processing}>{t('inv.recordMove')}</button>
                </form>
            </Card>
            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('inv.ledger')}</h2>
            <DataTable
                columns={[
                    { key: 'at', label: t('acad.common.date') },
                    { key: 'type', label: t('inv.col.type'), render: (r) => enumLabel(t, 'inv.move', r.type) },
                    { key: 'quantity', label: t('inv.col.qty') },
                    { key: 'balance_after', label: t('inv.col.balance') },
                    { key: 'location', label: t('inv.col.location') },
                    { key: 'by', label: t('inv.col.by') },
                ]}
                rows={movements}
                emptyMessage={t('inv.noMoves')}
            />
            <Card title={t('inv.editItem')} className="mt-8 max-w-3xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        edit.put(route('inventory.items.update', item.id), { preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={edit.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('inv.col.name')}><Input value={edit.data.name} onChange={(e) => edit.setData('name', e.target.value)} required /></Field>
                        <Field label={t('inv.col.sku')}><Input value={edit.data.sku} onChange={(e) => edit.setData('sku', e.target.value)} required /></Field>
                        <Field label={t('inv.col.unit')}><Input value={edit.data.unit} onChange={(e) => edit.setData('unit', e.target.value)} required /></Field>
                        <Field label={t('inv.col.reorder')}><Input type="number" min="0" value={edit.data.reorder_level} onChange={(e) => edit.setData('reorder_level', e.target.value)} required /></Field>
                        <Field label={t('inv.col.status')}>
                            <Select value={edit.data.is_active ? '1' : '0'} onChange={(e) => edit.setData('is_active', e.target.value === '1')}>
                                <option value="1">{t('inv.status.active')}</option>
                                <option value="0">{t('inv.status.inactive')}</option>
                            </Select>
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={edit.processing}>{t('acad.common.save')}</button>
                </form>
            </Card>
        </InventoryLayout>
    );
}
