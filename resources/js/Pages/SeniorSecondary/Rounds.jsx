import PageHeader from '@/Components/SchoolOS/PageHeader';
import SeniorRoundForm from '@/Components/SchoolOS/SeniorRoundForm';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Rounds({ rounds = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [creating, setCreating] = useState(false);

    return (
        <AppShell title={t('ss.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('ss.title')} />
            <PageHeader
                title={t('ss.title')}
                subtitle={t('ss.subtitle')}
                actions={
                    can.manage ? (
                        <button type="button" className="btn-teal" onClick={() => setCreating(!creating)}>
                            {creating ? t('acad.common.cancel') : t('ss.newRound')}
                        </button>
                    ) : null
                }
            />

            {creating ? (
                <div className="mb-8">
                    <SeniorRoundForm url={route('senior-secondary.rounds.store')} />
                </div>
            ) : null}

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('ss.col.name')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.window')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.capacity')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.applications')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rounds.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                    {t('ss.noRounds')}
                                </td>
                            </tr>
                        ) : null}
                        {rounds.map((r) => (
                            <tr key={r.id}>
                                <td className="px-4 py-3 font-medium text-navy">{r.name}</td>
                                <td className="px-4 py-3 text-slate-600">{r.opens_on || r.closes_on ? `${r.opens_on ?? '…'} → ${r.closes_on ?? '…'}` : '—'}</td>
                                <td className="px-4 py-3">
                                    {r.capacity}
                                    {r.waitlist_capacity ? ` + ${r.waitlist_capacity}` : ''}
                                </td>
                                <td className="px-4 py-3">{r.applications_count}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={r.status} label={enumLabel(t, 'ss.roundStatus', r.status)} />
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <Link href={route('senior-secondary.rounds.show', r.id)} className="btn-outline">
                                        {t('ext.open')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
