import { Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, errorList } from '@/lib/academic';
import { QUESTION_TYPES } from '@/lib/lms';
import { useState } from 'react';

/** Shared question editor (quiz questions and question-bank questions). Values sent to the server stay stable slugs. */
export default function QuestionForm({ initial = null, types = QUESTION_TYPES, onSubmit, onCancel, processing = false, errors = {}, submitLabel }) {
    const { t } = useLocale();
    const startOptions = initial?.options?.length ? initial.options : ['', ''];
    const [type, setType] = useState(initial?.type ?? 'multiple_choice');
    const [prompt, setPrompt] = useState(initial?.prompt ?? '');
    const [points, setPoints] = useState(initial?.points ?? 1);
    const [options, setOptions] = useState(initial?.type === 'multiple_choice' ? startOptions : ['', '']);
    const [correctIndex, setCorrectIndex] = useState(initial?.type === 'multiple_choice' ? Math.max(0, startOptions.indexOf(initial.correct_answer)) : 0);
    const [trueFalse, setTrueFalse] = useState(initial?.type === 'true_false' ? initial.correct_answer : 'true');
    const [accepted, setAccepted] = useState((initial?.accepted_answers ?? []).join('\n'));
    const [caseSensitive, setCaseSensitive] = useState(!!initial?.case_sensitive);
    const [explanation, setExplanation] = useState(initial?.explanation ?? '');
    const list = errorList(t, errors);

    function submit(e) {
        e.preventDefault();
        const payload = { type, prompt, points: Number(points) || 1, explanation: explanation || null, case_sensitive: caseSensitive };
        if (type === 'multiple_choice') {
            const kept = options.map((o, i) => ({ o: o.trim(), i })).filter((x) => x.o !== '');
            payload.options = kept.map((x) => x.o);
            payload.correct_answer = options[correctIndex]?.trim() || '';
        } else if (type === 'true_false') {
            payload.correct_answer = trueFalse;
        } else if (type === 'short_answer') {
            payload.accepted_answers = accepted.split('\n').map((x) => x.trim()).filter(Boolean);
        }
        onSubmit(payload);
    }

    return (
        <form onSubmit={submit} className="space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
            {list.length ? (
                <ul className="list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 px-8 py-3 text-sm text-red-800" role="alert">
                    {list.map((m, i) => (
                        <li key={i}>{m}</li>
                    ))}
                </ul>
            ) : null}
            <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                <Field label={t('lms.q.type')}>
                    <Select value={type} onChange={(e) => setType(e.target.value)}>
                        {types.map((x) => (
                            <option key={x} value={x}>
                                {enumLabel(t, 'lms.qtype', x)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('lms.q.points')}>
                    <Input type="number" min="1" max="100" value={points} onChange={(e) => setPoints(e.target.value)} />
                </Field>
            </div>
            <Field label={t('lms.q.prompt')}>
                <Textarea rows={3} value={prompt} onChange={(e) => setPrompt(e.target.value)} required maxLength={3000} />
            </Field>

            {type === 'multiple_choice' ? (
                <fieldset className="space-y-2">
                    <legend className="mb-1 text-sm font-medium text-slate-700">{t('lms.q.options')}</legend>
                    <p className="text-xs text-slate-500">{t('lms.q.optionsHelp')}</p>
                    {options.map((o, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <input type="radio" name="correct" checked={correctIndex === i} onChange={() => setCorrectIndex(i)} aria-label={t('lms.q.correctOption')} />
                            <Input value={o} maxLength={300} onChange={(e) => setOptions(options.map((x, j) => (j === i ? e.target.value : x)))} placeholder={`${t('lms.q.option')} ${i + 1}`} />
                            {options.length > 2 ? (
                                <button
                                    type="button"
                                    className="btn-outline !text-red-700"
                                    onClick={() => {
                                        setOptions(options.filter((_, j) => j !== i));
                                        setCorrectIndex(correctIndex >= i && correctIndex > 0 ? correctIndex - 1 : correctIndex);
                                    }}
                                >
                                    {t('lms.common.remove')}
                                </button>
                            ) : null}
                        </div>
                    ))}
                    {options.length < 8 ? (
                        <button type="button" className="btn-outline" onClick={() => setOptions([...options, ''])}>
                            {t('lms.q.addOption')}
                        </button>
                    ) : null}
                </fieldset>
            ) : null}

            {type === 'true_false' ? (
                <Field label={t('lms.q.correctAnswer')}>
                    <Select value={trueFalse} onChange={(e) => setTrueFalse(e.target.value)}>
                        <option value="true">{t('lms.q.true')}</option>
                        <option value="false">{t('lms.q.false')}</option>
                    </Select>
                </Field>
            ) : null}

            {type === 'short_answer' ? (
                <>
                    <Field label={t('lms.q.accepted')} hint={t('lms.q.acceptedHelp')}>
                        <Textarea rows={3} value={accepted} onChange={(e) => setAccepted(e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" className="rounded border-gray-300" checked={caseSensitive} onChange={(e) => setCaseSensitive(e.target.checked)} />
                        {t('lms.q.caseSensitive')}
                    </label>
                </>
            ) : null}

            {type === 'essay' ? <p className="text-sm text-slate-600">{t('lms.q.essayHelp')}</p> : null}

            <Field label={t('lms.q.explanation')} hint={t('lms.q.explanationHelp')}>
                <Textarea rows={2} value={explanation} onChange={(e) => setExplanation(e.target.value)} maxLength={2000} />
            </Field>

            <div className="flex flex-wrap gap-2">
                <button type="submit" className="btn-teal" disabled={processing}>
                    {submitLabel ?? t('lms.common.save')}
                </button>
                {onCancel ? (
                    <button type="button" className="btn-outline" onClick={onCancel}>
                        {t('lms.common.cancel')}
                    </button>
                ) : null}
            </div>
        </form>
    );
}
