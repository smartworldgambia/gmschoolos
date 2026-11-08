import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import InventoryLayout from '@/Components/SchoolOS/InventoryLayout';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';

export default function AssetShow(props) {
    const { asset, assignments = [], maintenance = [], staff = [], locations = [] } = props;
    const { t } = useLocale();
    const final = asset.status === 'disposed' || asset.status === 'lost';
    const assign = useForm({ staff_id: '', inventory_location_id: asset.inventory_location_id ?? '', assigned_on: '', notes: '' });
    assign.transform((d) => ({ ...d, staff_id: d.staff_id || null, inventory_location_id: d.inventory_location_id || null, assigned_on: d.assigned_on || null }));
    const maint = useForm({ kind: 'repair', description: '', scheduled_on: '' });

    return (
        <InventoryLayout title={asset.name} subtitle={asset.tag} current="assets" shell={props} actions={<Link href={route('inventory.assets.index')} className="btn-outline">{t('acad.common.back')}</Link>}>
            <p className="mb-4"><StatusBadge status={asset.status} label={enumLabel(t, 'asset.status', asset.status)} /> {asset.location ? ` · ${asset.location}` : ''} {asset.holder ? ` · ${asset.holder}` : ''}</p>

            {!final ? (
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card title={t('asset.assign')}>
                        <form onSubmit={(e) => { e.preventDefault(); assign.post(route('inventory.assets.assign', asset.id), { preserveScroll: true }); }} className="space-y-3">
                            <ErrorSummary errors={assign.errors} />
                            <Field label={t('asset.col.holder')}>
                                <Select value={assign.data.staff_id} onChange={(e) => assign.setData('staff_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('inv.col.location')}>
                                <Select value={assign.data.inventory_location_id} onChange={(e) => assign.setData('inventory_location_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {locations.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                                </Select>
                            </Field>
                            <button type="submit" className="btn-teal" disabled={assign.processing}>{t('asset.assign')}</button>
                        </form>
                        {asset.status === 'in_use' ? (
                            <button type="button" className="btn-outline mt-3" onClick={() => router.post(route('inventory.assets.return', asset.id), {}, { preserveScroll: true })}>{t('asset.return')}</button>
                        ) : null}
                    </Card>
                    <Card title={t('asset.maint')}>
                        <form onSubmit={(e) => { e.preventDefault(); maint.post(route('inventory.assets.maintenance.store', asset.id), { preserveScroll: true, onSuccess: () => maint.reset() }); }} className="space-y-3">
                            <ErrorSummary errors={maint.errors} />
                            <Field label={t('inv.col.type')}>
                                <Select value={maint.data.kind} onChange={(e) => maint.setData('kind', e.target.value)}>
                                    {['preventive', 'repair', 'inspection'].map((k) => <option key={k} value={k}>{t(`asset.maintKind.${k}`)}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('asset.maint.desc')}><Input value={maint.data.description} onChange={(e) => maint.setData('description', e.target.value)} required /></Field>
                            <Field label={t('asset.maint.when')}><Input type="date" value={maint.data.scheduled_on} onChange={(e) => maint.setData('scheduled_on', e.target.value)} required /></Field>
                            <button type="submit" className="btn-teal" disabled={maint.processing}>{t('asset.maint.save')}</button>
                        </form>
                    </Card>
                </div>
            ) : null}

            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('asset.history')}</h2>
            <DataTable
                columns={[
                    { key: 'assigned_on', label: t('asset.col.from') },
                    { key: 'returned_on', label: t('asset.col.to') },
                    { key: 'staff', label: t('asset.col.holder') },
                    { key: 'location', label: t('inv.col.location') },
                ]}
                rows={assignments}
                emptyMessage={t('asset.noHistory')}
            />
            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('asset.maint')}</h2>
            <DataTable
                columns={[
                    { key: 'kind', label: t('inv.col.type'), render: (r) => enumLabel(t, 'asset.maintKind', r.kind) },
                    { key: 'description', label: t('asset.maint.desc'), cellClassName: 'max-w-xs whitespace-normal' },
                    { key: 'scheduled_on', label: t('asset.maint.when') },
                    { key: 'cost', label: t('asset.col.cost'), render: (r) => money(r.cost) },
                    { key: 'status', label: t('inv.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'asset.maintStatus', r.status)} /> },
                    {
                        key: 'actions',
                        label: '',
                        render: (r) => r.status === 'scheduled' ? (
                            <div className="flex flex-wrap gap-2 whitespace-normal">
                                <button type="button" className="btn-teal" onClick={() => router.post(route('inventory.maintenance.complete', r.id), { cost: 0 }, { preserveScroll: true })}>{t('asset.maint.done')}</button>
                                <button type="button" className="btn-outline" onClick={() => router.post(route('inventory.maintenance.cancel', r.id), {}, { preserveScroll: true })}>{t('acad.common.cancel')}</button>
                            </div>
                        ) : null,
                    },
                ]}
                rows={maintenance}
                emptyMessage={t('asset.noMaint')}
            />
            {!final ? (
                <Card title={t('asset.retire')} className="mt-8 max-w-xl">
                    <p className="mb-3 text-sm text-slate-600">{t('asset.retireNote')}</p>
                    <div className="flex flex-wrap gap-2">
                        <ReasonAction url={route('inventory.assets.retire', asset.id)} fieldName="reason" fields={{ kind: 'disposed' }} label={t('asset.dispose')} tone="danger" />
                        <ReasonAction url={route('inventory.assets.retire', asset.id)} fieldName="reason" fields={{ kind: 'lost' }} label={t('asset.lost')} tone="danger" />
                    </div>
                </Card>
            ) : null}
        </InventoryLayout>
    );
}
