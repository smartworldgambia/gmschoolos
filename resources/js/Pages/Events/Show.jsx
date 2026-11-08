import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Select } from '@/Components/SchoolOS/Form';
import EventsLayout from '@/Components/SchoolOS/EventsLayout';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtWhen } from '@/lib/comm';
import { Link, router, useForm } from '@inertiajs/react';

export default function Show(props) {
    const { event: e, students = [], staff = [], canManage = false } = props;
    const { t, locale } = useLocale();
    const reg = useForm({ student_id: '', staff_id: '' });
    reg.transform((d) => ({ student_id: d.student_id || null, staff_id: d.staff_id || null }));

    return (
        <EventsLayout title={e.title} subtitle={e.venue ?? ''} current="calendar" canManage={canManage} shell={props} actions={<Link href={route('events.index')} className="btn-outline">{t('acad.common.back')}</Link>}>
            <section className="mb-6 overflow-hidden rounded-[1.6rem] border border-surface-muted bg-white p-5 shadow-soft sm:p-6">
            <p className="mb-4 text-sm text-slate-600"><StatusBadge status={e.status} label={enumLabel(t, 'ev.status', e.status)} /> · {enumLabel(t, 'ev.category', e.category)} · {fmtWhen(e.starts_at, locale)} – {fmtWhen(e.ends_at, locale)}</p>
            {e.description ? <p className="whitespace-pre-wrap text-sm text-slate-700">{e.description}</p> : null}
            </section>
            {canManage && e.status === 'draft' ? (
                <div className="mb-4 flex flex-wrap gap-2">
                    <button type="button" className="btn-teal" onClick={() => router.post(route('events.publish', e.id))}>{t('ev.publish')}</button>
                    <ReasonAction url={route('events.cancel', e.id)} fieldName="reason" label={t('acad.common.cancel')} tone="danger" />
                </div>
            ) : null}
            {canManage && e.status === 'published' ? (
                <div className="mb-4 flex flex-wrap gap-2">
                    <button type="button" className="btn-navy" onClick={() => router.post(route('events.complete', e.id))}>{t('ev.complete')}</button>
                    <ReasonAction url={route('events.cancel', e.id)} fieldName="reason" label={t('acad.common.cancel')} tone="danger" />
                </div>
            ) : null}
            {canManage ? (
                <>
                    <h2 className="mb-3 text-base font-semibold text-navy">{t('ev.regs')} ({e.taken}{e.capacity ? ` / ${e.capacity}` : ''})</h2>
                    <DataTable
                        columns={[
                            { key: 'who', label: t('inv.col.name') },
                            { key: 'kind', label: t('inv.col.type'), render: (r) => t(`ev.who.${r.kind}`) },
                            { key: 'status', label: t('inv.col.status'), render: (r) => enumLabel(t, 'ev.reg', r.status) },
                            { key: 'attendance', label: t('ev.att'), render: (r) => r.attendance ? enumLabel(t, 'ev.mark', r.attendance) : '—' },
                            {
                                key: 'actions',
                                label: '',
                                render: (r) => r.status === 'registered' ? (
                                    <div className="flex flex-wrap gap-2 whitespace-normal">
                                        <button type="button" className="btn-teal" onClick={() => router.post(route('events.registrations.attendance', r.id), { attendance: 'present' }, { preserveScroll: true })}>{t('ev.present')}</button>
                                        <button type="button" className="btn-outline" onClick={() => router.post(route('events.registrations.attendance', r.id), { attendance: 'absent' }, { preserveScroll: true })}>{t('ev.absent')}</button>
                                        <button type="button" className="btn-outline !text-red-700" onClick={() => router.post(route('events.registrations.drop', r.id), {}, { preserveScroll: true })}>{t('ev.drop')}</button>
                                    </div>
                                ) : null,
                            },
                        ]}
                        rows={e.registrations}
                        emptyMessage={t('ev.noRegs')}
                    />
                    {e.status === 'published' ? (
                        <Card title={t('ev.register')} className="mt-6 max-w-xl">
                            <form onSubmit={(e2) => { e2.preventDefault(); reg.post(route('events.register', e.id), { preserveScroll: true }); }} className="space-y-3">
                                <ErrorSummary errors={reg.errors} />
                                <Field label={t('tr.student')}>
                                    <Select value={reg.data.student_id} onChange={(e) => reg.setData((d) => ({ ...d, student_id: e.target.value, staff_id: '' }))}>
                                        <option value="">{t('acad.common.none')}</option>
                                        {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                    </Select>
                                </Field>
                                <Field label={t('hr.col.staff')}>
                                    <Select value={reg.data.staff_id} onChange={(e) => reg.setData((d) => ({ ...d, staff_id: e.target.value, student_id: '' }))}>
                                        <option value="">{t('acad.common.none')}</option>
                                        {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                    </Select>
                                </Field>
                                <button type="submit" className="btn-teal" disabled={reg.processing}>{t('ev.register')}</button>
                            </form>
                        </Card>
                    ) : null}
                </>
            ) : null}
        </EventsLayout>
    );
}
