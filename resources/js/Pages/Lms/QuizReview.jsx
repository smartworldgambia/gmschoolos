import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmt, num } from '@/lib/lms';
import { Link } from '@inertiajs/react';

export default function QuizReview({ course, author, quiz, attempt, marksVisible, answersVisible, items = [] }) {
    const { t } = useLocale();

    return (
        <LmsLayout
            title={quiz.title}
            subtitle={`${t('lms.assignment.attempt')} ${attempt.attempt_no} · ${fmt(attempt.submitted_at)}`}
            course={course}
            author={author}
            showStatus={false}
            actions={
                <Link href={quiz.url} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            <div className="mb-6 rounded-xl bg-white p-5 shadow-sm">
                {marksVisible ? (
                    <p className="text-2xl font-bold text-navy">
                        {num(attempt.score)} / {num(attempt.max_score)}
                    </p>
                ) : attempt.awaiting_marking ? (
                    <StatusBadge status="pending" label={t('lms.quiz.awaitingMarking')} />
                ) : (
                    <StatusBadge status="scheduled" label={t('lms.quiz.resultsWithheld')} />
                )}
                {!marksVisible ? <p className="mt-2 text-sm text-slate-600">{t('lms.quiz.withheldHelp')}</p> : null}
                {marksVisible && !answersVisible ? <p className="mt-2 text-sm text-slate-600">{t('lms.quiz.answersLaterHelp')}</p> : null}
                {attempt.timed_out ? <p className="mt-2 text-sm font-semibold text-red-700">{t('lms.quiz.timedOut')}</p> : null}
            </div>

            <ol className="space-y-4">
                {items.map((q, i) => (
                    <li key={q.id}>
                        <Card>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <p className="text-xs text-slate-500">
                                    {i + 1}. {enumLabel(t, 'lms.qtype', q.type)} · {q.points} {t('lms.q.pointsShort')}
                                </p>
                                {marksVisible && q.points_awarded !== null ? (
                                    <StatusBadge status={q.is_correct ? 'passed' : q.points_awarded > 0 ? 'partial' : 'failed'} label={`${num(q.points_awarded)} / ${q.points}`} />
                                ) : null}
                            </div>
                            <p className="mt-1 whitespace-pre-wrap text-sm font-medium text-navy">{q.prompt}</p>
                            <p className="mt-3 text-xs font-semibold text-slate-500">{t('lms.quiz.yourAnswer')}</p>
                            <p className="mt-1 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm">{q.answer || t('lms.quiz.noAnswer')}</p>
                            {answersVisible && q.correct_answer ? (
                                <>
                                    <p className="mt-3 text-xs font-semibold text-slate-500">{t('lms.quiz.expected')}</p>
                                    <p className="mt-1 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                                        {q.type === 'true_false' ? (q.correct_answer === 'true' ? t('lms.q.true') : t('lms.q.false')) : q.correct_answer}
                                    </p>
                                </>
                            ) : null}
                            {answersVisible && q.explanation ? <p className="mt-2 text-sm text-slate-700">{q.explanation}</p> : null}
                            {q.feedback ? (
                                <div className="mt-3 rounded-lg border border-teal/30 bg-teal/5 p-3 text-sm">
                                    <p className="text-xs font-semibold text-slate-500">{t('lms.assignment.feedback')}</p>
                                    <p className="whitespace-pre-wrap">{q.feedback}</p>
                                </div>
                            ) : null}
                        </Card>
                    </li>
                ))}
            </ol>
        </LmsLayout>
    );
}
