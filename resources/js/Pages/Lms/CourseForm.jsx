import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import LmsLayout from '@/Components/Lms/LmsLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { COURSE_STATUSES } from '@/lib/lms';
import { Link, useForm } from '@inertiajs/react';

function toggle(list, id) {
    return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
}

export default function CourseForm({ course = null, subjects = [], classes = [], teachers = [], categories = [], canAssignTeachers = false }) {
    const { t } = useLocale();
    const form = useForm({
        title: course?.title ?? '',
        code: course?.code ?? '',
        description: course?.description ?? '',
        subject_id: course?.subject_id ?? '',
        category_id: course?.category_id ?? '',
        category_name: '',
        status: course?.status ?? 'draft',
        objectives: course?.objectives?.length ? course.objectives : [''],
        teacher_id: course?.teacher_id ?? '',
        co_teacher_ids: course?.co_teacher_ids ?? [],
        audience_class_ids: course?.audience_class_ids ?? [],
    });
    const d = form.data;

    function submit(e) {
        e.preventDefault();
        const payload = { ...d, objectives: d.objectives.filter((o) => o.trim() !== '') };
        form.transform(() => payload);
        if (course) {
            form.put(route('lms.courses.update', course.id));
        } else {
            form.post(route('lms.courses.store'));
        }
    }

    return (
        <LmsLayout
            title={course ? t('lms.course.editTitle') : t('lms.course.newTitle')}
            subtitle={t('lms.course.subtitle')}
            course={course ? { id: course.id, title: course.title, status: course.status } : null}
            author
            actions={
                <Link href={course ? route('lms.courses.show', course.id) : route('lms.courses.index')} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            <form onSubmit={submit} className="space-y-6">
                <ErrorSummary errors={form.errors} />
                <Card title={t('lms.course.details')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('lms.course.name')}>
                            <Input value={d.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('lms.course.code')}>
                            <Input value={d.code} onChange={(e) => form.setData('code', e.target.value)} maxLength={40} />
                        </Field>
                        <Field label={t('lms.course.subject')} hint={t('lms.course.subjectHelp')}>
                            <Select value={d.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                                <option value="">{t('lms.common.none')}</option>
                                {subjects.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('lms.course.status')}>
                            <Select value={d.status} onChange={(e) => form.setData('status', e.target.value)}>
                                {COURSE_STATUSES.map((x) => (
                                    <option key={x} value={x}>
                                        {enumLabel(t, 'lms.status', x)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('lms.course.category')}>
                            <Select value={d.category_id} onChange={(e) => form.setData('category_id', e.target.value)}>
                                <option value="">{t('lms.common.none')}</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('lms.course.newCategory')} hint={t('lms.course.newCategoryHelp')}>
                            <Input value={d.category_name} onChange={(e) => form.setData('category_name', e.target.value)} maxLength={120} />
                        </Field>
                    </div>
                    <Field label={t('lms.course.description')} className="mt-4">
                        <Textarea rows={4} value={d.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={5000} />
                    </Field>
                </Card>

                <Card title={t('lms.course.objectives')}>
                    <p className="mb-3 text-sm text-slate-600">{t('lms.course.objectivesHelp')}</p>
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

                <Card title={t('lms.course.audience')}>
                    <p className="mb-3 text-sm text-slate-600">{t('lms.course.audienceHelp')}</p>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {classes.map((c) => (
                            <label key={c.id} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" className="rounded border-gray-300" checked={d.audience_class_ids.includes(c.id)} onChange={() => form.setData('audience_class_ids', toggle(d.audience_class_ids, c.id))} />
                                {c.name}
                            </label>
                        ))}
                        {classes.length === 0 ? <p className="text-sm text-slate-500">{t('lms.course.noClasses')}</p> : null}
                    </div>
                </Card>

                {canAssignTeachers ? (
                    <Card title={t('lms.course.teachers')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('lms.course.leadTeacher')}>
                                <Select value={d.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)}>
                                    <option value="">{t('lms.common.none')}</option>
                                    {teachers.map((x) => (
                                        <option key={x.id} value={x.id}>
                                            {x.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <fieldset>
                                <legend className="mb-1 text-sm font-medium text-slate-700">{t('lms.course.coTeachers')}</legend>
                                <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2">
                                    {teachers.map((x) => (
                                        <label key={x.id} className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" className="rounded border-gray-300" checked={d.co_teacher_ids.includes(x.id)} onChange={() => form.setData('co_teacher_ids', toggle(d.co_teacher_ids, x.id))} />
                                            {x.name}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        </div>
                    </Card>
                ) : null}

                <button type="submit" className="btn-teal" disabled={form.processing}>
                    {t('lms.course.save')}
                </button>
            </form>
        </LmsLayout>
    );
}
