import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Input, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmt, num } from '@/lib/lms';
import { Link, useForm } from '@inertiajs/react';

export default function QuizGrade({ course, author, quiz, student, attempt, items = [] }) {
    const { t } = useLocale();
    const form = useForm({
        points: Object.fromEntries(items.map((q) => [q.id, q.points_awarded ?? ''])),
        feedback: Object.fromEntries(items.map((q) => [q.id, q.feedback ?? ''])),
    });
    const total = items.reduce((s, q) => s + (Number(form.data.points[q.id]) || 0), 0);

    return (
        <LmsLayout
            title={`${quiz.title} — ${student.name}`}
            subtitle={`${student.number} · ${t('lms.assignment.attempt')} ${attempt.attempt_no} · ${fmt(attempt.submitted_at)}`}
            course={course}
            author={author}
            actions={
                <Link href={quiz.url} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('lms.attempts.grade.store', attempt.id), { preserveScroll: true });
                }}
            >
                <ErrorSummary errors={form.errors} />
                <p className="text-sm text-slate-600">
                    <StatusBadge status={attempt.grading_status === 'needs_grading' ? 'pending' : 'graded'} label={enumLabel(t, 'lms.grading', attempt.grading_status)} />{' '}
                    {attempt.timed_out ? <span className="ms-2 font-semibold text-red-700">{t('lms.quiz.timedOut')}</span> : null}
                </p>
                {items.map((q, i) => (
                    <Card key={q.id}>
                        <p className="text-xs text-slate-500">
                            {i + 1}. {enumLabel(t, 'lms.qtype', q.type)} · {q.points} {t('lms.q.pointsShort')}
                            {q.needs_grading ? <span className="ms-2 font-semibold text-amber-700">{t('lms.quiz.needsMarking')}</span> : null}
                        </p>
                        <p className="mt-1 whitespace-pre-wrap text-sm font-medium text-navy">{q.prompt}</p>
                        <div className="mt-3 grid gap-3 lg:grid-cols-2">
                            <div>
                                <p className="text-xs font-semibold text-slate-500">{t('lms.quiz.studentAnswer')}</p>
                                <p className={`mt-1 whitespace-pre-wrap rounded-lg p-3 text-sm ${q.answer ? 'bg-slate-50' : 'bg-slate-50 text-slate-400'}`}>{q.answer || t('lms.quiz.noAnswer')}</p>
                            </div>
                            {q.type !== 'essay' ? (
                                <div>
                                    <p className="text-xs font-semibold text-slate-500">{t('lms.quiz.expected')}</p>
                                    <p className="mt-1 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                                        {q.type === 'short_answer' ? (q.accepted_answers ?? []).join(' / ') : q.type === 'true_false' ? (q.correct_answer === 'true' ? t('lms.q.true') : t('lms.q.false')) : q.correct_answer}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">{q.is_correct ? t('lms.quiz.autoCorrect') : t('lms.quiz.autoWrong')}</p>
                                </div>
                            ) : null}
                        </div>
                        <div className="mt-3 grid gap-3 sm:grid-cols-[10rem_1fr]">
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium text-slate-700">{`${t('lms.common.score')} (0–${q.points})`}</span>
                                <Input
                                    type="number"
                                    min="0"
                                    max={q.points}
                                    step="0.5"
                                    value={form.data.points[q.id]}
                                    onChange={(e) => form.setData('points', { ...form.data.points, [q.id]: e.target.value })}
                                    required
                                />
                            </label>
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium text-slate-700">{t('lms.assignment.feedback')}</span>
                                <Textarea rows={2} value={form.data.feedback[q.id]} maxLength={2000} onChange={(e) => form.setData('feedback', { ...form.data.feedback, [q.id]: e.target.value })} />
                            </label>
                        </div>
                    </Card>
                ))}
                <div className="flex flex-wrap items-center gap-4">
                    <button type="submit" className="btn-teal" disabled={form.processing}>
                        {t('lms.assignment.saveGrade')}
                    </button>
                    <p className="text-sm font-semibold text-navy">
                        {t('lms.common.score')}: {num(total)} / {num(attempt.max_score)}
                    </p>
                </div>
            </form>
        </LmsLayout>
    );
}
