import LmsLayout from '@/Components/Lms/LmsLayout';
import QuestionForm from '@/Components/Lms/QuestionForm';
import QuestionSummary from '@/Components/Lms/QuestionSummary';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function BankShow({ bank, questions = [], subjects = [], questionTypes = [] }) {
    const { t } = useLocale();
    const form = useForm({ title: bank.title, subject_id: bank.subject_id ?? '', is_shared: bank.is_shared });
    const [editing, setEditing] = useState(null);
    const [adding, setAdding] = useState(false);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);

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
        <LmsLayout
            title={bank.title}
            subtitle={t('lms.banks.subtitle')}
            author
            actions={
                <Link href={route('lms.banks.index')} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            {!bank.can_edit ? <p className="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{t('lms.banks.readOnly')}</p> : null}

            {bank.can_edit ? (
                <Card title={t('lms.banks.settings')} className="mb-6">
                    <form
                        className="grid gap-3 sm:grid-cols-[2fr_1fr_auto] sm:items-end"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put(route('lms.banks.update', bank.id), { preserveScroll: true });
                        }}
                    >
                        <div className="sm:col-span-3">
                            <ErrorSummary errors={form.errors} />
                        </div>
                        <Field label={t('lms.common.title')}>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('lms.course.subject')}>
                            <Select value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                                <option value="">{t('lms.common.none')}</option>
                                {subjects.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <button type="submit" className="btn-navy" disabled={form.processing}>
                            {t('lms.common.save')}
                        </button>
                        <label className="flex items-center gap-2 text-sm sm:col-span-3">
                            <input type="checkbox" className="rounded border-gray-300" checked={form.data.is_shared} onChange={(e) => form.setData('is_shared', e.target.checked)} />
                            {t('lms.banks.share')}
                        </label>
                    </form>
                    <button type="button" className="btn-outline mt-4 !text-red-700" onClick={() => window.confirm(t('lms.banks.confirmDelete')) && router.delete(route('lms.banks.destroy', bank.id))}>
                        {t('lms.banks.delete')}
                    </button>
                </Card>
            ) : null}

            <Card
                title={`${t('lms.banks.questions')} (${questions.length})`}
                actions={
                    bank.can_edit ? (
                        <button type="button" className="btn-teal" onClick={() => { setAdding(!adding); setEditing(null); setErrors({}); }}>
                            {t('lms.quiz.addQuestion')}
                        </button>
                    ) : null
                }
            >
                <p className="mb-3 text-xs text-slate-500">{t('lms.banks.snapshotNote')}</p>
                {adding ? (
                    <div className="mb-4">
                        <QuestionForm
                            types={questionTypes}
                            processing={busy}
                            errors={errors}
                            onSubmit={(p) => router.post(route('lms.banks.questions.store', bank.id), p, opts)}
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
                                    types={questionTypes}
                                    processing={busy}
                                    errors={errors}
                                    onSubmit={(p) => router.put(route('lms.bank-questions.update', q.id), p, opts)}
                                    onCancel={() => { setEditing(null); setErrors({}); }}
                                />
                            ) : (
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <QuestionSummary q={q} index={i} />
                                    {bank.can_edit ? (
                                        <div className="flex gap-2">
                                            <button type="button" className="btn-outline" onClick={() => { setEditing(q.id); setAdding(false); setErrors({}); }}>
                                                {t('lms.common.edit')}
                                            </button>
                                            <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('lms.quiz.confirmQuestion')) && router.delete(route('lms.bank-questions.destroy', q.id), { preserveScroll: true })}>
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
        </LmsLayout>
    );
}
