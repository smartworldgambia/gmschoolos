import { useLocale } from '@/i18n/LocaleProvider';
import { assetUrl } from '@/lib/assetUrl';
import { Head, Link } from '@inertiajs/react';

export default function ReportCard({ student, school, grades = [], summary = {}, scale = [], history = [], academicYear }) {
    const { t } = useLocale();
    const dash = '—';
    const yearLabel = typeof academicYear === 'string' ? academicYear : academicYear?.name ?? '';

    return (
        <>
            <Head title={`${t('results.reportCard')} — ${student?.full_name ?? ''}`} />

            <div className="min-h-screen bg-surface py-6 print:bg-white print:py-0">
                <div className="mx-auto max-w-4xl px-4 sm:px-6">
                    <div className="mb-4 flex flex-wrap gap-2 print:hidden">
                        <button type="button" onClick={() => window.print()} className="btn-teal">
                            {t('results.print')}
                        </button>
                        <Link href={route('students.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </div>

                    <article className="rounded-xl bg-white p-6 shadow-sm sm:p-10 print:rounded-none print:shadow-none">
                        <header className="border-b border-slate-200 pb-6 text-center">
                            {school?.logo_url || school?.logo_path ? (
                                <img src={assetUrl(school.logo_url ?? school.logo_path)} alt="" className="mx-auto mb-3 h-16 w-16 object-contain" />
                            ) : null}
                            <h1 className="text-2xl font-extrabold text-navy">{school?.name}</h1>
                            {school?.address ? <p className="mt-1 text-xs text-slate-500">{school.address}</p> : null}
                            <p className="mt-2 text-sm font-medium text-slate-600">{t('results.reportCard')}</p>
                            {yearLabel ? <p className="text-sm text-navy">{yearLabel}</p> : null}
                        </header>

                        <section className="mt-6 grid gap-3 text-sm sm:grid-cols-3">
                            <p>
                                <span className="text-slate-500">{t('results.student')}:</span>{' '}
                                <span className="font-semibold text-navy">{student?.full_name}</span>
                            </p>
                            <p>
                                <span className="text-slate-500">{t('students.col.number')}:</span>{' '}
                                <span className="font-semibold text-navy">{student?.student_number ?? dash}</span>
                            </p>
                            <p>
                                <span className="text-slate-500">{t('students.col.class')}:</span>{' '}
                                <span className="font-semibold text-navy">{student?.class ?? dash}</span>
                            </p>
                        </section>

                        <div className="mt-8 overflow-x-auto">
                            <table className="min-w-full border-collapse text-start text-sm">
                                <thead>
                                    <tr className="bg-navy text-white">
                                        <th className="px-3 py-2.5 text-start font-semibold">{t('results.col.subject')}</th>
                                        <th className="px-3 py-2.5 text-start font-semibold">{t('results.col.examination')}</th>
                                        <th className="px-3 py-2.5 text-start font-semibold">{t('results.col.ca')}</th>
                                        <th className="px-3 py-2.5 text-start font-semibold">{t('results.col.exam')}</th>
                                        <th className="px-3 py-2.5 text-start font-semibold">{t('results.col.total')}</th>
                                        <th className="px-3 py-2.5 text-start font-semibold">{t('results.col.grade')}</th>
                                        <th className="px-3 py-2.5 text-start font-semibold">{t('results.col.remark')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {grades.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-8 text-center text-slate-500">
                                                {t('results.empty')}
                                            </td>
                                        </tr>
                                    ) : (
                                        grades.map((row, index) => (
                                            <tr key={index} className={index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}>
                                                <td className="border-b border-slate-100 px-3 py-2.5 font-medium text-navy">{row.subject ?? dash}</td>
                                                <td className="border-b border-slate-100 px-3 py-2.5">{row.examination ?? dash}</td>
                                                <td className="border-b border-slate-100 px-3 py-2.5">{row.ca_score ?? dash}</td>
                                                <td className="border-b border-slate-100 px-3 py-2.5">{row.exam_score ?? dash}</td>
                                                <td className="border-b border-slate-100 px-3 py-2.5 font-semibold">
                                                    {row.total ?? dash}
                                                    {row.revised ? <sup className="ms-1 text-amber-600">*</sup> : null}
                                                </td>
                                                <td className="border-b border-slate-100 px-3 py-2.5">{row.letter_grade ?? dash}</td>
                                                <td className="border-b border-slate-100 px-3 py-2.5 text-slate-500">{row.remark ?? dash}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                            {grades.some((g) => g.revised) ? <p className="mt-2 text-xs text-slate-500">* {t('results.revisedNote')}</p> : null}
                        </div>

                        <section className="mt-8 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-xl bg-surface p-4">
                                <p className="text-xs text-slate-500">{t('results.col.average')}</p>
                                <p className="mt-1 text-xl font-bold text-navy">{summary.average ?? dash}</p>
                            </div>
                            <div className="rounded-xl bg-surface p-4">
                                <p className="text-xs text-slate-500">{t('results.overallGrade')}</p>
                                <p className="mt-1 text-xl font-bold text-navy">{summary.overall_grade ?? dash}</p>
                            </div>
                        </section>

                        {history.length > 0 ? (
                            <section className="mt-8">
                                <h2 className="mb-2 text-sm font-semibold text-navy">{t('students.show.performance')}</h2>
                                <table className="min-w-full text-start text-sm">
                                    <tbody>
                                        {history.map((h, i) => (
                                            <tr key={i} className="border-b border-slate-100">
                                                <td className="px-3 py-1.5">{h.examination}</td>
                                                <td className="px-3 py-1.5 text-slate-500">{h.term ?? dash}</td>
                                                <td className="px-3 py-1.5">{h.average}</td>
                                                <td className="px-3 py-1.5 font-semibold">{h.letter}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </section>
                        ) : null}

                        {scale.length > 0 ? (
                            <section className="mt-8 text-xs text-slate-500">
                                <h2 className="mb-1 font-semibold text-navy">{t('results.scale')}</h2>
                                <p>{scale.map((b) => `${b.letter} ≥ ${b.min}${b.remark ? ` (${b.remark})` : ''}`).join(' · ')}</p>
                            </section>
                        ) : null}
                    </article>
                </div>
            </div>
        </>
    );
}
