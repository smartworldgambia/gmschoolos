import LmsLayout from '@/Components/Lms/LmsLayout';
import WorkStatus from '@/Components/Lms/WorkStatus';
import InputError from '@/Components/InputError';
import { Card, ErrorSummary, Field, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, messageText } from '@/lib/academic';
import { fmt, fmtBytes, num } from '@/lib/lms';
import { useLmsTone } from '@/lib/lmsTone';
import { Link, useForm } from '@inertiajs/react';

function SubmitBox({ assignment, maxFiles, fileMaxKb, block, submitLabel }) {
    const { t } = useLocale();
    const form = useForm({ body: '', files: [] });

    if (block) {
        return <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{messageText(t, block)}</p>;
    }

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('lms.assignments.submit', assignment.id), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <ErrorSummary errors={form.errors} />
            {assignment.accepts_text ? (
                <Field label={t('lms.assignment.yourAnswer')}>
                    <Textarea rows={8} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} maxLength={50000} />
                </Field>
            ) : null}
            {assignment.accepts_files ? (
                <Field label={t('lms.assignment.yourFiles')} hint={`${t('lms.assignment.filesHint')} ${maxFiles} · ${Math.round(fileMaxKb / 1024)} MB`}>
                    <input type="file" multiple className="text-sm" onChange={(e) => form.setData('files', Array.from(e.target.files).slice(0, maxFiles))} />
                    <InputError message={messageText(t, form.errors['files.0'])} />
                </Field>
            ) : null}
            <button type="submit" className="btn-teal" disabled={form.processing}>
                {submitLabel || t('lms.assignment.submit')}
            </button>
        </form>
    );
}

