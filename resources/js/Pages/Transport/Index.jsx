import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Select, Stat } from '@/Components/SchoolOS/Form';
import TransportLayout from '@/Components/SchoolOS/TransportLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { router, useForm } from '@inertiajs/react';

export default function Index(props) {
    const { vehicles = [], drivers = [], routes = [], assignments = [], students = [], terms = [], stats = {} } = props;
    const { t } = useLocale();
    const vehicle = useForm({ name: '', plate_number: '', capacity: 12, driver_name: '', driver_phone: '' });
    const driver = useForm({ name: '', phone: '', licence_number: '', licence_expires_on: '' });
    const routeF = useForm({ name: '', area: '', transport_vehicle_id: '', fee_per_term: '' });
    routeF.transform((d) => ({ ...d, transport_vehicle_id: d.transport_vehicle_id || null, fee_per_term: d.fee_per_term === '' ? null : Number(d.fee_per_term) }));
    const stop = useForm({ transport_route_id: routes[0]?.id ?? '', name: '', sequence: '' });
    const assign = useForm({ transport_route_id: routes[0]?.id ?? '', student_id: '', transport_stop_id: '', stop_name: '' });
    assign.transform((d) => ({ ...d, transport_stop_id: d.transport_stop_id || null }));
    const bill = useForm({ term_id: terms[0]?.id ?? '' });
    const chosen = routes.find((r) => String(r.id) === String(assign.data.transport_route_id));

    return (
        <TransportLayout title={t('tr.title')} subtitle={t('tr.subtitle')} current="overview" shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <Stat label={t('tr.stat.vehicles')} value={stats.vehicles} />
                <Stat label={t('tr.stat.routes')} value={stats.routes} />
                <Stat label={t('tr.stat.riders')} value={stats.riders} />
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <div>
                    <h2 className="mb-3 text-base font-semibold text-navy">{t('tr.vehicles')}</h2>
                    <DataTable columns={[{ key: 'name', label: t('inv.col.name') }, { key: 'plate_number', label: t('tr.plate') }, { key: 'capacity', label: t('tr.capacity') }, { key: 'driver', label: t('tr.driver') }]} rows={vehicles} emptyMessage={t('tr.noVehicles')} />
                    <Card title={t('tr.addVehicle')} className="mt-4">
                        <form onSubmit={(e) => { e.preventDefault(); vehicle.post(route('transport.vehicles.store'), { preserveScroll: true, onSuccess: () => vehicle.reset() }); }} className="space-y-3">
                            <ErrorSummary errors={vehicle.errors} />
                            <Field label={t('inv.col.name')}><Input value={vehicle.data.name} onChange={(e) => vehicle.setData('name', e.target.value)} required /></Field>
                            <Field label={t('tr.capacity')}><Input type="number" min="1" value={vehicle.data.capacity} onChange={(e) => vehicle.setData('capacity', e.target.value)} required /></Field>
                            <button type="submit" className="btn-teal" disabled={vehicle.processing}>{t('tr.saveVehicle')}</button>
                        </form>
                    </Card>
                </div>
                <div>
                    <h2 className="mb-3 text-base font-semibold text-navy">{t('tr.drivers')}</h2>
                    <DataTable columns={[{ key: 'name', label: t('inv.col.name') }, { key: 'phone', label: t('proc.sup.phone') }, { key: 'licence_number', label: t('tr.licence') }]} rows={drivers} emptyMessage={t('tr.noDrivers')} />
                    <Card title={t('tr.addDriver')} className="mt-4">
                        <form onSubmit={(e) => { e.preventDefault(); driver.post(route('transport.drivers.store'), { preserveScroll: true, onSuccess: () => driver.reset() }); }} className="space-y-3">
                            <ErrorSummary errors={driver.errors} />
                            <Field label={t('inv.col.name')}><Input value={driver.data.name} onChange={(e) => driver.setData('name', e.target.value)} required /></Field>
                            <button type="submit" className="btn-teal" disabled={driver.processing}>{t('tr.saveDriver')}</button>
                        </form>
                    </Card>
                </div>
            </div>
            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('tr.routes')}</h2>
            <DataTable columns={[{ key: 'name', label: t('inv.col.name') }, { key: 'vehicle', label: t('tr.vehicle') }, { key: 'riders', label: t('tr.riders'), render: (r) => `${r.riders} / ${r.capacity || '—'}` }, { key: 'fee_per_term', label: t('tr.fee'), render: (r) => money(r.fee_per_term) }]} rows={routes} emptyMessage={t('tr.noRoutes')} />
            <Card title={t('tr.addRoute')} className="mt-4 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); routeF.post(route('transport.routes.store'), { preserveScroll: true, onSuccess: () => routeF.reset() }); }} className="space-y-3">
                    <ErrorSummary errors={routeF.errors} />
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field label={t('inv.col.name')}><Input value={routeF.data.name} onChange={(e) => routeF.setData('name', e.target.value)} required /></Field>
                        <Field label={t('tr.vehicle')}>
                            <Select value={routeF.data.transport_vehicle_id} onChange={(e) => routeF.setData('transport_vehicle_id', e.target.value)}>
                                <option value="">{t('acad.common.none')}</option>
                                {vehicles.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('tr.fee')}><Input type="number" min="0" value={routeF.data.fee_per_term} onChange={(e) => routeF.setData('fee_per_term', e.target.value)} /></Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={routeF.processing}>{t('tr.saveRoute')}</button>
                </form>
            </Card>
            <Card title={t('tr.addStop')} className="mt-4 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); stop.post(route('transport.stops.store'), { preserveScroll: true, onSuccess: () => stop.reset('name') }); }} className="grid gap-3 sm:grid-cols-3">
                    <Field label={t('tr.route')}>
                        <Select value={stop.data.transport_route_id} onChange={(e) => stop.setData('transport_route_id', e.target.value)} required>
                            {routes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                        </Select>
                    </Field>
                    <Field label={t('inv.col.name')}><Input value={stop.data.name} onChange={(e) => stop.setData('name', e.target.value)} required /></Field>
                    <button type="submit" className="btn-teal self-end" disabled={stop.processing}>{t('tr.saveStop')}</button>
                </form>
            </Card>
            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('tr.assignments')}</h2>
            <DataTable
                columns={[
                    { key: 'student', label: t('tr.student') },
                    { key: 'route', label: t('tr.route') },
                    { key: 'stop', label: t('tr.stop') },
                    { key: 'actions', label: '', render: (r) => <button type="button" className="btn-outline" onClick={() => window.confirm(t('tr.endConfirm')) && router.post(route('transport.assignments.end', r.id))}>{t('tr.end')}</button> },
                ]}
                rows={assignments}
                emptyMessage={t('tr.noAssignments')}
            />
            <Card title={t('tr.assign')} className="mt-4 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); assign.post(route('transport.assignments.store'), { preserveScroll: true }); }} className="space-y-3">
                    <ErrorSummary errors={assign.errors} />
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field label={t('tr.student')}>
                            <Select value={assign.data.student_id} onChange={(e) => assign.setData('student_id', e.target.value)} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('tr.route')}>
                            <Select value={assign.data.transport_route_id} onChange={(e) => assign.setData((d) => ({ ...d, transport_route_id: e.target.value, transport_stop_id: '' }))}>
                                {routes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('tr.stop')}>
                            <Select value={assign.data.transport_stop_id} onChange={(e) => assign.setData('transport_stop_id', e.target.value)}>
                                <option value="">{t('acad.common.none')}</option>
                                {(chosen?.stops ?? []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </Select>
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={assign.processing}>{t('tr.assign')}</button>
                </form>
            </Card>
            <Card title={t('tr.bill')} className="mt-8 max-w-xl">
                <p className="mb-3 text-sm text-slate-600">{t('tr.billNote')}</p>
                <form onSubmit={(e) => { e.preventDefault(); bill.post(route('transport.bill'), { preserveScroll: true }); }} className="flex flex-wrap items-end gap-3">
                    <Field label={t('tr.term')}>
                        <Select value={bill.data.term_id} onChange={(e) => bill.setData('term_id', e.target.value)} required>
                            {terms.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
                        </Select>
                    </Field>
                    <button type="submit" className="btn-teal" disabled={bill.processing}>{t('tr.bill')}</button>
                </form>
            </Card>
        </TransportLayout>
    );
}
