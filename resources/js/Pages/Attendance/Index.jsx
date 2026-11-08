import { Input, Stat } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ date, overview = [], classes = [], pendingCorrections, can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const totals = overview.reduce(
        (a, r) => ({ students: a.students + r.students, present: a.present + r.present + r.late + r.left_early, absent: a.absent + r.absent, marked: a.marked + (r.marked ? 1 : 0) }),
        { students: 0, present: 0, absent: 0, marked: 0 },
    );

    return (
        <AppShell title={t('attendance.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('attendance.title')} />
            <PageHeader
                title={t('attendance.title')}
                subtitle={t('attendance.subtitle')}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        {can.record ? (
                            <Link href={route('attendance.take', { date })} className="btn-teal">
                                {t('attendance.take')}
                            </Link>
                        ) : null}
                        <Link href={route('attendance.corrections')} className="btn-outline">
                            {t('attendance.corrections')}
                            {pendingCorrections ? ` (${pendingCorrections})` : ''}
                        </Link>
                        <Link href={route('attendance.report')} className="btn-outline">
                            {t('attendance.reports')}
                        </Link>
                        {can.staff ? (
                            <Link href={route('attendance.staff', { date })} className="btn-outline">
                                {t('attendance.staff')}
                            </Link>
                        ) : null}
                    </div>
                }
            />

            <div className="mb-4 flex items-center gap-3">
                <label className="text-sm text-slate-600" htmlFor="att-date">
                    {t('acad.common.date')}
                </label>
                <Input id="att-date" type="date" max={new Date().toISOString().slice(0, 10)} value={date} onChange={(e) => router.get(route('attendance.index'), { date: e.target.value }, { preserveState: true })} className="!w-auto" />
            </div>

            {can.approve ? (
                <>
                    <div className="mb-6 grid gap-4 sm:grid-cols-4">
                        <Stat label={t('attendance.stat.classesMarked')} value={`${totals.marked} / ${overview.length}`} />
                        <Stat label={t('attendance.stat.learners')} value={totals.students} />
                        <Stat label={t('attendance.stat.attended')} value={totals.present} tone="green" />
                        <Stat label={t('attendance.stat.absent')} value={totals.absent} tone="red" />
                    </div>
                    <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-start">{t('students.col.class')}</th>
                                    <th className="px-4 py-3 text-start">{t('attendance.stat.learners')}</th>
                                    <th className="px-4 py-3 text-start">{t('attendance.status.present')}</th>
                                    <th className="px-4 py-3 text-start">{t('attendance.status.late')}</th>
                                    <th className="px-4 py-3 text-start">{t('attendance.status.absent')}</th>
                                    <th className="px-4 py-3 text-start">{t('attendance.status.excused')}</th>
                                    <th className="px-4 py-3 text-start">{t('students.col.status')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {overview.map((r) => (
                                    <tr key={r.class_id} className="border-t border-slate-100">
                                        <td className="px-4 py-2 font-medium text-navy">
                                            <Link href={route('attendance.take', { class_id: r.class_id, date })} className="hover:underline">
                                                {r.class}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2">{r.students}</td>
                                        <td className="px-4 py-2">{r.present}</td>
                                        <td className="px-4 py-2">{r.late}</td>
                                        <td className="px-4 py-2">{r.absent}</td>
                                        <td className="px-4 py-2">{r.excused}</td>
                                        <td className="px-4 py-2">
                                            <StatusBadge status={r.marked ? 'completed' : 'pending'} label={r.marked ? t('attendance.registered') : t('attendance.notRegistered')} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            ) : (
                <div className="rounded-xl bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-base font-semibold text-navy">{t('attendance.myClasses')}</h2>
                    {classes.length === 0 ? <p className="text-sm text-slate-500">{t('attendance.noClasses')}</p> : null}
                    <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {classes.map((c) => (
                            <li key={c.id}>
                                <Link href={route('attendance.take', { class_id: c.id, date })} className="block rounded-lg border border-slate-200 px-4 py-3 text-sm font-medium text-navy hover:border-teal">
                                    {c.name}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </AppShell>
    );
}
