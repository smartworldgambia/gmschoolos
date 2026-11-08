import { Card, ErrorSummary, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

function Register({ date, session, selected, slotId, roster, statuses, locked, canTake }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({
        class_id: selected,
        date,
        session,
        slot_id: slotId ?? '',
        entries: roster.map((r) => ({
            student_id: r.student_id,
            status: r.status ?? 'present',
            reason: r.reason ?? '',
            arrived_at: r.arrived_at ?? '',
            left_at: r.left_at ?? '',
        })),
    });
    const setEntry = (i, key, value) => setData('entries', data.entries.map((e, j) => (j === i ? { ...e, [key]: value } : e)));
    const disabled = locked || !canTake;

    function submit(e) {
        e.preventDefault();
        post(route('attendance.store'), { preserveScroll: true });
    }

    if (roster.length === 0) {
        return <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('attendance.noLearners')}</p>;
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            <ErrorSummary errors={errors} />
            {locked ? <p className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{t('attendance.lockedNotice')}</p> : null}
            {!canTake ? <p className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{t('attendance.notYourRegister')}</p> : null}

            <div className="flex flex-wrap gap-2 text-sm">
                <span className="text-slate-500">{t('attendance.markAll')}:</span>
                {statuses.slice(0, 2).map((s) => (
                    <button key={s} type="button" disabled={disabled} className="text-teal hover:underline disabled:opacity-40" onClick={() => setData('entries', data.entries.map((e) => ({ ...e, status: s })))}>
                        {enumLabel(t, 'attendance.status', s)}
                    </button>
                ))}
            </div>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('students.col.name')}</th>
                            <th className="px-4 py-3 text-start">{t('students.col.status')}</th>
                            <th className="px-4 py-3 text-start">{t('attendance.arrived')}</th>
                            <th className="px-4 py-3 text-start">{t('attendance.left')}</th>
                            <th className="px-4 py-3 text-start">{t('acad.common.reason')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {roster.map((r, i) => {
                            const e = data.entries[i];
                            const changed = r.status && r.status !== e.status;

                            return (
                                <tr key={r.student_id} className="border-t border-slate-100">
                                    <td className="px-4 py-2">
                                        <p className="font-medium text-navy">{r.name}</p>
                                        <p className="text-xs text-slate-400">{r.student_number}</p>
                                    </td>
                                    <td className="px-4 py-2">
                                        <Select value={e.status} onChange={(ev) => setEntry(i, 'status', ev.target.value)} disabled={disabled} className="!w-auto">
                                            {statuses.map((s) => (
                                                <option key={s} value={s}>
                                                    {enumLabel(t, 'attendance.status', s)}
                                                </option>
                                            ))}
                                        </Select>
                                        {changed ? <span className="ms-2 text-xs text-amber-600">{t('attendance.changed')}</span> : null}
                                    </td>
                                    <td className="px-4 py-2">
                                        <Input type="time" value={e.arrived_at} onChange={(ev) => setEntry(i, 'arrived_at', ev.target.value)} disabled={disabled || e.status !== 'late'} className="!w-28" />
                                    </td>
                                    <td className="px-4 py-2">
                                        <Input type="time" value={e.left_at} onChange={(ev) => setEntry(i, 'left_at', ev.target.value)} disabled={disabled || e.status !== 'left_early'} className="!w-28" />
                                    </td>
                                    <td className="px-4 py-2">
                                        <Input value={e.reason} onChange={(ev) => setEntry(i, 'reason', ev.target.value)} disabled={disabled} placeholder={t('attendance.reasonPlaceholder')} />
                                    </td>
                                    <td className="px-4 py-2 text-end">
                                        {r.record_id && locked ? <CorrectionLink record={r} /> : null}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {!disabled ? (
                <button type="submit" className="btn-teal" disabled={processing}>
                    {t('attendance.save')}
                </button>
            ) : null}
        </form>
    );
}

function CorrectionLink({ record }) {
    const { t } = useLocale();

    return (
        <Link href={route('attendance.student', record.student_id)} className="text-teal hover:underline">
            {t('attendance.correction.request')}
        </Link>
    );
}
export default function Take(props) {
    const { t } = useLocale();
    const { date, session, classes = [], selected, slots = [], slotId, roster = [], statuses = [], locked, canTake, nav = [], user, school, academicYear } = props;

    function go(next) {
        router.get(route('attendance.take'), { class_id: selected, date, session: slotId ? undefined : session, slot_id: slotId || undefined, ...next }, { preserveScroll: true });
    }

    const sessionValue = slotId ? `p${slotId}` : session;

    return (
        <AppShell title={t('attendance.take')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('attendance.take')} />
            <PageHeader
                title={t('attendance.take')}
                subtitle={t('attendance.takeSubtitle')}
                actions={
                    <Link href={route('attendance.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <Card className="mb-4">
                <div className="flex flex-wrap items-end gap-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">{t('students.col.class')}</label>
                        <Select value={selected ?? ''} onChange={(e) => go({ class_id: e.target.value, slot_id: undefined, session: 'full' })} className="!w-auto">
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">{t('acad.common.date')}</label>
                        <Input type="date" max={new Date().toISOString().slice(0, 10)} value={date} onChange={(e) => go({ date: e.target.value, slot_id: undefined })} className="!w-auto" />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">{t('attendance.session')}</label>
                        <Select
                            value={sessionValue}
                            onChange={(e) => {
                                const v = e.target.value;
                                v.startsWith('p') ? go({ slot_id: v.slice(1), session: undefined }) : go({ session: v, slot_id: undefined });
                            }}
                            className="!w-auto"
                        >
                            {['full', 'am', 'pm'].map((s) => (
                                <option key={s} value={s}>
                                    {t(`attendance.session.${s}`)}
                                </option>
                            ))}
                            {slots.map((s) => (
                                <option key={s.id} value={`p${s.id}`}>
                                    {s.label} ({s.starts_at}–{s.ends_at})
                                </option>
                            ))}
                        </Select>
                    </div>
                </div>
            </Card>

            {selected ? (
                <Register key={`${selected}-${date}-${sessionValue}`} date={date} session={session} selected={selected} slotId={slotId} roster={roster} statuses={statuses} locked={locked} canTake={canTake} />
            ) : (
                <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('attendance.noClasses')}</p>
            )}
        </AppShell>
    );
}
