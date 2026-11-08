import DataTable from '@/Components/SchoolOS/DataTable';
import { Field, Input, Select } from '@/Components/SchoolOS/Form';
import BoardingLayout from '@/Components/SchoolOS/BoardingLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { todayIso } from '@/lib/academic';
import { router, useForm } from '@inertiajs/react';

export default function Attendance(props) {
    const { date, session, rows = [] } = props;
    const { t } = useLocale();
    const f = useForm({
        attended_on: date,
        session,
        marks: rows.map((r) => ({ student_id: r.student_id, status: r.status || 'present', remarks: r.remarks || '' })),
    });
    const set = (id, patch) => f.setData('marks', f.data.marks.map((m) => (m.student_id === id ? { ...m, ...patch } : m)));
    const row = (id) => f.data.marks.find((m) => m.student_id === id) || { status: 'present', remarks: '' };

    return (
        <BoardingLayout title={t('bd.att.title')} subtitle={t('bd.att.subtitle')} current="attendance" shell={props}>
            <form className="mb-4 flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); router.get(route('boarding.attendance.index'), { date: f.data.attended_on, session: f.data.session }, { preserveState: true, replace: true }); }}>
                <Field label={t('acad.common.date')}><Input type="date" max={todayIso()} value={f.data.attended_on} onChange={(e) => f.setData('attended_on', e.target.value)} /></Field>
                <Field label={t('bd.att.session')}>
                    <Select value={f.data.session} onChange={(e) => f.setData('session', e.target.value)}>
                        <option value="morning">{t('bd.att.morning')}</option>
                        <option value="evening">{t('bd.att.evening')}</option>
                    </Select>
                </Field>
                <button type="submit" className="btn-navy">{t('acad.common.search')}</button>
            </form>
            <form onSubmit={(e) => { e.preventDefault(); f.post(route('boarding.attendance.save'), { preserveScroll: true }); }}>
                <DataTable
                    columns={[
                        { key: 'student', label: t('tr.student') },
                        { key: 'status', label: t('inv.col.status'), render: (r) => (
                            <Select value={row(r.student_id).status} onChange={(e) => set(r.student_id, { status: e.target.value })}>
                                {['present', 'absent', 'leave', 'sick'].map((k) => <option key={k} value={k}>{t(`bd.mark.${k}`)}</option>)}
                            </Select>
                        ) },
                    ]}
                    rows={rows}
                    emptyMessage={t('bd.att.empty')}
                />
                {rows.length ? <button type="submit" className="btn-teal mt-4" disabled={f.processing}>{t('acad.common.save')}</button> : null}
            </form>
        </BoardingLayout>
    );
}
