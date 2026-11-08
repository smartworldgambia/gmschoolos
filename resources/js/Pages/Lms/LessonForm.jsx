import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { CONTENT_TYPES, fmtBytes, MATERIAL_TYPES } from '@/lib/lms';
import { Link, router, useForm } from '@inertiajs/react';

function toggle(list, id) {
    return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
}

function Materials({ lesson }) {
    const { t } = useLocale();
    const form = useForm({ type: 'file', title: '', url: '', file: null });

    return (
        <Card title={t('lms.lesson.materials')}>
            <p className="mb-3 text-sm text-slate-600">{t('lms.lesson.materialsHelp')}</p>
            {lesson.materials.length === 0 ? <p className="mb-3 text-sm text-slate-500">{t('lms.lesson.noMaterials')}</p> : null}
            <ul className="mb-4 divide-y divide-slate-100">
                {lesson.materials.map((m) => (
                    <li key={m.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                        <div className="min-w-0">
                            <p className="font-medium text-navy">{m.title}</p>
                            <p className="text-xs text-slate-500">
                                {enumLabel(t, 'lms.mtype', m.type)}
                                {m.size ? ` · ${fmtBytes(m.size)}` : ''}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {m.file_url || m.url ? (
                                <a href={m.file_url ?? m.url} target="_blank" rel="noreferrer noopener" className="btn-outline">
                                    {t('lms.common.open')}
                                </a>
                            ) : null}
                            {m.delete_url ? (
                                <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('lms.lesson.confirmMaterial')) && router.delete(m.delete_url, { preserveScroll: true })}>
                                    {t('lms.common.remove')}
                                </button>
                            ) : null}
                        </div>
                    </li>
                ))}
            </ul>
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('lms.lessons.materials.store', lesson.id), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <ErrorSummary errors={form.errors} />
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label={t('lms.lesson.materialType')}>
                        <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            {MATERIAL_TYPES.map((x) => (
                                <option key={x} value={x}>
                                    {enumLabel(t, 'lms.mtype', x)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('lms.common.title')}>
                        <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} maxLength={190} />
                    </Field>
                    <Field label={t('lms.lesson.materialUrl')} hint={t('lms.lesson.materialUrlHelp')}>
                        <Input type="url" dir="ltr" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="https://" />
                    </Field>
                    <Field label={t('lms.lesson.materialFile')} hint={t('lms.lesson.materialFileHelp')}>
                        <input type="file" className="text-sm" onChange={(e) => form.setData('file', e.target.files[0] ?? null)} />
                    </Field>
                </div>
                <button type="submit" className="btn-navy" disabled={form.processing}>
                    {t('lms.lesson.addMaterial')}
                </button>
            </form>
        </Card>
    );
}

export default function LessonForm({ course, author, lesson = null, sections = [], classes = [] }) {
    const { t } = useLocale();
    const form = useForm({
        title: lesson?.title ?? '',
        content_type: lesson?.content_type ?? 'text',
        content: lesson?.content ?? '',
        course_section_id: lesson?.course_section_id ?? '',
        duration_minutes: lesson?.duration_minutes ?? '',
        status: lesson?.status ?? 'draft',
        publish_at: lesson?.publish_at ?? '',
        objectives: lesson?.objectives?.length ? lesson.objectives : [''],
        audience_class_ids: lesson?.audience_class_ids ?? [],
        sort_order: lesson?.sort_order ?? '',
    });
    const d = form.data;

    function submit(e) {
        e.preventDefault();
        form.transform((data) => ({ ...data, objectives: data.objectives.filter((o) => o.trim() !== '') }));
        if (lesson) {
            form.put(route('lms.lessons.update', lesson.id), { preserveScroll: true });
        } else {
            form.post(route('lms.lessons.store', course.id));
        }
    }

    return (
        <LmsLayout
            title={lesson ? t('lms.lesson.editTitle') : t('lms.lesson.newTitle')}
            subtitle={t('lms.lesson.formSubtitle')}
            course={course}
            author={author}
            actions={
                <>
                    {lesson ? (
                        <Link href={route('lms.lessons.show', lesson.id)} className="btn-outline">
                            {t('lms.lesson.preview')}
                        </Link>
                    ) : null}
                    <Link href={route('lms.courses.show', course.id)} className="btn-outline">
                        {t('lms.common.back')}
                    </Link>
                </>
            }
        >
            <form onSubmit={submit} className="mb-6 space-y-6">
                <ErrorSummary errors={form.errors} />
                <Card title={t('lms.lesson.content')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('lms.common.title')}>
                            <Input value={d.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('lms.lesson.type')}>
                            <Select value={d.content_type} onChange={(e) => form.setData('content_type', e.target.value)}>
                                {CONTENT_TYPES.map((x) => (
                                    <option key={x} value={x}>
                                        {enumLabel(t, 'lms.ctype', x)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('lms.lesson.section')}>
                            <Select value={d.course_section_id} onChange={(e) => form.setData('course_section_id', e.target.value)}>
                                <option value="">{t('lms.lesson.noSection')}</option>
                                {sections.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.title}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('lms.lesson.duration')}>
                            <Input type="number" min="1" max="1000" value={d.duration_minutes} onChange={(e) => form.setData('duration_minutes', e.target.value)} />
                        </Field>
                    </div>
                    <Field label={t('lms.lesson.body')} hint={t('lms.lesson.bodyHelp')} className="mt-4">
                        <Textarea rows={10} value={d.content} onChange={(e) => form.setData('content', e.target.value)} maxLength={50000} />
                    </Field>
                </Card>

                <Card title={t('lms.course.objectives')}>
                    <div className="space-y-2">
                        {d.objectives.map((o, i) => (
                            <div key={i} className="flex gap-2">
                                <Input value={o} maxLength={300} onChange={(e) => form.setData('objectives', d.objectives.map((x, j) => (j === i ? e.target.value : x)))} />
                                <button type="button" className="btn-outline !text-red-700" onClick={() => form.setData('objectives', d.objectives.filter((_, j) => j !== i))}>
                                    {t('lms.common.remove')}
                                </button>
                            </div>
                        ))}
                    </div>
                    <button type="button" className="btn-outline mt-3" onClick={() => form.setData('objectives', [...d.objectives, ''])}>
                        {t('lms.course.addObjective')}
                    </button>
                </Card>

                <Card title={t('lms.lesson.publishing')}>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label={t('lms.common.status')}>
                            <Select value={d.status} onChange={(e) => form.setData('status', e.target.value)}>
                                <option value="draft">{t('lms.status.draft')}</option>
                                <option value="published">{t('lms.status.published')}</option>
                            </Select>
                        </Field>
                        <Field label={t('lms.lesson.publishAt')} hint={t('lms.lesson.publishAtHelp')}>
                            <Input type="datetime-local" value={d.publish_at} onChange={(e) => form.setData('publish_at', e.target.value)} />
                        </Field>
                        <Field label={t('lms.lesson.order')}>
                            <Input type="number" min="0" max="9999" value={d.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} />
                        </Field>
                    </div>
                    <fieldset className="mt-4">
                        <legend className="mb-1 text-sm font-medium text-slate-700">{t('lms.lesson.restrictClasses')}</legend>
                        <p className="mb-2 text-xs text-slate-500">{t('lms.lesson.restrictHelp')}</p>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            {classes.map((c) => (
                                <label key={c.id} className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" className="rounded border-gray-300" checked={d.audience_class_ids.includes(c.id)} onChange={() => form.setData('audience_class_ids', toggle(d.audience_class_ids, c.id))} />
                                    {c.name}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                </Card>

                <div className="flex flex-wrap gap-2">
                    <button type="submit" className="btn-teal" disabled={form.processing}>
                        {t('lms.lesson.save')}
                    </button>
                    {lesson ? (
                        <button
                            type="button"
                            className="btn-outline !text-red-700"
                            onClick={() => window.confirm(t('lms.lesson.confirmDelete')) && router.delete(route('lms.lessons.destroy', lesson.id))}
                        >
                            {t('lms.common.delete')}
                        </button>
                    ) : null}
                </div>
            </form>

            {lesson ? <Materials lesson={lesson} /> : <p className="text-sm text-slate-500">{t('lms.lesson.saveFirst')}</p>}
        </LmsLayout>
    );
}
