import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select } from '@/Components/SchoolOS/Form';
import InventoryLayout from '@/Components/SchoolOS/InventoryLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Assets(props) {
    const { assets, q = '', filter = '', categories = [], locations = [], suppliers = [], conditions = [] } = props;
    const { t } = useLocale();
    const [search, setSearch] = useState(q);
    const f = useForm({ tag: '', name: '', inventory_category_id: '', inventory_location_id: '', supplier_id: '', serial_number: '', acquired_on: '', cost: '', condition: 'good', notes: '' });
    f.transform((d) => ({ ...d, inventory_category_id: d.inventory_category_id || null, inventory_location_id: d.inventory_location_id || null, supplier_id: d.supplier_id || null, cost: d.cost === '' ? 0 : Number(d.cost), acquired_on: d.acquired_on || null }));

    return (
        <InventoryLayout title={t('asset.title')} subtitle={t('asset.subtitle')} current="assets" shell={props}>
            <form
                onSubmit={(e) => { e.preventDefault(); router.get(route('inventory.assets.index'), { q: search || undefined, status: filter || undefined }, { preserveState: true, replace: true }); }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t('acad.common.search')}><Input value={search} onChange={(e) => setSearch(e.target.value)} /></Field>
                <button type="submit" className="btn-navy">{t('acad.common.search')}</button>
            </form>
            <DataTable
                columns={[
                    { key: 'tag', label: t('asset.col.tag') },
                    { key: 'name', label: t('inv.col.name'), render: (r) => <Link href={route('inventory.assets.show', r.id)} className="font-medium text-teal-700 hover:underline">{r.name}</Link> },
                    { key: 'location', label: t('inv.col.location') },
                    { key: 'holder', label: t('asset.col.holder') },
                    { key: 'status', label: t('inv.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'asset.status', r.status)} /> },
                ]}
                rows={assets?.data ?? []}
                emptyMessage={t('asset.empty')}
            />
            <Pager links={assets?.links} />
            <Card title={t('asset.add')} className="mt-8 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); f.post(route('inventory.assets.store'), { preserveScroll: true }); }} className="space-y-3">
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('asset.col.tag')}><Input value={f.data.tag} onChange={(e) => f.setData('tag', e.target.value)} required /></Field>
                        <Field label={t('inv.col.name')}><Input value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} required /></Field>
                        <Field label={t('inv.col.location')}>
                            <Select value={f.data.inventory_location_id} onChange={(e) => f.setData('inventory_location_id', e.target.value)}>
                                <option value="">{t('acad.common.none')}</option>
                                {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('asset.col.condition')}>
                            <Select value={f.data.condition} onChange={(e) => f.setData('condition', e.target.value)}>
                                {conditions.map((k) => <option key={k} value={k}>{t(`asset.condition.${k}`)}</option>)}
                            </Select>
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={f.processing}>{t('asset.save')}</button>
                </form>
            </Card>
        </InventoryLayout>
    );
}
