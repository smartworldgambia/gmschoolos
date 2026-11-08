import { Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function Decide({ id }) {
    const { t } = useLocale();
    const [note, setNote] = useState('');
    const send = (decision) => router.post(route('attendance.corrections.decide', id), { decision, note }, { preserveScroll: true });

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Input value={note} onChange={(e) => setNote(e.target.value)} placeholder={t('attendance.correction.note')} className="!w-44" maxLength={255} />
            <button type="button" className="btn-teal" onClick={() => send('approve')}>
                {t('acad.common.approve')}
            </button>
            <button type="button" className="btn-outline" onClick={() => send('reject')}>
                {t('acad.common.reject')}
            </button>
        </div>
    );
}

export default function Corrections({ corrections = [], filters = {}, can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();

    return (
        <AppShell title={t('attendance.corrections')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('attendance.corrections')} />
            <PageHeader
                title={t('attendance.corrections')}
                subtitle={t('attendance.correction.subtitle')}
                actions={
                    <Link href={route('attendance.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <div className="mb-4">
                <Select value={filters.status ?? ''} onChange={(e) => router.get(route('attendance.corrections'), { status: e.target.value }, { preserveState: true })} className="!w-auto" aria-label={t('students.col.status')}>
                    <option value="">{t('acad.common.all')}</option>
                    {['pending', 'approved', 'rejected'].map((s) => (
                        <option key={s} value={s}>
                            {enumLabel(t, 'correction', s)}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('students.col.name')}</th>
                            <th className="px-4 py-3 text-start">{t('acad.common.date')}</th>
                            <th className="px-4 py-3 text-start">{t('attendance.correction.change')}</th>
                            <th className="px-4 py-3 text-start">{t('acad.common.reason')}</th>
                            <th className="px-4 py-3 text-start">{t('attendance.correction.requestedBy')}</th>
                            <th className="px-4 py-3 text-start">{t('students.col.status')}</th>
                            {can.approve ? <th className="px-4 py-3" /> : null}
                        </tr>
                    </thead>
                    <tbody>
                        {corrections.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                    {t('attendance.correction.empty')}
                                </td>
                            </tr>
                        ) : null}
                        {corrections.map((c) => (
                            <tr key={c.id} className="border-t border-slate-100 align-top">
                                <td className="px-4 py-2 font-medium text-navy">{c.student}</td>
                                <td className="px-4 py-2 whitespace-nowrap">{c.date}</td>
                                <td className="px-4 py-2 whitespace-nowrap">
                                    {enumLabel(t, 'attendance.status', c.from)} → {enumLabel(t, 'attendance.status', c.to)}
                                </td>
                                <td className="px-4 py-2">
                                    {c.reason}
                                    {c.decision_note ? <p className="text-xs text-slate-400">{c.decision_note}</p> : null}
                                </td>
                                <td className="px-4 py-2">{c.requested_by}</td>
                                <td className="px-4 py-2">
                                    <StatusBadge status={c.status} label={enumLabel(t, 'correction', c.status)} />
                                </td>
                                {can.approve ? <td className="px-4 py-2">{c.status === 'pending' ? <Decide id={c.id} /> : null}</td> : null}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
