import LmsLayout from '@/Components/Lms/LmsLayout';
import QuestionSummary from '@/Components/Lms/QuestionSummary';
import WorkStatus from '@/Components/Lms/WorkStatus';
import { Card } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, messageText } from '@/lib/academic';
import { fmt, num } from '@/lib/lms';
import { Link, router } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useRef, useState } from 'react';

function Rules({ quiz }) {
    const { t } = useLocale();
    const rows = [
        [t('lms.quiz.timeLimit'), quiz.time_limit_minutes ? `${quiz.time_limit_minutes} ${t('lms.common.minutesShort')}` : t('lms.quiz.noLimit')],
        [t('lms.quiz.maxAttempts'), quiz.max_attempts],
        [t('lms.quiz.opens'), fmt(quiz.opens_at)],
        [t('lms.quiz.closes'), fmt(quiz.closes_at)],
        [t('lms.quiz.maxScore'), num(quiz.max_score)],
        [t('lms.quiz.gradeMethod'), enumLabel(t, 'lms.method', quiz.grade_method)],
    ];

    return (
        <dl className="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
            {rows.map(([k, v]) => (
                <div key={k} className="flex justify-between gap-2 border-b border-slate-100 py-1">
                    <dt className="text-slate-500">{k}</dt>
                    <dd className="font-medium text-navy">{v}</dd>
                </div>
            ))}
        </dl>
    );
}

function Preview({ quiz, questions, pulls, course, author }) {
    const { t } = useLocale();

    return (
        <LmsLayout
            title={quiz.title}
            subtitle={t('lms.quiz.previewSubtitle')}
            course={course}
            author={author}
            actions={
                <>
                    <Link href={quiz.edit_url} className="btn-outline">
                        {t('lms.common.edit')}
                    </Link>
                    <Link href={quiz.attempts_url} className="btn-teal">
                        {t('lms.quiz.results')}
                    </Link>
                </>
            }
        >
            <Card title={t('lms.quiz.settings')} className="mb-6">
                {quiz.description ? <p className="mb-3 whitespace-pre-wrap text-sm text-slate-700">{quiz.description}</p> : null}
                <div className="mb-3 flex flex-wrap gap-2">
                    <StatusBadge status={quiz.status} label={enumLabel(t, 'lms.status', quiz.status)} />
                    <span className="text-xs text-slate-500">{t('lms.quiz.releaseMode')}: {enumLabel(t, 'lms.release', quiz.release_mode)}</span>
                </div>
                <Rules quiz={quiz} />
            </Card>
            {pulls.length ? (
                <Card title={t('lms.quiz.randomTitle')} className="mb-6">
                    <ul className="text-sm">
                        {pulls.map((p, i) => (
                            <li key={i}>
                                {p.bank} · {t('lms.quiz.drawCount')}: {p.count}
                            </li>
                        ))}
                    </ul>
                </Card>
            ) : null}
            <Card title={`${t('lms.quiz.questions')} (${questions.length})`}>
                {questions.length === 0 ? <p className="text-sm text-slate-500">{t('lms.quiz.noQuestions')}</p> : null}
                <ol className="space-y-4">
                    {questions.map((q, i) => (
                        <li key={q.id} className="flex">
                            <QuestionSummary q={q} index={i} />
                        </li>
                    ))}
                </ol>
            </Card>
        </LmsLayout>
    );
}

