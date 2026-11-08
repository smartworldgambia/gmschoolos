import { Input, Select, Stat } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { messageText } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';

export default function Report({ kind, date, summary, error, classes = [], selected, canAll, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const go = (next) => router.get(route('attendance.report'), { kind, date, class_id: selected ?? '', ...next }, { preserveState: true });
    const totals = summary?.totals;

    return (
        <AppShell title={t('attendance.reports')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('attendance.reports')} />
            <PageHeader
                title={t('attendance.reports')}
                subtitle={t('attendance.report.subtitle')}
                actions={
                    <Link href={route('attendance.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <div className="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm">
                <Select value={kind} onChange={(e) => go({ kind: e.target.value })} className="!w-auto" aria-label={t('attendance.report.period')}>
                    {['day', 'week', 'month', 'term'].map((k) => (
                        <option key={k} value={k}>
                            {t(`attendance.report.${k}`)}
                        </option>
                    ))}
                </Select>
                <Input type="date" max={new Date().toISOString().slice(0, 10)} value={date} onChange={(e) => go({ date: e.target.value })} className="!w-auto" aria-label={t('acad.common.date')} />
                <Select value={selected ?? ''} onChange={(e) => go({ class_id: e.target.value })} className="!w-auto" aria-label={t('students.col.class')}>
                    {canAll ? <option value="">{t('attendance.report.wholeSchool')}</option> : null}
                    {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </Select>
                {summary ? (
                    <span className="text-sm text-slate-500">
                        {summary.from} → {summary.to}
                    </span>
                ) : null}
            </div>

            {error ? <p className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{messageText(t, error)}</p> : null}

            {totals ? (
                <>
                    <div className="mb-6 grid gap-4 sm:grid-cols-4">
                        <Stat label={t('attendance.report.rate')} value={totals.rate === null ? '—' : `${totals.rate}%`} tone="green" />
                        <Stat label={t('attendance.status.present')} value={totals.present} />
                        <Stat label={t('attendance.status.absent')} value={totals.absent} tone="red" />
                        <Stat label={t('attendance.status.late')} value={totals.late} />
                    </div>
                    <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-start">{t('students.col.name')}</th>
                                    {['present', 'late', 'absent', 'excused', 'left_early'].map((s) => (
                                        <th key={s} className="px-4 py-3 text-start">
                                            {t(`attendance.status.${s}`)}
                                        </th>
                                    ))}
                                    <th className="px-4 py-3 text-start">{t('attendance.report.rate')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {summary.rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                            {t('attendance.report.empty')}
                                        </td>
                                    </tr>
                                ) : null}
                                {summary.rows.map((r) => (
                                    <tr key={r.student_id} className="border-t border-slate-100">
                                        <td className="px-4 py-2">
                                            <Link href={route('attendance.student', r.student_id)} className="font-medium text-navy hover:underline">
                                                {r.name}
                                            </Link>
                                            <p className="text-xs text-slate-400">{r.student_number}</p>
                                        </td>
                                        <td className="px-4 py-2">{r.present}</td>
                                        <td className="px-4 py-2">{r.late}</td>
                                        <td className="px-4 py-2">{r.absent}</td>
                                        <td className="px-4 py-2">{r.excused}</td>
                                        <td className="px-4 py-2">{r.left_early}</td>
                                        <td className="px-4 py-2">{r.rate === null ? '—' : `${r.rate}%`}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            ) : null}
        </AppShell>
    );
}
