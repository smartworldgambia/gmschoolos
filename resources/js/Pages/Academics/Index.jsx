import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function remove(t, url) {
    if (confirm(t('academics.confirmRemove'))) {
        router.delete(url, { preserveScroll: true });
    }
}

function AddLevel() {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', teaching_model: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('academics.levels.store'), {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                    transform: (d) => (d.teaching_model ? d : { name: d.name, code: d.code }),
                });
            }}
            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
        >
            <ErrorSummary errors={errors} />
            <Field label={t('academics.level.name')}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            </Field>
            <Field label={t('acad.common.code')}>
                <Input value={data.code} onChange={(e) => setData('code', e.target.value)} className="!w-28" />
            </Field>
            <Field label={t('academics.level.model')}>
                <Select value={data.teaching_model} onChange={(e) => setData('teaching_model', e.target.value)} className="!w-auto">
                    <option value="">{t('acad.common.choose')}</option>
                    <option value="homeroom">{t('academics.level.homeroomShort')}</option>
                    <option value="specialist">{t('academics.level.specialistShort')}</option>
                </Select>
            </Field>
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('acad.common.add')}
            </button>
        </form>
    );
}

function ClassRow({ item, teachers, canManage }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors } = useForm({
        name: item.name,
        stream: item.stream ?? '',
        capacity: item.capacity ?? '',
        class_teacher_id: item.class_teacher_id ?? '',
    });

    if (!editing) {
        return (
            <tr className="border-t border-slate-100">
                <td className="px-3 py-2 font-medium text-navy">{item.name}</td>
                <td className="px-3 py-2 text-slate-600">{item.level ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">{item.stream ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">{item.teacher ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">
                    {item.students_count}
                    {item.capacity ? ` / ${item.capacity}` : ''}
                </td>
                <td className="px-3 py-2 text-end">
                    {canManage ? (
                        <span className="inline-flex gap-3 whitespace-nowrap">
                            <button type="button" className="text-teal hover:underline" onClick={() => setEditing(true)}>
                                {t('acad.common.edit')}
                            </button>
                            <button type="button" className="text-red-600 hover:underline" onClick={() => remove(t, route('academics.classes.destroy', item.id))}>
                                {t('acad.common.remove')}
                            </button>
                        </span>
                    ) : null}
                </td>
            </tr>
        );
    }

    return (
        <tr className="border-t border-slate-100 bg-slate-50 align-top">
            <td colSpan={6} className="px-3 py-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('academics.classes.update', item.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
                    <Field label={t('academics.class.name')} error={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label={t('academics.class.stream')} error={errors.stream}>
                        <Input value={data.stream} onChange={(e) => setData('stream', e.target.value)} className="!w-28" />
                    </Field>
                    <Field label={t('academics.class.capacity')} error={errors.capacity}>
                        <Input type="number" min="1" value={data.capacity} onChange={(e) => setData('capacity', e.target.value)} className="!w-28" />
                    </Field>
                    <Field label={t('academics.class.classTeacher')} error={errors.class_teacher_id}>
                        <Select value={data.class_teacher_id} onChange={(e) => setData('class_teacher_id', e.target.value)}>
                            <option value="">{t('acad.common.none')}</option>
                            {teachers.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('acad.common.save')}
                    </button>
                    <button type="button" className="btn-outline" onClick={() => setEditing(false)}>
                        {t('acad.common.cancel')}
                    </button>
                </form>
            </td>
        </tr>
    );
}

function AddClass({ levels, teachers }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ level_id: '', name: '', stream: '', capacity: '', class_teacher_id: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('academics.classes.store'), { preserveScroll: true, onSuccess: () => reset() });
            }}
            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
        >
            <ErrorSummary errors={errors} />
            <Field label={t('academics.class.level')}>
                <Select value={data.level_id} onChange={(e) => setData('level_id', e.target.value)} required>
                    <option value="">{t('acad.common.choose')}</option>
                    {levels.map((l) => (
                        <option key={l.id} value={l.id}>
                            {l.name}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('academics.class.name')}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            </Field>
            <Field label={t('academics.class.stream')}>
                <Input value={data.stream} onChange={(e) => setData('stream', e.target.value)} className="!w-28" />
            </Field>
            <Field label={t('academics.class.capacity')}>
                <Input type="number" min="1" value={data.capacity} onChange={(e) => setData('capacity', e.target.value)} className="!w-28" />
            </Field>
            <Field label={t('academics.class.classTeacher')} error={errors.class_teacher_id}>
                <Select value={data.class_teacher_id} onChange={(e) => setData('class_teacher_id', e.target.value)}>
                    <option value="">{t('acad.common.none')}</option>
                    {teachers.map((x) => (
                        <option key={x.id} value={x.id}>
                            {x.name}
                        </option>
                    ))}
                </Select>
            </Field>
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('acad.common.add')}
            </button>
        </form>
    );
}

