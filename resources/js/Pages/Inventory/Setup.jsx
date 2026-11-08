import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input } from '@/Components/SchoolOS/Form';
import InventoryLayout from '@/Components/SchoolOS/InventoryLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';

export default function Setup(props) {
    const { locations = [], categories = [] } = props;
    const { t } = useLocale();
    const loc = useForm({ name: '', description: '' });
    const cat = useForm({ name: '' });

    return (
        <InventoryLayout title={t('inv.setup.title')} subtitle={t('inv.setup.subtitle')} current="setup" shell={props}>
            <div className="grid gap-6 lg:grid-cols-2">
                <div>
                    <h2 className="mb-3 text-base font-semibold text-navy">{t('inv.setup.locations')}</h2>
                    <DataTable columns={[{ key: 'name', label: t('inv.col.name') }, { key: 'description', label: t('inv.setup.description') }]} rows={locations} emptyMessage={t('inv.setup.noLocations')} />
                    <Card title={t('inv.setup.addLocation')} className="mt-4">
                        <form onSubmit={(e) => { e.preventDefault(); loc.post(route('inventory.locations.store'), { preserveScroll: true, onSuccess: () => loc.reset() }); }} className="space-y-3">
                            <ErrorSummary errors={loc.errors} />
                            <Field label={t('inv.col.name')}><Input value={loc.data.name} onChange={(e) => loc.setData('name', e.target.value)} required /></Field>
                            <button type="submit" className="btn-teal" disabled={loc.processing}>{t('acad.common.save')}</button>
                        </form>
                    </Card>
                </div>
                <div>
                    <h2 className="mb-3 text-base font-semibold text-navy">{t('inv.setup.categories')}</h2>
                    <DataTable columns={[{ key: 'name', label: t('inv.col.name') }]} rows={categories} emptyMessage={t('inv.setup.noCategories')} />
                    <Card title={t('inv.setup.addCategory')} className="mt-4">
                        <form onSubmit={(e) => { e.preventDefault(); cat.post(route('inventory.categories.store'), { preserveScroll: true, onSuccess: () => cat.reset() }); }} className="space-y-3">
                            <ErrorSummary errors={cat.errors} />
                            <Field label={t('inv.col.name')}><Input value={cat.data.name} onChange={(e) => cat.setData('name', e.target.value)} required /></Field>
                            <button type="submit" className="btn-teal" disabled={cat.processing}>{t('acad.common.save')}</button>
                        </form>
                    </Card>
                </div>
            </div>
        </InventoryLayout>
    );
}
