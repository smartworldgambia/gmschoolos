import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Field, Input, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmt, fmtBytes, num } from '@/lib/lms';
import { Link, useForm } from '@inertiajs/react';

export default function SubmissionGrade({ course, author, assignment, student, submission, history = [] }) {
    const { t } = useLocale();
    const rubric = assignment.criteria ?? [];
    const returned = submission.status === 'returned';
    const grade = useForm({
        score: submission.score ?? '',
        rubric_scores: Object.fromEntries(rubric.map((_, i) => [i, submission.rubric_scores?.[i] ?? ''])),
        feedback: submission.feedback ?? '',
    });
    const back = useForm({ feedback: submission.feedback ?? '' });
    const total = rubric.reduce((s, _, i) => s + (Number(grade.data.rubric_scores[i]) || 0), 0);
    const canReturn = history.length < assignment.max_attempts;

    return (
        <LmsLayout
            title={`${assignment.title} — ${student.name}`}
            subtitle={student.number}
            course={course}
            author={author}
            actions={
                <Link href={assignment.url} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <div className="space-y-6">
                    <Card
                        title={`${t('lms.assignment.attempt')} ${submission.attempt_no}`}
                        actions={
                            <>
                                <StatusBadge status={submission.status} label={enumLabel(t, 'lms.status', submission.status)} />
                                {submission.is_late ? <StatusBadge status="overdue" label={t('lms.assignment.late')} /> : null}
                            </>
                        }
                    >
                        <p className="mb-2 text-xs text-slate-500">
                            {t('lms.assignment.submittedAt')}: {fmt(submission.submitted_at)} · {t('lms.assignment.due')}: {fmt(assignment.due_at)}
                        </p>
                        {submission.body ? <p className="whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm">{submission.body}</p> : <p className="text-sm text-slate-500">{t('lms.assignment.noText')}</p>}
                        {submission.files.length ? (
                            <ul className="mt-3 space-y-1 text-sm">
                                {submission.files.map((f) => (
                                    <li key={f.id}>
                                        <a href={f.file_url} target="_blank" rel="noreferrer noopener" className="text-teal hover:underline">
                                            {f.name}
                                        </a>{' '}
                                        <span className="text-xs text-slate-500">{fmtBytes(f.size)}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </Card>

                    {history.length > 1 ? (
                        <Card title={t('lms.assignment.history')}>
                            <ul className="flex flex-wrap gap-2">
                                {history.map((h) => (
                                    <li key={h.id}>
                                        <Link href={h.url} className={h.current ? 'btn-navy' : 'btn-outline'}>
                                            {t('lms.assignment.attempt')} {h.attempt_no} · {enumLabel(t, 'lms.status', h.status)}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    ) : null}
                </div>

                <div className="space-y-6">
                    {returned ? (
                        <Card title={t('lms.assignment.returnedTitle')}>
                            <p className="text-sm text-slate-600">{t('lms.assignment.returnedHelp')}</p>
                            {submission.feedback ? <p className="mt-2 whitespace-pre-wrap text-sm">{submission.feedback}</p> : null}
                        </Card>
                    ) : (
                        <Card title={t('lms.assignment.gradeTitle')}>
                            <form
                                className="space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    grade.post(route('lms.submissions.grade', submission.id), { preserveScroll: true });
                                }}
                            >
                                <ErrorSummary errors={grade.errors} />
                                {rubric.length ? (
                                    <>
                                        {rubric.map((c, i) => (
                                            <Field key={i} label={`${c.label} (0–${c.max_points})`}>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max={c.max_points}
                                                    step="0.5"
                                                    value={grade.data.rubric_scores[i]}
                                                    onChange={(e) => grade.setData('rubric_scores', { ...grade.data.rubric_scores, [i]: e.target.value })}
                                                    required
                                                />
                                            </Field>
                                        ))}
                                        <p className="text-sm font-semibold text-navy">
                                            {t('lms.common.score')}: {num(total)} / {num(assignment.max_score)}
                                        </p>
                                    </>
                                ) : (
                                    <Field label={`${t('lms.common.score')} (0–${num(assignment.max_score)})`}>
                                        <Input type="number" min="0" max={assignment.max_score} step="0.5" value={grade.data.score} onChange={(e) => grade.setData('score', e.target.value)} required />
                                    </Field>
                                )}
                                <Field label={t('lms.assignment.feedback')}>
                                    <Textarea rows={5} value={grade.data.feedback} onChange={(e) => grade.setData('feedback', e.target.value)} maxLength={5000} />
                                </Field>
                                <button type="submit" className="btn-teal" disabled={grade.processing}>
                                    {t('lms.assignment.saveGrade')}
                                </button>
                            </form>
                        </Card>
                    )}

                    {!returned && canReturn ? (
                        <Card title={t('lms.assignment.requestRevision')}>
                            <p className="mb-3 text-sm text-slate-600">{t('lms.assignment.requestRevisionHelp')}</p>
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    back.post(route('lms.submissions.return', submission.id), { preserveScroll: true });
                                }}
                            >
                                <ErrorSummary errors={back.errors} />
                                <Textarea rows={3} value={back.data.feedback} onChange={(e) => back.setData('feedback', e.target.value)} required maxLength={5000} placeholder={t('lms.assignment.revisionNote')} />
                                <button type="submit" className="btn-outline" disabled={back.processing}>
                                    {t('lms.assignment.returnButton')}
                                </button>
                            </form>
                        </Card>
                    ) : null}
                </div>
            </div>
        </LmsLayout>
    );
}