function Intro({ quiz, attempts, result, block, startUrl, course, author }) {
    const { t } = useLocale();
    const closing = quiz.closes_at && new Date(String(quiz.closes_at).replace(' ', 'T')) < new Date();
    const workState = attempts.some((a) => a.score !== null)
        ? 'graded'
        : attempts.length
          ? 'submitted'
          : closing
            ? 'overdue'
            : 'upcoming';

    return (
        <LmsLayout title={quiz.title} subtitle={course.title} course={course} author={author} showStatus={false}>
            <div className="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-surface-muted bg-white px-4 py-3 shadow-soft">
                <WorkStatus state={workState} t={t} />
                {quiz.closes_at ? (
                    <p className="text-sm text-slate-600">
                        {t('lms.quiz.closes')}: {fmt(quiz.closes_at)}
                    </p>
                ) : null}
            </div>
            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="space-y-6">
                    <Card title={t('lms.quiz.about')}>
                        {quiz.description ? <p className="mb-3 whitespace-pre-wrap text-sm text-slate-700">{quiz.description}</p> : null}
                        <Rules quiz={quiz} />
                    </Card>
                    <Card title={t('lms.quiz.yourAttempts')}>
                        {attempts.length === 0 ? <p className="text-sm text-slate-500">{t('lms.quiz.noAttempts')}</p> : null}
                        <ul className="divide-y divide-slate-100">
                            {attempts.map((a) => (
                                <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                    <div>
                                        <p className="font-medium text-navy">
                                            {t('lms.assignment.attempt')} {a.attempt_no}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {fmt(a.submitted_at)}
                                            {a.timed_out ? ` · ${t('lms.quiz.timedOut')}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {a.awaiting_marking ? <StatusBadge status="pending" label={t('lms.quiz.awaitingMarking')} /> : null}
                                        {a.withheld ? <StatusBadge status="scheduled" label={t('lms.quiz.resultsWithheld')} /> : null}
                                        {a.score !== null ? (
                                            <span className="font-semibold text-navy">
                                                {num(a.score)} / {num(a.max_score)}
                                            </span>
                                        ) : null}
                                        {a.review_url ? (
                                            <Link href={a.review_url} className="btn-outline">
                                                {t('lms.quiz.review')}
                                            </Link>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
                <div className="space-y-6">
                    {result && result.percent !== null ? (
                        <Card title={t('lms.quiz.yourResult')}>
                            <p className="text-3xl font-bold text-navy">{num(result.percent)}%</p>
                            <p className="text-sm text-slate-600">
                                {num(result.score)} / {num(result.max)} · {enumLabel(t, 'lms.method', quiz.grade_method)}
                            </p>
                        </Card>
                    ) : null}
                    <Card title={t('lms.quiz.start')}>
                        {block ? (
                            <p className="text-sm text-slate-600">{messageText(t, block)}</p>
                        ) : (
                            <>
                                <p className="mb-3 text-sm text-slate-600">{quiz.time_limit_minutes ? t('lms.quiz.startTimed') : t('lms.quiz.startUntimed')}</p>
                                <button type="button" className="btn-teal" onClick={() => router.post(startUrl)}>
                                    {attempts.some((a) => a.status === 'submitted') ? t('lms.quiz.startAgain') : t('lms.quiz.startButton')}
                                </button>
                            </>
                        )}
                    </Card>
                </div>
            </div>
        </LmsLayout>
    );
}

function clock(total) {
    const s = Math.max(0, Math.floor(Number(total) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n) => String(n).padStart(2, '0');

    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
}

function Taking({ quiz, attempt, questions, answers: saved, course, author }) {
    const { t } = useLocale();
    const [answers, setAnswers] = useState(() => Object.fromEntries(Object.entries(saved ?? {}).map(([k, v]) => [k, v ?? ''])));
    const [state, setState] = useState('saved'); // saved | dirty | saving | error
    const [left, setLeft] = useState(attempt.seconds_left);
    const deadline = useRef(attempt.seconds_left === null ? null : Date.now() + attempt.seconds_left * 1000);
    const latest = useRef(answers);
    const submitted = useRef(false);
    latest.current = answers;

    const submit = useCallback(() => {
        if (submitted.current) {
            return;
        }
        submitted.current = true;
        router.post(attempt.submit_url, { answers: latest.current });
    }, [attempt.submit_url]);

    const save = useCallback(async () => {
        setState('saving');
        try {
            await axios.post(attempt.save_url, { answers: latest.current });
            setState('saved');
        } catch (e) {
            if (e?.response?.status === 409) {
                submitted.current = true;
                router.reload();

                return;
            }
            setState('error');
        }
    }, [attempt.save_url]);

    // autosave shortly after the last change
    useEffect(() => {
        if (state !== 'dirty') {
            return undefined;
        }
        const id = setTimeout(save, 1200);

        return () => clearTimeout(id);
    }, [answers, state, save]);

    // countdown driven by the server's deadline; the server enforces it regardless of this clock
    useEffect(() => {
        if (deadline.current === null) {
            return undefined;
        }
        const id = setInterval(() => {
            const remaining = Math.round((deadline.current - Date.now()) / 1000);
            setLeft(remaining);
            if (remaining <= 0 && !submitted.current) {
                clearInterval(id);
                submit();
            }
        }, 1000);

        return () => clearInterval(id);
    }, [submit]);

    function set(id, value) {
        setAnswers((a) => ({ ...a, [id]: value }));
        setState('dirty');
    }

    const answered = questions.filter((q) => (answers[q.id] ?? '').trim() !== '').length;
    const low = left !== null && left <= 60;

    return (
        <LmsLayout title={quiz.title} subtitle={`${t('lms.assignment.attempt')} ${attempt.attempt_no}`} course={course} author={author} showStatus={false}>
            <div className="sticky top-0 z-10 mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                <p className="text-sm text-slate-600">
                    {t('lms.quiz.answered')}: <span className="font-semibold text-navy">{answered}/{questions.length}</span>
                    <span className="ms-3 text-xs text-slate-500" aria-live="polite">
                        {state === 'saving' ? t('lms.quiz.saving') : state === 'error' ? t('lms.quiz.saveFailed') : state === 'dirty' ? t('lms.quiz.unsaved') : t('lms.quiz.saved')}
                    </span>
                </p>
                {left !== null ? (
                    <p className={`font-mono text-lg font-bold ${low ? 'text-red-600' : 'text-navy'}`} dir="ltr" role="timer" aria-label={t('lms.quiz.timeLeft')}>
                        {clock(left)}
                    </p>
                ) : null}
            </div>

            <ol className="space-y-4">
                {questions.map((q, i) => (
                    <li key={q.id}>
                        <Card>
                            <p className="text-xs text-slate-500">
                                {i + 1}. {enumLabel(t, 'lms.qtype', q.type)} · {q.points} {t('lms.q.pointsShort')}
                            </p>
                            <p className="mt-1 whitespace-pre-wrap text-sm font-medium text-navy">{q.prompt}</p>
                            <div className="mt-3">
                                {q.type === 'multiple_choice' ? (
                                    <div className="space-y-2" role="radiogroup">
                                        {q.options.map((o, j) => (
                                            <label key={j} className="flex items-center gap-2 text-sm">
                                                <input type="radio" name={`q${q.id}`} checked={answers[q.id] === o} onChange={() => set(q.id, o)} />
                                                {o}
                                            </label>
                                        ))}
                                    </div>
                                ) : null}
                                {q.type === 'true_false' ? (
                                    <div className="flex gap-6" role="radiogroup">
                                        {['true', 'false'].map((v) => (
                                            <label key={v} className="flex items-center gap-2 text-sm">
                                                <input type="radio" name={`q${q.id}`} checked={answers[q.id] === v} onChange={() => set(q.id, v)} />
                                                {v === 'true' ? t('lms.q.true') : t('lms.q.false')}
                                            </label>
                                        ))}
                                    </div>
                                ) : null}
                                {q.type === 'short_answer' ? (
                                    <input
                                        type="text"
                                        className="block w-full max-w-md rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-bright focus:ring-brand-bright"
                                        value={answers[q.id] ?? ''}
                                        maxLength={1000}
                                        onChange={(e) => set(q.id, e.target.value)}
                                        aria-label={t('lms.quiz.yourAnswer')}
                                    />
                                ) : null}
                                {q.type === 'essay' ? (
                                    <textarea
                                        rows={8}
                                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-bright focus:ring-brand-bright"
                                        value={answers[q.id] ?? ''}
                                        maxLength={10000}
                                        onChange={(e) => set(q.id, e.target.value)}
                                        aria-label={t('lms.quiz.yourAnswer')}
                                    />
                                ) : null}
                            </div>
                        </Card>
                    </li>
                ))}
            </ol>

            <div className="mt-6 flex flex-wrap items-center gap-3">
                <button type="button" className="btn-teal" onClick={() => window.confirm(t('lms.quiz.confirmSubmit')) && submit()}>
                    {t('lms.quiz.submit')}
                </button>
                <button type="button" className="btn-outline" onClick={save} disabled={state === 'saving'}>
                    {t('lms.quiz.saveNow')}
                </button>
            </div>
        </LmsLayout>
    );
}

export default function QuizShow(props) {
    if (props.mode === 'preview') {
        return <Preview {...props} pulls={props.pulls ?? []} />;
    }
    if (props.mode === 'taking') {
        return <Taking {...props} />;
    }

    return <Intro {...props} attempts={props.attempts ?? []} />;
}