function SubjectRow({ item, departments, canManage }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors } = useForm({
        name: item.name,
        code: item.code ?? '',
        department_id: item.department_id ?? '',
        is_specialist: !!item.is_specialist,
    });

    if (!editing) {
        return (
            <tr className="border-t border-slate-100">
                <td className="px-3 py-2 font-medium text-navy">{item.name}</td>
                <td className="px-3 py-2 text-slate-600">{item.code ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">{item.department ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">{item.classes_count}</td>
                <td className="px-3 py-2 text-slate-600">{(item.teachers ?? []).join(', ') || '—'}</td>
                <td className="px-3 py-2 text-slate-600">
                    {item.is_specialist ? t('academics.subject.specialistShort') : '—'}
                </td>
                <td className="px-3 py-2 text-end">
                    {canManage ? (
                        <span className="inline-flex gap-3 whitespace-nowrap">
                            <button type="button" className="text-teal hover:underline" onClick={() => setEditing(true)}>
                                {t('acad.common.edit')}
                            </button>
                            <button type="button" className="text-red-600 hover:underline" onClick={() => remove(t, route('academics.subjects.destroy', item.id))}>
                                {t('acad.common.remove')}
                            </button>
                        </span>
                    ) : null}
                </td>
            </tr>
        );
    }

    return (
        <tr className="border-t border-slate-100 bg-slate-50">
            <td colSpan={7} className="px-3 py-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('academics.subjects.update', item.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
                    <Field label={t('academics.subject.name')} error={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label={t('acad.common.code')} error={errors.code}>
                        <Input value={data.code} onChange={(e) => setData('code', e.target.value)} className="!w-28" />
                    </Field>
                    <Field label={t('academics.subject.department')} error={errors.department_id}>
                        <Select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)}>
                            <option value="">{t('acad.common.none')}</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <label className="inline-flex items-center gap-2 pb-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            className="rounded border-gray-300"
                            checked={!!data.is_specialist}
                            onChange={(e) => setData('is_specialist', e.target.checked)}
                        />
                        {t('academics.subject.specialist')}
                    </label>
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('acad.common.save')}
                    </button>
                    <button type="button" className="btn-outline" onClick={() => setEditing(false)}>
                        {t('acad.common.cancel')}
                    </button>
                </form>
            </td>
        </tr>
    );
}

function AddSubject({ departments }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', department_id: '', is_specialist: false });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('academics.subjects.store'), {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                    transform: (d) => (d.is_specialist ? d : { name: d.name, code: d.code, department_id: d.department_id }),
                });
            }}
            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
        >
            <ErrorSummary errors={errors} />
            <Field label={t('academics.subject.name')}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            </Field>
            <Field label={t('acad.common.code')}>
                <Input value={data.code} onChange={(e) => setData('code', e.target.value)} className="!w-28" />
            </Field>
            <Field label={t('academics.subject.department')}>
                <Select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)}>
                    <option value="">{t('acad.common.none')}</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </Select>
            </Field>
            <label className="inline-flex items-center gap-2 pb-2 text-sm text-slate-700">
                <input
                    type="checkbox"
                    className="rounded border-gray-300"
                    checked={!!data.is_specialist}
                    onChange={(e) => setData('is_specialist', e.target.checked)}
                />
                {t('academics.subject.specialist')}
            </label>
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('acad.common.add')}
            </button>
            <p className="w-full text-xs text-slate-500">{t('academics.subject.lmsHint')}</p>
        </form>
    );
}

function DepartmentRow({ item, teachers, canManage }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors } = useForm({ name: item.name, code: item.code ?? '', head_user_id: item.head_user_id ?? '' });

    if (!editing) {
        return (
            <tr className="border-t border-slate-100">
                <td className="px-3 py-2 font-medium text-navy">{item.name}</td>
                <td className="px-3 py-2 text-slate-600">{item.code ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">{item.head ?? '—'}</td>
                <td className="px-3 py-2 text-end">
                    {canManage ? (
                        <span className="inline-flex gap-3 whitespace-nowrap">
                            <button type="button" className="text-teal hover:underline" onClick={() => setEditing(true)}>
                                {t('acad.common.edit')}
                            </button>
                            <button type="button" className="text-red-600 hover:underline" onClick={() => remove(t, route('academics.departments.destroy', item.id))}>
                                {t('acad.common.remove')}
                            </button>
                        </span>
                    ) : null}
                </td>
            </tr>
        );
    }

    return (
        <tr className="border-t border-slate-100 bg-slate-50">
            <td colSpan={4} className="px-3 py-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('academics.departments.update', item.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
                    <Field label={t('academics.department.name')} error={errors.name}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label={t('acad.common.code')} error={errors.code}>
                        <Input value={data.code} onChange={(e) => setData('code', e.target.value)} className="!w-28" />
                    </Field>
                    <Field label={t('academics.department.head')} error={errors.head_user_id}>
                        <Select value={data.head_user_id} onChange={(e) => setData('head_user_id', e.target.value)}>
                            <option value="">{t('acad.common.none')}</option>
                            {teachers.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('acad.common.save')}
                    </button>
                    <button type="button" className="btn-outline" onClick={() => setEditing(false)}>
                        {t('acad.common.cancel')}
                    </button>
                </form>
            </td>
        </tr>
    );
}

