import { Input, Pager, Select, Stat } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import SeniorRoundForm from '@/Components/SchoolOS/SeniorRoundForm';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const NEXT = { draft: ['open'], open: ['closed', 'draft'], closed: ['open', 'finalised'], finalised: [] };
const STATUSES = ['submitted', 'under_review', 'shortlisted', 'waitlisted', 'approved', 'rejected', 'withdrawn'];

export default function Round({ round, applications, counts = {}, status = '', q = '', can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(false);
    const [search, setSearch] = useState(q);
    const finalised = round.status === 'finalised';

    function filter(params) {
        router.get(route('senior-secondary.rounds.show', round.id), { q: search, status, ...params }, { preserveState: true, replace: true });
    }

    function act(name, method = 'post', data = {}) {
        router[method](route(name, round.id), data, { preserveScroll: true });
    }

    return (
        <AppShell title={round.name} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={round.name} />
            <PageHeader
                title={round.name}
                subtitle={`${round.opens_on ?? '…'} → ${round.closes_on ?? '…'}`}
                actions={
                    <>
                        <StatusBadge status={round.status} label={enumLabel(t, 'ss.roundStatus', round.status)} />
                        <Link href={route('senior-secondary.rounds.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Stat label={t('ss.col.applications')} value={round.applications_count} />
                <Stat label={t('ss.col.capacity')} value={`${round.capacity}${round.waitlist_capacity ? ` + ${round.waitlist_capacity}` : ''}`} />
                <Stat label={enumLabel(t, 'ss.appStatus', 'approved')} value={counts.approved ?? 0} tone="green" />
                <Stat label={enumLabel(t, 'ss.appStatus', 'waitlisted')} value={counts.waitlisted ?? 0} />
            </div>

            {round.public_url ? (
                <p className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {t('ss.publicLink')}:{' '}
                    <a href={round.public_url} target="_blank" rel="noreferrer" className="break-all font-mono underline" dir="ltr">
                        {round.public_url}
                    </a>
                </p>
            ) : null}

            {can.manage ? (
                <div className="mb-6 flex flex-wrap gap-2">
                    {NEXT[round.status].map((s) => (
                        <button key={s} type="button" className={s === 'finalised' ? 'btn-navy' : 'btn-outline'} onClick={() => act('senior-secondary.rounds.status', 'patch', { status: s })}>
                            {t(`ss.moveTo.${s}`)}
                        </button>
                    ))}
                    {!finalised ? (
                        <>
                            <button type="button" className="btn-outline" onClick={() => act('senior-secondary.rounds.recompute')}>
                                {t('ss.recompute')}
                            </button>
                            <button type="button" className="btn-teal" onClick={() => act('senior-secondary.rounds.apply')}>
                                {t('ss.applyRecommendations')}
                            </button>
                            <button type="button" className="btn-outline" onClick={() => setEditing(!editing)}>
                                {editing ? t('acad.common.cancel') : t('ss.editRules')}
                            </button>
                        </>
                    ) : null}
                </div>
            ) : null}
            {can.manage && !finalised ? <p className="mb-6 text-xs text-slate-500">{t('ss.workflowHelp')}</p> : null}

            {editing ? (
                <div className="mb-8">
                    <SeniorRoundForm round={round} url={route('senior-secondary.rounds.update', round.id)} method="put" onDone={() => setEditing(false)} />
                </div>
            ) : null}

            <form
                className="mb-4 flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    filter({});
                }}
            >
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('ss.searchPlaceholder')} className="max-w-xs" />
                <Select value={status} onChange={(e) => filter({ status: e.target.value })} className="max-w-[12rem]">
                    <option value="">{t('acad.common.all')}</option>
                    {STATUSES.map((s) => (
                        <option key={s} value={s}>
                            {enumLabel(t, 'ss.appStatus', s)} ({counts[s] ?? 0})
                        </option>
                    ))}
                </Select>
                <button type="submit" className="btn-outline">
                    {t('acad.common.search')}
                </button>
            </form>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('ss.col.rank')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.applicant')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.aggregate')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.eligible')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.verified')}</th>
                            <th className="px-4 py-3 text-start">{t('ss.col.recommendation')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {applications.data.length === 0 ? (
                            <tr>
                                <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                                    {t('ss.noApplications')}
                                </td>
                            </tr>
                        ) : null}
                        {applications.data.map((a) => (
                            <tr key={a.id}>
                                <td className="px-4 py-3 font-semibold">{a.rank ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-navy">{a.name}</p>
                                    <p className="font-mono text-xs text-slate-500" dir="ltr">
                                        {a.reference}
                                    </p>
                                </td>
                                <td className="px-4 py-3">{a.aggregate ?? '—'}</td>
                                <td className="px-4 py-3">{a.eligible === null ? '—' : a.eligible ? t('acad.common.yes') : t('acad.common.no')}</td>
                                <td className="px-4 py-3">{a.verified ? t('acad.common.yes') : t('acad.common.no')}</td>
                                <td className="px-4 py-3">{a.recommendation ? enumLabel(t, 'ss.recommendation', a.recommendation) : '—'}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={a.status} label={enumLabel(t, 'ss.appStatus', a.status)} />
                                    {a.override ? <span className="ms-2 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">{t('ss.override')}</span> : null}
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <Link href={route('senior-secondary.applications.show', a.id)} className="btn-outline">
                                        {t('ext.open')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager links={applications.links} />
        </AppShell>
    );
}