export default function AssignmentShow({ course, author, assignment, attachments = [], roster = [], mine = null, block = null, fileMaxKb = 10240, maxFiles = 5 }) {
    const { t } = useLocale();
    const tone = useLmsTone();
    // Hint only; the server decides what is late (it knows the school's time zone).
    const overdue = !!assignment.due_at && new Date(String(assignment.due_at).replace(' ', 'T')) < new Date();
    const latest = Array.isArray(mine) && mine.length ? mine[mine.length - 1] : null;
    const workState = author
        ? null
        : latest?.status === 'graded'
          ? 'graded'
          : latest?.status === 'returned'
            ? 'returned'
            : latest?.status === 'submitted'
              ? 'submitted'
              : overdue
                ? 'overdue'
                : 'upcoming';

    return (
        <LmsLayout
            title={assignment.title}
            subtitle={`${t('lms.assignment.due')}: ${fmt(assignment.due_at)} · ${num(assignment.max_score)} ${t('lms.q.pointsShort')}`}
            course={course}
            author={author}
            actions={
                assignment.edit_url ? (
                    <Link href={assignment.edit_url} className="btn-outline">
                        {t('lms.common.edit')}
                    </Link>
                ) : null
            }
        >
            {workState ? (
                <div className="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-surface-muted bg-white px-4 py-3 shadow-soft">
                    <WorkStatus state={workState} t={t} />
                    <p className="text-sm text-slate-600">
                        {t('lms.assignment.dueAt')}: {fmt(assignment.due_at)}
                    </p>
                </div>
            ) : null}

            <div className="mb-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card title={tone.nursery ? t('lms.tone.nursery.instructions') : t('lms.assignment.instructions')}>
                    {assignment.instructions ? <p className="whitespace-pre-wrap text-sm text-slate-800">{assignment.instructions}</p> : <p className="text-sm text-slate-500">{t('lms.assignment.noInstructions')}</p>}
                    {attachments.length ? (
                        <>
                            <h3 className="mt-4 text-sm font-semibold text-navy">{t('lms.assignment.attachments')}</h3>
                            <ul className="mt-1 space-y-1 text-sm">
                                {attachments.map((f) => (
                                    <li key={f.id}>
                                        <a href={f.file_url} target="_blank" rel="noreferrer noopener" className="text-teal hover:underline">
                                            {f.title}
                                        </a>{' '}
                                        <span className="text-xs text-slate-500">{fmtBytes(f.size)}</span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : null}
                </Card>
                <Card title={t('lms.assignment.rules')}>
                    <dl className="space-y-1 text-sm text-slate-700">
                        <div className="flex justify-between gap-2">
                            <dt>{t('lms.assignment.dueAt')}</dt>
                            <dd>{fmt(assignment.due_at)}</dd>
                        </div>
                        <div className="flex justify-between gap-2">
                            <dt>{t('lms.assignment.closesAt')}</dt>
                            <dd>{fmt(assignment.closes_at)}</dd>
                        </div>
                        <div className="flex justify-between gap-2">
                            <dt>{t('lms.assignment.latePolicy')}</dt>
                            <dd>{enumLabel(t, 'lms.latePolicy', assignment.late_policy)}</dd>
                        </div>
                        <div className="flex justify-between gap-2">
                            <dt>{t('lms.assignment.maxAttempts')}</dt>
                            <dd>{assignment.max_attempts}</dd>
                        </div>
                        {author ? (
                            <div className="flex justify-between gap-2">
                                <dt>{t('lms.common.status')}</dt>
                                <dd>
                                    <StatusBadge status={assignment.status} label={enumLabel(t, 'lms.status', assignment.status)} />
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                    {assignment.criteria?.length ? (
                        <>
                            <h3 className="mt-4 text-sm font-semibold text-navy">{assignment.rubric_title || t('lms.assignment.rubric')}</h3>
                            <ul className="mt-1 space-y-1 text-sm text-slate-700">
                                {assignment.criteria.map((c, i) => (
                                    <li key={i} className="flex justify-between gap-2">
                                        <span>{c.label}</span>
                                        <span>{c.max_points}</span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    ) : null}
                </Card>
            </div>

            {author ? (
                <Card title={t('lms.assignment.submissions')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-2 py-2 text-start">{t('lms.common.student')}</th>
                                    <th className="px-2 py-2 text-start">{t('lms.common.class')}</th>
                                    <th className="px-2 py-2 text-start">{t('lms.common.status')}</th>
                                    <th className="px-2 py-2 text-start">{t('lms.assignment.submittedAt')}</th>
                                    <th className="px-2 py-2 text-start">{t('lms.common.score')}</th>
                                    <th className="px-2 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {roster.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-2 py-6 text-center text-slate-500">
                                            {t('lms.people.none')}
                                        </td>
                                    </tr>
                                ) : null}
                                {roster.map((r) => (
                                    <tr key={r.student_id}>
                                        <td className="px-2 py-2 font-medium text-navy">{r.name}</td>
                                        <td className="px-2 py-2">{r.class ?? '—'}</td>
                                        <td className="px-2 py-2">
                                            <StatusBadge status={r.status} label={enumLabel(t, 'lms.status', r.status)} />
                                            {r.is_late ? <span className="ms-2 text-xs font-semibold text-red-700">{t('lms.assignment.late')}</span> : null}
                                        </td>
                                        <td className="px-2 py-2">{fmt(r.submitted_at)}</td>
                                        <td className="px-2 py-2">{r.score !== null ? `${num(r.score)} / ${num(assignment.max_score)}` : '—'}</td>
                                        <td className="px-2 py-2 text-end">
                                            {r.url ? (
                                                <Link href={r.url} className="btn-outline">
                                                    {r.status === 'submitted' ? t('lms.assignment.mark') : t('lms.common.open')}
                                                </Link>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            ) : (
                <div className="space-y-6">
                    {(mine ?? []).map((s) => (
                        <Card
                            key={s.id}
                            title={`${t('lms.assignment.attempt')} ${s.attempt_no}`}
                            actions={
                                <>
                                    <StatusBadge status={s.status} label={enumLabel(t, 'lms.status', s.status)} />
                                    {s.is_late ? <StatusBadge status="overdue" label={t('lms.assignment.late')} /> : null}
                                </>
                            }
                        >
                            <p className="mb-2 text-xs text-slate-500">
                                {t('lms.assignment.submittedAt')}: {fmt(s.submitted_at)}
                            </p>
                            {s.body ? <p className="whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm">{s.body}</p> : null}
                            {s.files.length ? (
                                <ul className="mt-2 space-y-1 text-sm">
                                    {s.files.map((f) => (
                                        <li key={f.id}>
                                            <a href={f.file_url} target="_blank" rel="noreferrer noopener" className="text-teal hover:underline">
                                                {f.name}
                                            </a>{' '}
                                            <span className="text-xs text-slate-500">{fmtBytes(f.size)}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            {s.score !== null ? (
                                <p className="mt-3 text-sm font-semibold text-navy">
                                    {t('lms.common.score')}: {num(s.score)} / {num(assignment.max_score)}
                                </p>
                            ) : null}
                            {s.rubric_scores && assignment.criteria?.length ? (
                                <ul className="mt-1 text-sm text-slate-700">
                                    {assignment.criteria.map((c, i) => (
                                        <li key={i}>
                                            {c.label}: {num(s.rubric_scores[i])} / {c.max_points}
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            {s.feedback ? (
                                <div className="mt-3 rounded-lg border border-teal/30 bg-teal/5 p-3 text-sm">
                                    <p className="text-xs font-semibold text-slate-500">{t('lms.assignment.feedback')}</p>
                                    <p className="whitespace-pre-wrap">{s.feedback}</p>
                                </div>
                            ) : null}
                        </Card>
                    ))}
                    <Card title={mine && mine.length ? t('lms.assignment.resubmit') : t('lms.assignment.yourSubmission')}>
                        {overdue && !block && assignment.late_policy === 'allow' ? <p className="mb-3 text-sm text-amber-700">{t('lms.assignment.lateWarning')}</p> : null}
                        <SubmitBox assignment={assignment} maxFiles={maxFiles} fileMaxKb={fileMaxKb} block={block} submitLabel={tone.nursery ? t('lms.tone.nursery.submit') : null} />
                    </Card>
                </div>
            )}
        </LmsLayout>
    );
}
