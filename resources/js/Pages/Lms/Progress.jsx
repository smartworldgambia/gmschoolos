import LmsLayout from '@/Components/Lms/LmsLayout';
import { Stat } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { fmt, num } from '@/lib/lms';
import { useState } from 'react';

export default function Progress({ course, author, summary, rows = [] }) {
    const { t } = useLocale();
    const [onlyInactive, setOnlyInactive] = useState(false);
    const shown = onlyInactive ? rows.filter((r) => r.inactive) : rows;
    const pct = (v) => (v === null || v === undefined ? '—' : `${v}%`);

    return (
        <LmsLayout title={t('lms.progress.title')} subtitle={t('lms.progress.subtitle')} course={course} author={author} active="progress">
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label={t('lms.progress.enrolled')} value={summary.enrolled} />
                <Stat label={t('lms.progress.lessonCompletion')} value={pct(summary.avg_lesson_percent)} />
                <Stat label={t('lms.progress.assignmentCompletion')} value={pct(summary.assignment_completion)} />
                <Stat label={t('lms.progress.quizAttempts')} value={summary.quiz_attempts} />
                <Stat label={t('lms.progress.avgGrade')} value={summary.avg_grade !== null ? `${num(summary.avg_grade)}%` : '—'} />
                <Stat label={t('lms.progress.inactive')} value={summary.inactive} tone={summary.inactive > 0 ? 'red' : 'green'} />
                <Stat label={t('lms.progress.toMark')} value={(summary.pending?.submissions ?? 0) + (summary.pending?.attempts ?? 0)} />
                <Stat label={t('lms.progress.withdrawn')} value={summary.withdrawn} />
            </div>

            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-slate-500">
                    {t('lms.progress.inactiveRule')} {summary.inactive_days} {t('lms.progress.days')}
                </p>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" className="rounded border-gray-300" checked={onlyInactive} onChange={(e) => setOnlyInactive(e.target.checked)} />
                    {t('lms.progress.onlyInactive')}
                </label>
            </div>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('lms.common.student')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.grades.lessons')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.progress.assignments')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.progress.quizAttempts')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.grades.coursePercent')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.progress.lastActivity')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.common.status')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {shown.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                    {t('lms.people.none')}
                                </td>
                            </tr>
                        ) : null}
                        {shown.map((r) => (
                            <tr key={r.student.id}>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-navy">{r.student.name}</p>
                                    <p className="text-xs text-slate-500">
                                        {r.student.number}
                                        {r.student.class ? ` · ${r.student.class}` : ''}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    {r.lessons_done}/{r.lessons_total} <span className="text-xs text-slate-500">({pct(r.lesson_percent)})</span>
                                </td>
                                <td className="px-4 py-3">
                                    {r.assignments_done}/{r.assignments_total}
                                </td>
                                <td className="px-4 py-3">{r.quiz_attempts}</td>
                                <td className="px-4 py-3">{r.grade_percent !== null ? `${num(r.grade_percent)}%` : '—'}</td>
                                <td className="px-4 py-3">{fmt(r.last_activity)}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={r.inactive ? 'inactive' : 'active'} label={r.inactive ? t('lms.progress.stateInactive') : t('lms.progress.stateActive')} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </LmsLayout>
    );
}
