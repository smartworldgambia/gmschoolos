import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtBytes } from '@/lib/lms';
import { Link, router, useForm } from '@inertiajs/react';

function Attachments({ assignment }) {
    const { t } = useLocale();
    const form = useForm({ title: '', file: null });

    return (
        <Card title={t('lms.assignment.attachments')}>
            {assignment.attachments.length === 0 ? <p className="mb-3 text-sm text-slate-500">{t('lms.assignment.noAttachments')}</p> : null}
            <ul className="mb-4 divide-y divide-slate-100">
                {assignment.attachments.map((f) => (
                    <li key={f.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                        <span className="min-w-0">
                            <span className="font-medium text-navy">{f.title}</span> <span className="text-xs text-slate-500">{fmtBytes(f.size)}</span>
                        </span>
                        <span className="flex gap-2">
                            <a href={f.file_url} target="_blank" rel="noreferrer noopener" className="btn-outline">
                                {t('lms.common.download')}
                            </a>
                            {f.delete_url ? (
                                <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('lms.lesson.confirmMaterial')) && router.delete(f.delete_url, { preserveScroll: true })}>
                                    {t('lms.common.remove')}
                                </button>
                            ) : null}
                        </span>
                    </li>
                ))}
            </ul>
            <form
                className="flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('lms.assignments.attach', assignment.id), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <Field label={t('lms.common.title')}>
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} maxLength={190} />
                </Field>
                <Field label={t('lms.lesson.materialFile')} error={form.errors.file}>
                    <input type="file" className="text-sm" onChange={(e) => form.setData('file', e.target.files[0] ?? null)} />
                </Field>
                <button type="submit" className="btn-navy" disabled={form.processing || !form.data.file}>
                    {t('lms.assignment.attach')}
                </button>
            </form>
        </Card>
    );
}