function AddDepartment() {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('academics.departments.store'), { preserveScroll: true, onSuccess: () => reset() });
            }}
            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
        >
            <ErrorSummary errors={errors} />
            <Field label={t('academics.department.name')}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            </Field>
            <Field label={t('acad.common.code')}>
                <Input value={data.code} onChange={(e) => setData('code', e.target.value)} className="!w-28" />
            </Field>
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('acad.common.add')}
            </button>
        </form>
    );
}

function Th({ children }) {
    return <th className="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wide text-slate-500">{children}</th>;
}

export default function Index({ classes = [], subjects = [], levels = [], departments = [], teachers = [], can = {}, links = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();

    return (
        <AppShell title={t('academics.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('academics.title')} />
            <PageHeader
                title={t('academics.title')}
                subtitle={t('academics.subtitle')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Link href={links.allocation} className="btn-outline">
                            {t('academics.link.allocation')}
                        </Link>
                        <Link href={links.calendar} className="btn-outline">
                            {t('academics.link.calendar')}
                        </Link>
                        {links.promotion ? (
                            <Link href={links.promotion} className="btn-outline">
                                {t('academics.link.promotion')}
                            </Link>
                        ) : null}
                    </div>
                }
            />

            <div className="space-y-6">
                <Card title={t('academics.levels')}>
                    <div className="flex flex-wrap gap-2">
                        {levels.length === 0 ? <p className="text-sm text-slate-500">{t('academics.noLevels')}</p> : null}
                        {levels.map((l) => (
                            <span key={l.id} className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm text-navy">
                                {l.name}
                                {can.manage ? (
                                    <select
                                        value={l.teaching_model === 'specialist' ? 'specialist' : 'homeroom'}
                                        aria-label={t('academics.level.model')}
                                        className="max-w-[9rem] rounded-md border-0 bg-transparent py-0 pe-6 text-xs text-slate-600"
                                        onChange={(e) => router.put(route('academics.levels.update', l.id), { teaching_model: e.target.value }, { preserveScroll: true })}
                                    >
                                        <option value="homeroom">{t('academics.level.homeroomShort')}</option>
                                        <option value="specialist">{t('academics.level.specialistShort')}</option>
                                    </select>
                                ) : (
                                    <span className="text-xs text-slate-500">
                                        {t(l.teaching_model === 'homeroom' ? 'academics.level.homeroomShort' : 'academics.level.specialistShort')}
                                    </span>
                                )}
                                {can.manage ? (
                                    <button type="button" aria-label={t('acad.common.remove')} className="text-slate-400 hover:text-red-600" onClick={() => remove(t, route('academics.levels.destroy', l.id))}>
                                        ×
                                    </button>
                                ) : null}
                            </span>
                        ))}
                    </div>
                    {can.manage ? <AddLevel /> : null}
                </Card>

                <Card title={t('academics.classes')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <Th>{t('academics.class.name')}</Th>
                                    <Th>{t('academics.class.level')}</Th>
                                    <Th>{t('academics.class.stream')}</Th>
                                    <Th>{t('academics.class.classTeacher')}</Th>
                                    <Th>{t('academics.class.enrolment')}</Th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {classes.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-3 py-6 text-center text-slate-500">
                                            {t('academics.noClasses')}
                                        </td>
                                    </tr>
                                ) : null}
                                {classes.map((c) => (
                                    <ClassRow key={c.id} item={c} teachers={teachers} canManage={can.manage} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {can.manage ? <AddClass levels={levels} teachers={teachers} /> : null}
                </Card>

                <Card title={t('academics.subjects')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <Th>{t('academics.subject.name')}</Th>
                                    <Th>{t('acad.common.code')}</Th>
                                    <Th>{t('academics.subject.department')}</Th>
                                    <Th>{t('academics.subject.classes')}</Th>
                                    <Th>{t('academics.subject.teachers')}</Th>
                                    <Th>{t('academics.subject.specialistShort')}</Th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-3 py-6 text-center text-slate-500">
                                            {t('academics.noSubjects')}
                                        </td>
                                    </tr>
                                ) : null}
                                {subjects.map((s) => (
                                    <SubjectRow key={s.id} item={s} departments={departments} canManage={can.manage} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {can.manage ? <AddSubject departments={departments} /> : null}
                </Card>

                <Card title={t('academics.departments')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <Th>{t('academics.department.name')}</Th>
                                    <Th>{t('acad.common.code')}</Th>
                                    <Th>{t('academics.department.head')}</Th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {departments.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-3 py-6 text-center text-slate-500">
                                            {t('academics.noDepartments')}
                                        </td>
                                    </tr>
                                ) : null}
                                {departments.map((d) => (
                                    <DepartmentRow key={d.id} item={d} teachers={teachers} canManage={can.manage} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {can.manage ? <AddDepartment /> : null}
                </Card>
            </div>
        </AppShell>
    );
}
