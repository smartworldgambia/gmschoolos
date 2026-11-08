import LmsLayout from '@/Components/Lms/LmsLayout';
import QuestionForm from '@/Components/Lms/QuestionForm';
import QuestionSummary from '@/Components/Lms/QuestionSummary';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function Questions({ quiz, questions, types }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(null);
    const [adding, setAdding] = useState(false);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const locked = quiz.has_attempts;

    const opts = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onError: (e) => setErrors(e),
        onSuccess: () => {
            setErrors({});
            setEditing(null);
            setAdding(false);
        },
    };

    return (
        <Card
            title={`${t('lms.quiz.questions')} (${questions.length})`}
            actions={
                !locked ? (
                    <button type="button" className="btn-teal" onClick={() => { setAdding(!adding); setEditing(null); setErrors({}); }}>
                        {t('lms.quiz.addQuestion')}
                    </button>
                ) : null
            }
        >
            {locked ? <p className="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('lms.quiz.lockedNotice')}</p> : null}
            {adding ? (
                <div className="mb-4">
                    <QuestionForm
                        types={types}
                        processing={busy}
                        errors={errors}
                        onSubmit={(payload) => router.post(route('lms.quizzes.questions.store', quiz.id), payload, opts)}
                        onCancel={() => setAdding(false)}
                        submitLabel={t('lms.quiz.addQuestion')}
                    />
                </div>
            ) : null}
            {questions.length === 0 ? <p className="text-sm text-slate-500">{t('lms.quiz.noQuestions')}</p> : null}
            <ol className="space-y-3">
                {questions.map((q, i) => (
                    <li key={q.id} className="rounded-lg border border-slate-200 p-3">
                        {editing === q.id ? (
                            <QuestionForm
                                initial={q}
                                types={types}
                                processing={busy}
                                errors={errors}
                                onSubmit={(payload) => router.put(route('lms.questions.update', q.id), payload, opts)}
                                onCancel={() => { setEditing(null); setErrors({}); }}
                            />
                        ) : (
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <QuestionSummary q={q} index={i} />
                                {!locked ? (
                                    <div className="flex gap-2">
                                        <button type="button" className="btn-outline" onClick={() => { setEditing(q.id); setAdding(false); setErrors({}); }}>
                                            {t('lms.common.edit')}
                                        </button>
                                        <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('lms.quiz.confirmQuestion')) && router.delete(route('lms.questions.destroy', q.id), { preserveScroll: true })}>
                                            {t('lms.common.delete')}
                                        </button>
                                    </div>
                                ) : null}
                            </div>
                        )}
                    </li>
                ))}
            </ol>
        </Card>
    );
}

