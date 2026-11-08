import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, Textarea } from '@/Components/SchoolOS/Form';
import { useLmsTone } from '@/lib/lmsTone';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { num } from '@/lib/lms';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

function Cell({ cell, col, t }) {
    if (!cell || cell.state === 'missing') {
        return <span className="text-slate-400">—</span>;
    }
    if (cell.state === 'withheld') {
        return <StatusBadge status="scheduled" label={t('lms.quiz.resultsWithheld')} />;
    }
    if (cell.state === 'pending' && cell.score === null) {
        return <StatusBadge status="pending" label={t('lms.quiz.awaitingMarking')} />;
    }
    if (cell.state === 'submitted') {
        return <StatusBadge status="submitted" label={enumLabel(t, 'lms.status', 'submitted')} />;
    }
    if (cell.score === null || cell.score === undefined) {
        return <span className="text-slate-400">—</span>;
    }

    return (
        <span className={cell.late ? 'text-red-700' : 'text-navy'}>
            <span className="font-semibold">{num(cell.score)}</span>
            <span className="text-xs text-slate-500"> / {num(cell.max)}</span>
            {col.type === 'quiz' && cell.percent !== null ? <span className="ms-1 text-xs text-slate-500">({num(cell.percent)}%)</span> : null}
            {cell.pending ? <span className="ms-1 text-xs text-amber-700">*</span> : null}
        </span>
    );
}

function FeedbackRow({ course, row, colSpan, onDone }) {
    const { t } = useLocale();
    const [body, setBody] = useState(row.feedback ?? '');
    const [busy, setBusy] = useState(false);

    return (
        <tr className="bg-slate-50">
            <td colSpan={colSpan} className="px-4 py-3">
                <p className="mb-1 text-xs font-semibold text-slate-500">
                    {t('lms.grades.feedbackFor')} {row.student.name}
                </p>
                <Textarea rows={3} value={body} maxLength={3000} onChange={(e) => setBody(e.target.value)} />
                <div className="mt-2 flex gap-2">
                    <button
                        type="button"
                        className="btn-teal"
                        disabled={busy}
                        onClick={() =>
                            router.post(route('lms.courses.gradebook.feedback', [course.id, row.student.id]), { body }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: onDone })
                        }
                    >
                        {t('lms.common.save')}
                    </button>
                    <button type="button" className="btn-outline" onClick={onDone}>
                        {t('lms.common.cancel')}
                    </button>
                </div>
            </td>
        </tr>
    );
}

export default function Gradebook({ course, author, columns = [], rows = [], pending = null, exportUrl = null }) {
    const { t } = useLocale();
    const tone = useLmsTone();
    const [editing, setEditing] = useState(null);
    const span = columns.length + 4 + (author ? 1 : 0);
    const hasExams = rows.some((r) => r.exams?.length);

    return (
        <LmsLayout
            title={author ? t('lms.grades.titleStaff') : tone.nursery ? t('lms.tone.nursery.grades') : t('lms.grades.titleLearner')}
            subtitle={tone.nursery ? t('lms.tone.nursery.gradesHelp') : t('lms.grades.subtitle')}
            course={course}
            author={author}
            active="gradebook"
            actions={
                exportUrl ? (
                    <a href={exportUrl} className="btn-outline">
                        {t('lms.grades.export')}
                    </a>
                ) : null
            }
        >
            {pending && (pending.submissions > 0 || pending.attempts > 0) ? (
                <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {t('lms.grades.toMark')}: {pending.submissions} {t('lms.grades.submissions')} · {pending.attempts} {t('lms.grades.attempts')}
                </p>
            ) : null}

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            {author ? <th className="px-4 py-3 text-start">{t('lms.common.student')}</th> : null}
                            {columns.map((c) => (
                                <th key={c.key} className="px-4 py-3 text-start">
                                    <Link href={c.url} className="hover:underline">
                                        {c.title}
                                    </Link>
                                    <span className="block text-[10px] normal-case text-slate-400">{enumLabel(t, 'lms.gradeType', c.type)}</span>
                                </th>
                            ))}
                            <th className="px-4 py-3 text-start">{t('lms.grades.coursePercent')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.grades.lessons')}</th>
                            <th className="px-4 py-3 text-start">{t('lms.grades.feedback')}</th>
                            {author ? <th className="px-4 py-3" /> : null}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={span} className="px-4 py-8 text-center text-slate-500">
                                    {t('lms.people.none')}
                                </td>
                            </tr>
                        ) : null}
                        {rows.flatMap((r) => {
                            const main = (
                                <tr key={r.student.id}>
                                    {author ? (
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-navy">{r.student.name}</p>
                                            <p className="text-xs text-slate-500">
                                                {r.student.number}
                                                {r.student.class ? ` · ${r.student.class}` : ''}
                                            </p>
                                        </td>
                                    ) : null}
                                    {columns.map((c) => (
                                        <td key={c.key} className="px-4 py-3">
                                            <Cell cell={r.cells[c.key]} col={c} t={t} />
                                        </td>
                                    ))}
                                    <td className="px-4 py-3 font-semibold text-navy">{r.percent !== null ? `${num(r.percent)}%` : '—'}</td>
                                    <td className="px-4 py-3">{r.progress !== null ? `${r.progress}%` : '—'}</td>
                                    <td className="max-w-xs px-4 py-3 text-xs text-slate-600">{r.feedback || '—'}</td>
                                    {author ? (
                                        <td className="px-4 py-3 text-end">
                                            <button type="button" className="btn-outline" onClick={() => setEditing(editing === r.student.id ? null : r.student.id)}>
                                                {t('lms.grades.writeFeedback')}
                                            </button>
                                        </td>
                                    ) : null}
                                </tr>
                            );

                            return editing === r.student.id ? [main, <FeedbackRow key={`f${r.student.id}`} course={course} row={r} colSpan={span} onDone={() => setEditing(null)} />] : [main];
                        })}
                    </tbody>
                </table>
            </div>
            <p className="mt-3 text-xs text-slate-500">{t('lms.grades.note')}</p>

            {hasExams ? (
                <Card title={t('lms.grades.exams')} className="mt-6">
                    <p className="mb-3 text-xs text-slate-500">{t('lms.grades.examsNote')}</p>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {rows
                            .filter((r) => r.exams?.length)
                            .map((r) => (
                                <li key={r.student.id} className="py-2">
                                    {author ? <p className="font-medium text-navy">{r.student.name}</p> : null}
                                    <ul className="text-slate-700">
                                        {r.exams.map((e, i) => (
                                            <li key={i}>
                                                {e.exam ?? '—'} {e.term ? `(${e.term})` : ''}: {e.total !== null ? num(e.total) : '—'} {e.letter ? `· ${e.letter}` : ''}
                                            </li>
                                        ))}
                                    </ul>
                                </li>
                            ))}
                    </ul>
                </Card>
            ) : null}
        </LmsLayout>
    );
}
