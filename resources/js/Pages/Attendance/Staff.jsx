import { ErrorSummary, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Staff({ date, statuses = [], staff = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({
        date,
        entries: staff.map((s) => ({ staff_id: s.staff_id, status: s.status ?? 'present', remarks: s.remarks ?? '' })),
    });
    const setEntry = (i, key, value) => setData('entries', data.entries.map((e, j) => (j === i ? { ...e, [key]: value } : e)));

    return (
        <AppShell title={t('attendance.staff')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('attendance.staff')} />
            <PageHeader
                title={t('attendance.staff')}
                subtitle={t('attendance.staffSubtitle')}
                actions={
                    <Link href={route('attendance.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />
            <div className="mb-4">
                <Input type="date" max={new Date().toISOString().slice(0, 10)} value={date} onChange={(e) => router.get(route('attendance.staff'), { date: e.target.value })} className="!w-auto" aria-label={t('acad.common.date')} />
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('attendance.staff.store'), { preserveScroll: true });
                }}
                className="space-y-4"
            >
                <ErrorSummary errors={errors} />
                {staff.length === 0 ? <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('attendance.staffEmpty')}</p> : null}
                {staff.length > 0 ? (
                    <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-start">{t('students.col.name')}</th>
                                    <th className="px-4 py-3 text-start">{t('students.col.status')}</th>
                                    <th className="px-4 py-3 text-start">{t('attendance.remarks')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {staff.map((s, i) => (
                                    <tr key={s.staff_id} className="border-t border-slate-100">
                                        <td className="px-4 py-2">
                                            <p className="font-medium text-navy">{s.name}</p>
                                            {s.role_title ? <p className="text-xs text-slate-400">{s.role_title}</p> : null}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Select value={data.entries[i].status} onChange={(e) => setEntry(i, 'status', e.target.value)} className="!w-auto">
                                                {statuses.map((st) => (
                                                    <option key={st} value={st}>
                                                        {enumLabel(t, 'attendance.status', st)}
                                                    </option>
                                                ))}
                                            </Select>
                                        </td>
                                        <td className="px-4 py-2">
                                            <Input value={data.entries[i].remarks} onChange={(e) => setEntry(i, 'remarks', e.target.value)} maxLength={255} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : null}
                {staff.length > 0 ? (
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('attendance.save')}
                    </button>
                ) : null}
            </form>
        </AppShell>
    );
}
