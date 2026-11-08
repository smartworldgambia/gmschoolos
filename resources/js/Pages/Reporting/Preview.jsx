import { useLocale } from '@/i18n/LocaleProvider';
import { Head, Link } from '@inertiajs/react';

export default function Preview({ report, disclaimer }) {
    const { t } = useLocale();
    const payload = report.payload ?? {};
    const school = payload.school ?? {};
    const student = payload.student ?? {};
    const subjects = payload.subjects ?? [];
    const cfg = payload.template?.config ?? {};
    const title = cfg.title || t(`rpt.type.${report.type}`);

    return (
        <>
            <Head title={`${title} — ${student.full_name ?? ''}`} />
            <div className="min-h-screen bg-surface py-6 print:bg-white print:py-0">
                <div className="mx-auto max-w-4xl px-4 sm:px-6">
                    <div className="mb-4 flex flex-wrap gap-2 print:hidden">
                        <button type="button" onClick={() => window.print()} className="btn-teal">
                            {t('rpt.action.print')}
                        </button>
                        <a href={report.pdf_url} className="btn-outline whitespace-nowrap">
                            {t('rpt.action.pdf')}
                        </a>
                        <Link href={route('reporting.show', report.id)} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </div>

                    <article className="rounded-xl bg-white p-6 shadow-sm sm:p-10 print:rounded-none print:shadow-none">
                        <header className="border-b border-slate-200 pb-5 text-center">
                            <h1 className="text-2xl font-extrabold text-navy">{school.name}</h1>
                            {school.address ? <p className="mt-1 text-xs text-slate-500">{school.address}</p> : null}
                            {school.motto ? <p className="mt-1 text-xs italic text-slate-500">{school.motto}</p> : null}
                            <p className="mt-2 text-sm font-semibold text-slate-600">{title}</p>
                            <p className="text-xs text-slate-500">{disclaimer}</p>
                        </header>

                        <section className="mt-5 grid gap-2 text-sm sm:grid-cols-2">
                            <p>
                                <span className="text-slate-500">{t('rpt.col.student')}:</span> <strong className="text-navy">{student.full_name}</strong>
                            </p>
                            <p>
                                <span className="text-slate-500">{t('rpt.col.number')}:</span> <strong dir="ltr">{student.student_number}</strong>
                            </p>
                            <p>
                                <span className="text-slate-500">{t('rpt.col.class')}:</span> {payload.class?.name ?? '—'}
                            </p>
                            <p>
                                <span className="text-slate-500">{t('rpt.col.year')}:</span> {payload.academic_year}
                            </p>
                            {report.term ? (
                                <p>
                                    <span className="text-slate-500">{t('rpt.col.term')}:</span> {report.term}
                                </p>
                            ) : null}
                            <p>
                                <span className="text-slate-500">{t('rpt.col.reference')}:</span> <span dir="ltr">{report.reference}</span>
                            </p>
                        </section>

                        {report.type === 'annual' ? (
                            <div className="mt-6 overflow-x-auto">
                                <table className="min-w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="bg-navy text-white">
                                            <th className="border border-slate-300 px-2 py-2 text-start">{t('results.col.subject')}</th>
                                            {(payload.terms ?? []).map((term) => (
                                                <th key={term.id} className="border border-slate-300 px-2 py-2">
                                                    {term.name}
                                                </th>
                                            ))}
                                            <th className="border border-slate-300 px-2 py-2">{t('rpt.show.annualAvg')}</th>
                                            <th className="border border-slate-300 px-2 py-2">{t('rpt.col.grade')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {subjects.map((row) => (
                                            <tr key={row.subject_id}>
                                                <td className="border border-slate-300 px-2 py-1.5 font-medium">{row.name}</td>
                                                {(row.terms ?? []).map((tr) => (
                                                    <td key={tr.term_id} className="border border-slate-300 px-2 py-1.5 text-center">
                                                        {tr.status === 'missing' ? '—' : (tr.total ?? '—')}
                                                    </td>
                                                ))}
                                                <td className="border border-slate-300 px-2 py-1.5 text-center">{row.incomplete ? '—' : (row.annual_average ?? '—')}</td>
                                                <td className="border border-slate-300 px-2 py-1.5 text-center">{row.grade ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <p className="mt-2 text-xs text-slate-500">{t(`rpt.weighting.${payload.weighting || 'equal_available'}`)}</p>
                            </div>
                        ) : (
                            <div className="mt-6 overflow-x-auto">
                                <table className="min-w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="bg-navy text-white">
                                            <th className="border border-slate-300 px-2 py-2 text-start">{t('results.col.subject')}</th>
                                            <th className="border border-slate-300 px-2 py-2">{t('results.col.ca')}</th>
                                            <th className="border border-slate-300 px-2 py-2">{t('results.col.exam')}</th>
                                            <th className="border border-slate-300 px-2 py-2">{t('results.col.total')}</th>
                                            <th className="border border-slate-300 px-2 py-2">{t('rpt.col.grade')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {subjects.map((row) => (
                                            <tr key={row.subject_id}>
                                                <td className="border border-slate-300 px-2 py-1.5 font-medium">{row.name}</td>
                                                <td className="border border-slate-300 px-2 py-1.5 text-center">{row.ca_score ?? '—'}</td>
                                                <td className="border border-slate-300 px-2 py-1.5 text-center">{row.exam_score ?? '—'}</td>
                                                <td className="border border-slate-300 px-2 py-1.5 text-center">{row.status === 'missing' ? '—' : (row.total ?? '—')}</td>
                                                <td className="border border-slate-300 px-2 py-1.5 text-center">{row.grade ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <section className="mt-6 grid gap-3 text-sm sm:grid-cols-3">
                            <p>
                                {t('rpt.col.average')}: <strong>{report.average ?? '—'}</strong>
                            </p>
                            <p>
                                {t('rpt.col.grade')}: <strong>{report.grade ?? '—'}</strong>
                            </p>
                            <p>
                                {t('rpt.show.attendance')}: <strong>{payload.attendance?.percentage != null ? `${payload.attendance.percentage}%` : '—'}</strong>
                            </p>
                        </section>

                        <section className="mt-6 space-y-2 text-sm">
                            <p>
                                <strong>{t('rpt.show.classTeacher')}:</strong> {report.class_teacher_comment || '—'}
                            </p>
                            <p>
                                <strong>{t('rpt.show.headteacher')}:</strong> {report.headteacher_comment || '—'}
                            </p>
                        </section>
                    </article>
                </div>
            </div>
        </>
    );
}
