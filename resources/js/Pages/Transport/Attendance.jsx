import DataTable from '@/Components/SchoolOS/DataTable';
import { Field, Input, Select } from '@/Components/SchoolOS/Form';
import TransportLayout from '@/Components/SchoolOS/TransportLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { todayIso } from '@/lib/academic';
import { router, useForm } from '@inertiajs/react';

export default function Attendance(props) {
    const { date, routeId, routes = [], rows = [] } = props;
    const { t } = useLocale();
    const f = useForm({
        transport_route_id: routeId,
        travel_date: date,
        marks: rows.flatMap((r) => [
            { student_id: r.student_id, direction: 'pickup', status: r.pickup || 'boarded' },
            { student_id: r.student_id, direction: 'drop', status: r.drop || 'boarded' },
        ]),
    });
    const setMark = (studentId, direction, status) => f.setData('marks', f.data.marks.map((m) => (m.student_id === studentId && m.direction === direction ? { ...m, status } : m)));
    const val = (id, dir) => f.data.marks.find((m) => m.student_id === id && m.direction === dir)?.status || 'boarded';

    return (
        <TransportLayout title={t('tr.att.title')} subtitle={t('tr.att.subtitle')} current="attendance" shell={props}>
            <form className="mb-4 flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); router.get(route('transport.attendance.index'), { date: f.data.travel_date, route: f.data.transport_route_id }, { preserveState: true, replace: true }); }}>
                <Field label={t('tr.route')}>
                    <Select value={f.data.transport_route_id ?? ''} onChange={(e) => f.setData('transport_route_id', e.target.value)}>
                        {routes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </Select>
                </Field>
                <Field label={t('acad.common.date')}><Input type="date" max={todayIso()} value={f.data.travel_date} onChange={(e) => f.setData('travel_date', e.target.value)} /></Field>
                <button type="submit" className="btn-navy">{t('acad.common.search')}</button>
            </form>
            <form onSubmit={(e) => { e.preventDefault(); f.post(route('transport.attendance.save'), { preserveScroll: true }); }}>
                <DataTable
                    columns={[
                        { key: 'student', label: t('tr.student') },
                        { key: 'pickup', label: t('tr.att.pickup'), render: (r) => (
                            <Select value={val(r.student_id, 'pickup')} onChange={(e) => setMark(r.student_id, 'pickup', e.target.value)}>
                                {['boarded', 'absent', 'not_travelling'].map((k) => <option key={k} value={k}>{t(`tr.ride.${k}`)}</option>)}
                            </Select>
                        ) },
                        { key: 'drop', label: t('tr.att.drop'), render: (r) => (
                            <Select value={val(r.student_id, 'drop')} onChange={(e) => setMark(r.student_id, 'drop', e.target.value)}>
                                {['boarded', 'absent', 'not_travelling'].map((k) => <option key={k} value={k}>{t(`tr.ride.${k}`)}</option>)}
                            </Select>
                        ) },
                    ]}
                    rows={rows}
                    emptyMessage={t('tr.att.empty')}
                />
                {rows.length ? <button type="submit" className="btn-teal mt-4" disabled={f.processing}>{t('acad.common.save')}</button> : null}
            </form>
        </TransportLayout>
    );
}
