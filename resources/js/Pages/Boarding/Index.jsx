import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Select, Stat } from '@/Components/SchoolOS/Form';
import BoardingLayout from '@/Components/SchoolOS/BoardingLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { router, useForm } from '@inertiajs/react';

export default function Index(props) {
    const { hostels = [], rooms = [], allocations = [], students = [], terms = [], stats = {} } = props;
    const { t } = useLocale();
    const hostel = useForm({ name: '', gender: 'mixed', warden_name: '', fee_per_term: '' });
    hostel.transform((d) => ({ ...d, fee_per_term: d.fee_per_term === '' ? null : Number(d.fee_per_term) }));
    const room = useForm({ boarding_hostel_id: hostels[0]?.id ?? '', name: '', capacity: 4 });
    const alloc = useForm({ boarding_room_id: rooms[0]?.id ?? '', student_id: '', starts_on: '', boarding_bed_id: '' });
    alloc.transform((d) => ({ ...d, boarding_bed_id: d.boarding_bed_id || null }));
    const bill = useForm({ term_id: terms[0]?.id ?? '' });
    const chosen = rooms.find((r) => String(r.id) === String(alloc.data.boarding_room_id));

    return (
        <BoardingLayout title={t('bd.title')} subtitle={t('bd.subtitle')} current="overview" shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <Stat label={t('bd.stat.hostels')} value={stats.hostels} />
                <Stat label={t('bd.stat.rooms')} value={stats.rooms} />
                <Stat label={t('bd.stat.boarders')} value={stats.boarders} />
            </div>
            <DataTable columns={[{ key: 'name', label: t('inv.col.name') }, { key: 'gender', label: t('bd.gender') }, { key: 'rooms', label: t('bd.rooms') }, { key: 'fee_per_term', label: t('bd.fee'), render: (r) => money(r.fee_per_term) }]} rows={hostels} emptyMessage={t('bd.noHostels')} />
            <Card title={t('bd.addHostel')} className="mt-4 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); hostel.post(route('boarding.hostels.store'), { preserveScroll: true, onSuccess: () => hostel.reset() }); }} className="grid gap-3 sm:grid-cols-3">
                    <ErrorSummary errors={hostel.errors} />
                    <Field label={t('inv.col.name')}><Input value={hostel.data.name} onChange={(e) => hostel.setData('name', e.target.value)} required /></Field>
                    <Field label={t('bd.gender')}>
                        <Select value={hostel.data.gender} onChange={(e) => hostel.setData('gender', e.target.value)}>
                            {['male', 'female', 'mixed'].map((k) => <option key={k} value={k}>{t(`bd.gender.${k}`)}</option>)}
                        </Select>
                    </Field>
                    <Field label={t('bd.fee')}><Input type="number" min="0" value={hostel.data.fee_per_term} onChange={(e) => hostel.setData('fee_per_term', e.target.value)} /></Field>
                    <button type="submit" className="btn-teal self-end" disabled={hostel.processing}>{t('bd.saveHostel')}</button>
                </form>
            </Card>
            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('bd.rooms')}</h2>
            <DataTable columns={[{ key: 'name', label: t('inv.col.name') }, { key: 'hostel', label: t('bd.hostel') }, { key: 'occupied', label: t('bd.occupied'), render: (r) => `${r.occupied} / ${r.capacity}` }]} rows={rooms} emptyMessage={t('bd.noRooms')} />
            <Card title={t('bd.addRoom')} className="mt-4 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); room.post(route('boarding.rooms.store'), { preserveScroll: true, onSuccess: () => room.reset('name') }); }} className="grid gap-3 sm:grid-cols-3">
                    <Field label={t('bd.hostel')}>
                        <Select value={room.data.boarding_hostel_id} onChange={(e) => room.setData('boarding_hostel_id', e.target.value)} required>
                            {hostels.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
                        </Select>
                    </Field>
                    <Field label={t('inv.col.name')}><Input value={room.data.name} onChange={(e) => room.setData('name', e.target.value)} required /></Field>
                    <Field label={t('bd.beds')} hint={t('bd.bedsHint')}><Input type="number" min="1" value={room.data.capacity} onChange={(e) => room.setData('capacity', e.target.value)} required /></Field>
                    <button type="submit" className="btn-teal self-end" disabled={room.processing}>{t('bd.saveRoom')}</button>
                </form>
            </Card>
            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('bd.allocations')}</h2>
            <DataTable
                columns={[
                    { key: 'student', label: t('tr.student') },
                    { key: 'room', label: t('bd.room') },
                    { key: 'bed', label: t('bd.bed') },
                    { key: 'actions', label: '', render: (r) => <button type="button" className="btn-outline" onClick={() => window.confirm(t('bd.checkoutConfirm')) && router.post(route('boarding.allocations.checkout', r.id))}>{t('bd.checkout')}</button> },
                ]}
                rows={allocations}
                emptyMessage={t('bd.noAllocations')}
            />
            <Card title={t('bd.allocate')} className="mt-4 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); alloc.post(route('boarding.allocations.store'), { preserveScroll: true }); }} className="space-y-3">
                    <ErrorSummary errors={alloc.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('tr.student')}>
                            <Select value={alloc.data.student_id} onChange={(e) => alloc.setData('student_id', e.target.value)} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('bd.room')}>
                            <Select value={alloc.data.boarding_room_id} onChange={(e) => alloc.setData((d) => ({ ...d, boarding_room_id: e.target.value, boarding_bed_id: '' }))}>
                                {rooms.map((r) => <option key={r.id} value={r.id}>{r.hostel} / {r.name}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('bd.bed')}>
                            <Select value={alloc.data.boarding_bed_id} onChange={(e) => alloc.setData('boarding_bed_id', e.target.value)}>
                                <option value="">{t('bd.anyBed')}</option>
                                {(chosen?.beds ?? []).map((b) => <option key={b.id} value={b.id}>{b.label}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('bd.starts')}><Input type="date" value={alloc.data.starts_on} onChange={(e) => alloc.setData('starts_on', e.target.value)} required /></Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={alloc.processing}>{t('bd.allocate')}</button>
                </form>
            </Card>
            <Card title={t('bd.bill')} className="mt-8 max-w-xl">
                <p className="mb-3 text-sm text-slate-600">{t('bd.billNote')}</p>
                <form onSubmit={(e) => { e.preventDefault(); bill.post(route('boarding.bill'), { preserveScroll: true }); }} className="flex flex-wrap items-end gap-3">
                    <Field label={t('tr.term')}>
                        <Select value={bill.data.term_id} onChange={(e) => bill.setData('term_id', e.target.value)} required>
                            {terms.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
                        </Select>
                    </Field>
                    <button type="submit" className="btn-teal" disabled={bill.processing}>{t('bd.bill')}</button>
                </form>
            </Card>
        </BoardingLayout>
    );
}
