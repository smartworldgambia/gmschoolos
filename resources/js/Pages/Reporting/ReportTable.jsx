import { Pager } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link } from '@inertiajs/react';

export default function ReportTable({ reports, can = {} }) {
    const { t } = useLocale();
    const rows = reports?.data ?? reports ?? [];

    return (
        <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th className="px-4 py-3 text-start">{t('rpt.col.student')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.number')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.class')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.year')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.term')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.average')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.grade')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.status')}</th>
                        <th className="px-4 py-3 text-start">{t('rpt.col.generated')}</th>
                        <th className="px-4 py-3 text-end">{t('rpt.col.actions')}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={10} className="px-4 py-12 text-center text-slate-500">
                                {t('rpt.empty')}
                            </td>
                        </tr>
                    ) : (
                        rows.map((r) => (
                            <tr key={r.id} className="border-t border-slate-100">
                                <td className="px-4 py-3 font-medium text-navy">
                                    <Link href={route('reporting.show', r.id)} className="hover:underline">
                                        {r.student}
                                    </Link>
                                    {r.incomplete ? <span className="ms-2 text-xs font-normal text-amber-700">{t('rpt.incomplete')}</span> : null}
                                </td>
                                <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                    {r.student_number}
                                </td>
                                <td className="px-4 py-3">{r.class ?? '—'}</td>
                                <td className="px-4 py-3">{r.academic_year ?? '—'}</td>
                                <td className="px-4 py-3">{r.term ?? '—'}</td>
                                <td className="px-4 py-3">{r.average ?? '—'}</td>
                                <td className="px-4 py-3 font-semibold">{r.grade ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={r.status} label={enumLabel(t, 'rpt.status', r.status)} />
                                </td>
                                <td className="px-4 py-3 text-slate-500">{r.generated_at ?? '—'}</td>
                                <td className="px-4 py-3 text-end">
                                    <div className="flex flex-wrap justify-end gap-1">
                                        <Link href={route('reporting.show', r.id)} className="btn-outline !px-2.5 !py-1 text-xs">
                                            {t('rpt.action.view')}
                                        </Link>
                                        {can.prepare || can.family ? (
                                            <a href={route('reporting.pdf', r.id)} className="btn-outline !px-2.5 !py-1 text-xs whitespace-nowrap">
                                                {t('rpt.action.pdf')}
                                            </a>
                                        ) : null}
                                    </div>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
            <div className="px-4 pb-4">
                <Pager links={reports?.links} />
            </div>
        </div>
    );
}
