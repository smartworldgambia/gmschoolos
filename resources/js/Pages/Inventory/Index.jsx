import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select, Stat } from '@/Components/SchoolOS/Form';
import InventoryLayout from '@/Components/SchoolOS/InventoryLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Index(props) {
    const { items, q = '', categories = [], low = 0 } = props;
    const { t } = useLocale();
    const [search, setSearch] = useState(q);
    const f = useForm({ name: '', sku: '', unit: 'pcs', reorder_level: 0, inventory_category_id: '', notes: '' });
    f.transform((d) => ({ ...d, inventory_category_id: d.inventory_category_id || null, reorder_level: Number(d.reorder_level) }));

    return (
        <InventoryLayout title={t('inv.title')} subtitle={t('inv.subtitle')} current="items" shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-2">
                <Stat label={t('inv.stat.items')} value={items?.total ?? 0} />
                <Stat label={t('inv.stat.low')} value={low} tone={low > 0 ? 'red' : 'navy'} />
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('inventory.items.index'), search ? { q: search } : {}, { preserveState: true, replace: true });
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t('acad.common.search')}>
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('inv.searchPlaceholder')} />
                </Field>
                <button type="submit" className="btn-navy">
                    {t('acad.common.search')}
                </button>
            </form>
            <DataTable
                columns={[
                    { key: 'sku', label: t('inv.col.sku') },
                    { key: 'name', label: t('inv.col.name'), render: (r) => <Link href={route('inventory.items.show', r.id)} className="font-medium text-teal-700 hover:underline">{r.name}</Link> },
                    { key: 'category', label: t('inv.col.category') },
                    { key: 'quantity', label: t('inv.col.qty') },
                    { key: 'reorder_level', label: t('inv.col.reorder') },
                    { key: 'status', label: t('inv.col.status'), render: (r) => <StatusBadge status={r.is_active ? (r.low ? 'overdue' : 'active') : 'inactive'} label={r.low ? t('inv.status.low') : t(r.is_active ? 'inv.status.active' : 'inv.status.inactive')} /> },
                ]}
                rows={items?.data ?? []}
                emptyMessage={t('inv.empty')}
            />
            <Pager links={items?.links} />
            <Card title={t('inv.addItem')} className="mt-8 max-w-3xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(route('inventory.items.store'), { preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('inv.col.name')}><Input value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} required /></Field>
                        <Field label={t('inv.col.sku')} hint={t('inv.skuHint')}><Input value={f.data.sku} onChange={(e) => f.setData('sku', e.target.value)} required /></Field>
                        <Field label={t('inv.col.unit')}><Input value={f.data.unit} onChange={(e) => f.setData('unit', e.target.value)} required /></Field>
                        <Field label={t('inv.col.reorder')}><Input type="number" min="0" value={f.data.reorder_level} onChange={(e) => f.setData('reorder_level', e.target.value)} required /></Field>
                        <Field label={t('inv.col.category')}>
                            <Select value={f.data.inventory_category_id} onChange={(e) => f.setData('inventory_category_id', e.target.value)}>
                                <option value="">{t('acad.common.none')}</option>
                                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </Select>
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={f.processing}>{t('inv.saveItem')}</button>
                </form>
            </Card>
        </InventoryLayout>
    );
}
