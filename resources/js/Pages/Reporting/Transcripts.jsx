import { Input, Pager } from '@/Components/SchoolOS/Form';
import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { reportingShell } from './shell';

export default function Transcripts({ nav, user, school, academicYear, tabs = [], can = {}, documents, q = '', disclaimer }) {
    const { t } = useLocale();
    const [search, setSearch] = useState(q);
    const rows = documents?.data ?? [];

    return (
        <ModuleLayout
            ns="rpt"
            title={t('rpt.tr.title')}
            subtitle={t('rpt.tr.subtitle')}
            tabs={tabs}
            current="transcripts"
            shell={reportingShell({ nav, user, school, academicYear })}
            actions={
                can.recordsDraft ? (
                    <Link href={route('records.create')} className="btn-teal">
                        {t('rpt.newTranscript')}
                    </Link>
                ) : null
            }
        >
            <p className="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{disclaimer}</p>
            <form
                className="mb-4 flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('reporting.transcripts'), { q: search }, { preserveState: true, replace: true });
                }}
            >
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('rpt.searchPlaceholder')} className="max-w-xs" />
                <button type="submit" className="btn-outline">
                    {t('acad.common.search')}
                </button>
            </form>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('rpt.col.reference')}</th>
                            <th className="px-4 py-3 text-start">{t('rpt.col.student')}</th>
                            <th className="px-4 py-3 text-start">{t('rpt.col.type')}</th>
                            <th className="px-4 py-3 text-start">{t('rpt.col.status')}</th>
                            <th className="px-4 py-3 text-start">{t('rpt.col.issueDate')}</th>
                            <th className="px-4 py-3 text-end">{t('rpt.col.actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-4 py-12 text-center text-slate-500">
                                    {t('rpt.tr.empty')}
                                </td>
                            </tr>
                        ) : (
                            rows.map((d) => (
                                <tr key={d.id} className="border-t border-slate-100">
                                    <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                        {d.reference}
                                    </td>
                                    <td className="px-4 py-3">
                                        {d.student}
                                        <div className="font-mono text-xs text-slate-500" dir="ltr">
                                            {d.student_number}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">{enumLabel(t, 'rec.type', d.type)}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={d.status} label={enumLabel(t, 'rec.status', d.status)} />
                                    </td>
                                    <td className="px-4 py-3">{d.issue_date ?? '—'}</td>
                                    <td className="px-4 py-3 text-end">
                                        <Link href={d.show_url} className="btn-outline !px-2.5 !py-1 text-xs whitespace-nowrap">
                                            {t('rpt.action.openRecords')}
                                        </Link>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
                <div className="px-4 pb-4">
                    <Pager links={documents?.links} />
                </div>
            </div>
        </ModuleLayout>
    );
}
