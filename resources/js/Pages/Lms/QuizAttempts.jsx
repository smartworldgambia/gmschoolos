import LmsLayout from '@/Components/Lms/LmsLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmt, num } from '@/lib/lms';
import { Link, router } from '@inertiajs/react';

export default function QuizAttempts({ course, author, quiz, attempts = [] }) {
    const { t } = useLocale();

    return (
        <LmsLayout
            title={`${t('lms.quiz.results')}: ${quiz.title}`}
            subtitle={`${t('lms.quiz.releaseMode')}: ${enumLabel(t, 'lms.release', quiz.release_mode)} · ${quiz.released ? t('lms.quiz.released') : t('lms.quiz.notReleased')}`}
            course={course}
            author={author}
            actions={
                <>
                    {quiz.can_release ? (
                        <button type="button" className="btn-navy" onClick={() => window.confirm(t('lms.quiz.confirmRelease')) && router.post(route('lms.quizzes.release', quiz.id), {}, { preserveScroll: true })}>
                            {t('lms.quiz.releaseNow')}
                        </button>
                    ) : null}
                    <Link href={quiz.edit_url} className="btn-outline">
                        {t('lms.common.edit')}
                    </Link>
                </>
            }
        >
            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('lms.common.student')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.assignment.attempt')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.assignment.submittedAt')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.common.status')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.common.score')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {attempts.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                    {t('lms.quiz.noAttemptsYet')}
                                </td>
                            </tr>
                        ) : null}
                        {attempts.map((a) => (
                            <tr key={a.id}>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-navy">{a.name}</p>
                                    <p className="text-xs text-slate-500">{a.number}</p>
                                </td>
                                <td className="px-4 py-3">{a.attempt_no}</td>
                                <td className="px-4 py-3">
                                    {fmt(a.submitted_at)}
                                    {a.timed_out ? <span className="ms-2 text-xs font-semibold text-red-700">{t('lms.quiz.timedOut')}</span> : null}
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={a.grading_status === 'needs_grading' ? 'pending' : 'graded'} label={enumLabel(t, 'lms.grading', a.grading_status)} />
                                </td>
                                <td className="px-4 py-3">
                                    {num(a.score)} / {num(a.max_score)}
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <Link href={a.url} className="btn-outline">
                                        {a.grading_status === 'needs_grading' ? t('lms.assignment.mark') : t('lms.common.open')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </LmsLayout>
    );
}