function Pulls({ quiz, pulls, banks }) {
    const { t } = useLocale();
    const form = useForm({ question_bank_id: '', count: 5 });
    const locked = quiz.has_attempts;

    return (
        <Card title={t('lms.quiz.randomTitle')}>
            <p className="mb-3 text-sm text-slate-600">{t('lms.quiz.randomHelp')}</p>
            {pulls.length ? (
                <ul className="mb-4 divide-y divide-slate-100">
                    {pulls.map((p) => (
                        <li key={p.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                            <span>
                                <span className="font-medium text-navy">{p.bank}</span> · {t('lms.quiz.drawCount')}: {p.count}
                            </span>
                            {!locked ? (
                                <button type="button" className="btn-outline !text-red-700" onClick={() => router.delete(route('lms.pulls.destroy', p.id), { preserveScroll: true })}>
                                    {t('lms.common.remove')}
                                </button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : null}
            {!locked ? (
                <form
                    className="grid gap-3 sm:grid-cols-[2fr_8rem_auto] sm:items-end"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('lms.quizzes.pulls.store', quiz.id), { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                >
                    <Field label={t('lms.quiz.bank')} error={form.errors.question_bank_id}>
                        <Select value={form.data.question_bank_id} onChange={(e) => form.setData('question_bank_id', e.target.value)} required>
                            <option value="">{t('lms.common.choose')}</option>
                            {banks.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.title} ({b.count})
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('lms.quiz.drawCount')}>
                        <Input type="number" min="1" max="200" value={form.data.count} onChange={(e) => form.setData('count', e.target.value)} required />
                    </Field>
                    <button type="submit" className="btn-navy" disabled={form.processing}>
                        {t('lms.quiz.addDraw')}
                    </button>
                </form>
            ) : null}
            {banks.length === 0 ? (
                <p className="mt-2 text-xs text-slate-500">
                    {t('lms.quiz.noBanks')}{' '}
                    <Link href={route('lms.banks.index')} className="text-teal hover:underline">
                        {t('lms.index.banks')}
                    </Link>
                </p>
            ) : null}
        </Card>
    );
}

export default function QuizForm({ course, author, quiz = null, questions = [], pulls = [], banks = [], releaseModes = [], gradeMethods = [], questionTypes = [] }) {
    const { t } = useLocale();
    const form = useForm({
        title: quiz?.title ?? '',
        description: quiz?.description ?? '',
        status: quiz?.status ?? 'draft',
        opens_at: quiz?.opens_at ?? '',
        closes_at: quiz?.closes_at ?? '',
        time_limit_minutes: quiz?.time_limit_minutes ?? '',
        max_attempts: quiz?.max_attempts ?? 1,
        shuffle_questions: quiz ? quiz.shuffle_questions : false,
        shuffle_options: quiz ? quiz.shuffle_options : false,
        release_mode: quiz?.release_mode ?? 'immediate',
        release_at: quiz?.release_at ?? '',
        show_correct_answers: quiz ? quiz.show_correct_answers : true,
        grade_method: quiz?.grade_method ?? 'highest',
    });
    const d = form.data;

    function submit(e) {
        e.preventDefault();
        if (quiz) {
            form.put(route('lms.quizzes.update', quiz.id), { preserveScroll: true });
        } else {
            form.post(route('lms.quizzes.store', course.id));
        }
    }

    return (
        <LmsLayout
            title={quiz ? t('lms.quiz.editTitle') : t('lms.quiz.newTitle')}
            subtitle={quiz ? `${t('lms.quiz.maxScore')}: ${quiz.max_score ?? 0}` : t('lms.quiz.formSubtitle')}
            course={course}
            author={author}
            actions={
                <>
                    {quiz ? (
                        <>
                            <Link href={quiz.preview_url} className="btn-outline">
                                {t('lms.quiz.preview')}
                            </Link>
                            <Link href={quiz.attempts_url} className="btn-outline">
                                {t('lms.quiz.results')}
                            </Link>
                        </>
                    ) : null}
                    <Link href={route('lms.courses.show', course.id)} className="btn-outline">
                        {t('lms.common.back')}
                    </Link>
                </>
            }
        >
            <form onSubmit={submit} className="mb-6 space-y-6">
                <ErrorSummary errors={form.errors} />
                <Card title={t('lms.quiz.settings')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('lms.common.title')} className="sm:col-span-2">
                            <Input value={d.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('lms.common.description')} className="sm:col-span-2">
                            <Textarea rows={3} value={d.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={5000} />
                        </Field>
                        <Field label={t('lms.common.status')}>
                            <Select value={d.status} onChange={(e) => form.setData('status', e.target.value)}>
                                <option value="draft">{t('lms.status.draft')}</option>
                                <option value="published">{t('lms.status.published')}</option>
                            </Select>
                        </Field>
                        <Field label={t('lms.quiz.timeLimit')} hint={t('lms.quiz.timeLimitHelp')}>
                            <Input type="number" min="1" max="600" value={d.time_limit_minutes} onChange={(e) => form.setData('time_limit_minutes', e.target.value)} />
                        </Field>
                        <Field label={t('lms.quiz.opens')}>
                            <Input type="datetime-local" value={d.opens_at} onChange={(e) => form.setData('opens_at', e.target.value)} />
                        </Field>
                        <Field label={t('lms.quiz.closes')} hint={t('lms.quiz.closesHelp')}>
                            <Input type="datetime-local" value={d.closes_at} onChange={(e) => form.setData('closes_at', e.target.value)} />
                        </Field>
                        <Field label={t('lms.quiz.maxAttempts')}>
                            <Input type="number" min="1" max="10" value={d.max_attempts} onChange={(e) => form.setData('max_attempts', e.target.value)} required />
                        </Field>
                        <Field label={t('lms.quiz.gradeMethod')} hint={t('lms.quiz.gradeMethodHelp')}>
                            <Select value={d.grade_method} onChange={(e) => form.setData('grade_method', e.target.value)}>
                                {gradeMethods.map((x) => (
                                    <option key={x} value={x}>
                                        {enumLabel(t, 'lms.method', x)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-6">
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="rounded border-gray-300" checked={d.shuffle_questions} onChange={(e) => form.setData('shuffle_questions', e.target.checked)} />
                            {t('lms.quiz.shuffleQuestions')}
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="rounded border-gray-300" checked={d.shuffle_options} onChange={(e) => form.setData('shuffle_options', e.target.checked)} />
                            {t('lms.quiz.shuffleOptions')}
                        </label>
                    </div>
                </Card>

                <Card title={t('lms.quiz.resultsTitle')}>
                    <p className="mb-3 text-sm text-slate-600">{t('lms.quiz.resultsHelp')}</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('lms.quiz.releaseMode')}>
                            <Select value={d.release_mode} onChange={(e) => form.setData('release_mode', e.target.value)}>
                                {releaseModes.map((x) => (
                                    <option key={x} value={x}>
                                        {enumLabel(t, 'lms.release', x)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        {d.release_mode === 'scheduled' ? (
                            <Field label={t('lms.quiz.releaseAt')}>
                                <Input type="datetime-local" value={d.release_at} onChange={(e) => form.setData('release_at', e.target.value)} required />
                            </Field>
                        ) : null}
                    </div>
                    <label className="mt-4 flex items-start gap-2 text-sm">
                        <input type="checkbox" className="mt-1 rounded border-gray-300" checked={d.show_correct_answers} onChange={(e) => form.setData('show_correct_answers', e.target.checked)} />
                        <span>
                            {t('lms.quiz.showAnswers')}
                            <span className="block text-xs text-slate-500">{t('lms.quiz.showAnswersHelp')}</span>
                        </span>
                    </label>
                    {quiz && quiz.release_mode === 'manual' ? (
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            {quiz.released ? (
                                <span className="text-sm font-semibold text-emerald-700">{t('lms.quiz.released')}</span>
                            ) : (
                                <button type="button" className="btn-navy" onClick={() => window.confirm(t('lms.quiz.confirmRelease')) && router.post(route('lms.quizzes.release', quiz.id), {}, { preserveScroll: true })}>
                                    {t('lms.quiz.releaseNow')}
                                </button>
                            )}
                        </div>
                    ) : null}
                </Card>

                <div className="flex flex-wrap gap-2">
                    <button type="submit" className="btn-teal" disabled={form.processing}>
                        {t('lms.quiz.save')}
                    </button>
                    {quiz ? (
                        <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('lms.quiz.confirmDelete')) && router.delete(route('lms.quizzes.destroy', quiz.id))}>
                            {t('lms.common.delete')}
                        </button>
                    ) : null}
                </div>
            </form>

            {quiz ? (
                <div className="space-y-6">
                    <Questions quiz={quiz} questions={questions} types={questionTypes} />
                    <Pulls quiz={quiz} pulls={pulls} banks={banks} />
                </div>
            ) : (
                <p className="text-sm text-slate-500">{t('lms.quiz.saveFirst')}</p>
            )}
        </LmsLayout>
    );
}
