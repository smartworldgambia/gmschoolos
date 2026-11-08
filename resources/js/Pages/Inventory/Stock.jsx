import DataTable from '@/Components/SchoolOS/DataTable';
import { Field, Input, Pager } from '@/Components/SchoolOS/Form';
import InventoryLayout from '@/Components/SchoolOS/InventoryLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function Stock(props) {
    const { movements, q = '' } = props;
    const { t } = useLocale();
    const [search, setSearch] = useState(q);

    return (
        <InventoryLayout title={t('inv.stock.title')} subtitle={t('inv.stock.subtitle')} current="stock" shell={props}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('inventory.stock.index'), search ? { q: search } : {}, { preserveState: true, replace: true });
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t('acad.common.search')}><Input value={search} onChange={(e) => setSearch(e.target.value)} /></Field>
                <button type="submit" className="btn-navy">{t('acad.common.search')}</button>
            </form>
            <DataTable
                columns={[
                    { key: 'at', label: t('acad.common.date') },
                    { key: 'item', label: t('inv.col.name') },
                    { key: 'sku', label: t('inv.col.sku') },
                    { key: 'type', label: t('inv.col.type'), render: (r) => enumLabel(t, 'inv.move', r.type) },
                    { key: 'quantity', label: t('inv.col.qty') },
                    { key: 'balance_after', label: t('inv.col.balance') },
                    { key: 'location', label: t('inv.col.location') },
                    { key: 'by', label: t('inv.col.by') },
                ]}
                rows={movements?.data ?? []}
                emptyMessage={t('inv.noMoves')}
            />
            <Pager links={movements?.links} />
        </InventoryLayout>
    );
}
