import { Card, ErrorSummary, Field, Input, Pager, Select, Stat } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { ATTENDANCE_STATUSES, enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function CorrectionForm({ record, onDone }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({ to_status: 'present', reason: '' });

    function submit(e) {
        e.preventDefault();
        post(route('attendance.corrections.request', record.id), { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="mt-2 flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-3">
            <div className="w-full">
                <ErrorSummary errors={errors} />
            </div>
            <Field label={t('attendance.correction.newStatus')}>
                <Select value={data.to_status} onChange={(e) => setData('to_status', e.target.value)} className="!w-auto">
                    {ATTENDANCE_STATUSES.filter((s) => s !== record.status).map((s) => (
                        <option key={s} value={s}>
                            {enumLabel(t, 'attendance.status', s)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('acad.common.reason')} className="min-w-[14rem] flex-1">
                <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} minLength={5} maxLength={255} required />
            </Field>
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('attendance.correction.submit')}
            </button>
            <button type="button" className="btn-outline" onClick={onDone}>
                {t('acad.common.cancel')}
            </button>
        </form>
    );
}

export default function Student({ student, records, totals, canCorrect, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [open, setOpen] = useState(null);

    return (
        <AppShell title={t('attendance.student.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={student.name} />
            <PageHeader
                title={student.name}
                subtitle={`${student.student_number}${student.class ? ` · ${student.class}` : ''}`}
                actions={
                    <Link href={route('students.show', student.id)} className="btn-outline">
                        {t('attendance.student.profile')}
                    </Link>
                }
            />

            <div className="mb-6 grid gap-4 sm:grid-cols-4">
                <Stat label={t('attendance.report.rate')} value={totals.rate === null ? '—' : `${totals.rate}%`} tone="green" />
                <Stat label={t('attendance.status.absent')} value={totals.absent} tone="red" />
                <Stat label={t('attendance.status.excused')} value={totals.excused} />
                <Stat label={t('attendance.status.late')} value={totals.late} />
            </div>

            <Card title={t('attendance.student.history')}>
                {records.data.length === 0 ? <p className="text-sm text-slate-500">{t('attendance.report.empty')}</p> : null}
                <ul className="divide-y divide-slate-100">
                    {records.data.map((r) => (
                        <li key={r.id} className="py-2 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-medium text-navy">
                                    {r.date} <span className="text-xs font-normal text-slate-400">{r.session.startsWith('p') ? t('attendance.session.period') : t(`attendance.session.${r.session}`)}</span>
                                </span>
                                <span className="flex items-center gap-3">
                                    {r.reason ? <span className="text-xs text-slate-500">{r.reason}</span> : null}
                                    <StatusBadge status={r.status} label={enumLabel(t, 'attendance.status', r.status)} />
                                    {canCorrect ? (
                                        <button type="button" className="text-teal hover:underline" onClick={() => setOpen(open === r.id ? null : r.id)}>
                                            {t('attendance.correction.request')}
                                        </button>
                                    ) : null}
                                </span>
                            </div>
                            {open === r.id ? <CorrectionForm record={r} onDone={() => setOpen(null)} /> : null}
                        </li>
                    ))}
                </ul>
                <Pager links={records.links} />
            </Card>
        </AppShell>
    );
}