export default function AssignmentForm({ course, author, assignment = null, latePolicies = ['allow', 'block'] }) {
    const { t } = useLocale();
    const form = useForm({
        title: assignment?.title ?? '',
        instructions: assignment?.instructions ?? '',
        due_at: assignment?.due_at ?? '',
        closes_at: assignment?.closes_at ?? '',
        publish_at: assignment?.publish_at ?? '',
        max_score: assignment?.max_score ?? 100,
        status: assignment?.status ?? 'draft',
        late_policy: assignment?.late_policy ?? 'allow',
        max_attempts: assignment?.max_attempts ?? 1,
        accepts_text: assignment ? assignment.accepts_text : true,
        accepts_files: assignment ? assignment.accepts_files : true,
        rubric_title: assignment?.rubric_title ?? '',
        criteria: assignment?.criteria?.length ? assignment.criteria.map((c) => ({ label: c.label, max_points: c.max_points })) : [],
    });
    const d = form.data;
    const rubricTotal = d.criteria.reduce((s, c) => s + (Number(c.max_points) || 0), 0);

    function submit(e) {
        e.preventDefault();
        if (assignment) {
            form.put(route('lms.assignments.update', assignment.id), { preserveScroll: true });
        } else {
            form.post(route('lms.assignments.store', course.id));
        }
    }

    return (
        <LmsLayout
            title={assignment ? t('lms.assignment.editTitle') : t('lms.assignment.newTitle')}
            subtitle={t('lms.assignment.formSubtitle')}
            course={course}
            author={author}
            actions={
                <Link href={assignment ? route('lms.assignments.show', assignment.id) : route('lms.courses.show', course.id)} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            <form onSubmit={submit} className="mb-6 space-y-6">
                <ErrorSummary errors={form.errors} />
                <Card title={t('lms.assignment.details')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('lms.common.title')} className="sm:col-span-2">
                            <Input value={d.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('lms.assignment.maxScore')}>
                            <Input type="number" min="1" max="1000" value={d.max_score} onChange={(e) => form.setData('max_score', e.target.value)} required />
                        </Field>
                        <Field label={t('lms.common.status')}>
                            <Select value={d.status} onChange={(e) => form.setData('status', e.target.value)}>
                                <option value="draft">{t('lms.status.draft')}</option>
                                <option value="published">{t('lms.status.published')}</option>
                            </Select>
                        </Field>
                        <Field label={t('lms.assignment.publishAt')} hint={t('lms.lesson.publishAtHelp')}>
                            <Input type="datetime-local" value={d.publish_at} onChange={(e) => form.setData('publish_at', e.target.value)} />
                        </Field>
                        <Field label={t('lms.assignment.dueAt')}>
                            <Input type="datetime-local" value={d.due_at} onChange={(e) => form.setData('due_at', e.target.value)} />
                        </Field>
                        <Field label={t('lms.assignment.closesAt')} hint={t('lms.assignment.closesHelp')}>
                            <Input type="datetime-local" value={d.closes_at} onChange={(e) => form.setData('closes_at', e.target.value)} />
                        </Field>
                        <Field label={t('lms.assignment.latePolicy')}>
                            <Select value={d.late_policy} onChange={(e) => form.setData('late_policy', e.target.value)}>
                                {latePolicies.map((x) => (
                                    <option key={x} value={x}>
                                        {enumLabel(t, 'lms.latePolicy', x)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('lms.assignment.maxAttempts')} hint={t('lms.assignment.maxAttemptsHelp')}>
                            <Input type="number" min="1" max="10" value={d.max_attempts} onChange={(e) => form.setData('max_attempts', e.target.value)} required />
                        </Field>
                    </div>
                    <Field label={t('lms.assignment.instructions')} className="mt-4">
                        <Textarea rows={8} value={d.instructions} onChange={(e) => form.setData('instructions', e.target.value)} maxLength={20000} />
                    </Field>
                    <div className="mt-4 flex flex-wrap gap-6">
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="rounded border-gray-300" checked={d.accepts_text} onChange={(e) => form.setData('accepts_text', e.target.checked)} />
                            {t('lms.assignment.acceptsText')}
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="rounded border-gray-300" checked={d.accepts_files} onChange={(e) => form.setData('accepts_files', e.target.checked)} />
                            {t('lms.assignment.acceptsFiles')}
                        </label>
                    </div>
                </Card>

                <Card title={t('lms.assignment.rubric')}>
                    <p className="mb-3 text-sm text-slate-600">{t('lms.assignment.rubricHelp')}</p>
                    {d.criteria.length ? (
                        <Field label={t('lms.assignment.rubricTitle')} className="mb-3 max-w-md">
                            <Input value={d.rubric_title} onChange={(e) => form.setData('rubric_title', e.target.value)} maxLength={150} />
                        </Field>
                    ) : null}
                    <div className="space-y-2">
                        {d.criteria.map((c, i) => (
                            <div key={i} className="grid gap-2 sm:grid-cols-[1fr_8rem_auto]">
                                <Input value={c.label} placeholder={t('lms.assignment.criterion')} maxLength={150} onChange={(e) => form.setData('criteria', d.criteria.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} />
                                <Input type="number" min="1" max="1000" value={c.max_points} placeholder={t('lms.q.points')} onChange={(e) => form.setData('criteria', d.criteria.map((x, j) => (j === i ? { ...x, max_points: e.target.value } : x)))} />
                                <button type="button" className="btn-outline !text-red-700" onClick={() => form.setData('criteria', d.criteria.filter((_, j) => j !== i))}>
                                    {t('lms.common.remove')}
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <button type="button" className="btn-outline" onClick={() => form.setData('criteria', [...d.criteria, { label: '', max_points: '' }])}>
                            {t('lms.assignment.addCriterion')}
                        </button>
                        {d.criteria.length ? (
                            <span className={`text-sm ${rubricTotal === Number(d.max_score) ? 'text-emerald-700' : 'text-red-700'}`}>
                                {t('lms.assignment.rubricTotal')}: {rubricTotal} / {d.max_score}
                            </span>
                        ) : null}
                    </div>
                </Card>

                <div className="flex flex-wrap gap-2">
                    <button type="submit" className="btn-teal" disabled={form.processing}>
                        {t('lms.assignment.save')}
                    </button>
                    {assignment ? (
                        <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('lms.assignment.confirmDelete')) && router.delete(route('lms.assignments.destroy', assignment.id))}>
                            {t('lms.common.delete')}
                        </button>
                    ) : null}
                </div>
            </form>

            {assignment ? <Attachments assignment={assignment} /> : <p className="text-sm text-slate-500">{t('lms.assignment.saveFirst')}</p>}
        </LmsLayout>
    );
}
