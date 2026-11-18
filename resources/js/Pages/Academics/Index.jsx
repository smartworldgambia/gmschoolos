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

const ARRANGEMENTS = ['homeroom', 'activity', 'learning_centre', 'specialist'];

function arrangementLabel(t, value) {
    const key = ARRANGEMENTS.includes(value) ? value : 'homeroom';
    return t(`academics.ecd.arrangement.${key}`);
}

function educatorIds(item, role) {
    return (item.educators ?? []).filter((e) => e.role === role).map((e) => String(e.user_id));
}

function MultiTeacherSelect({ label, value, onChange, teachers, error }) {
    const { t } = useLocale();
    const selected = new Set((value ?? []).map(String));

    return (
        <Field label={label} error={error}>
            <div className="max-h-36 min-w-[12rem] overflow-y-auto rounded-md border border-slate-200 bg-white px-2 py-1.5">
                {teachers.length === 0 ? <p className="text-xs text-slate-500">{t('acad.common.none')}</p> : null}
                {teachers.map((x) => (
                    <label key={x.id} className="flex items-center gap-2 py-0.5 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            className="rounded border-gray-300"
                            checked={selected.has(String(x.id))}
                            onChange={(e) => {
                                const next = new Set(selected);
                                if (e.target.checked) next.add(String(x.id));
                                else next.delete(String(x.id));
                                onChange([...next]);
                            }}
                        />
                        <span className="whitespace-nowrap">{x.name}</span>
                    </label>
                ))}
            </div>
        </Field>
    );
}

function CentresEditor({ centres, onChange }) {
    const { t } = useLocale();
    const list = Array.isArray(centres) ? centres : [];

    return (
        <div className="w-full space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('academics.ecd.centres')}</p>
            {list.map((c, idx) => (
                <div key={c.key ?? idx} className="flex flex-wrap items-center gap-2">
                    <Input
                        value={c.name ?? ''}
                        placeholder={t('academics.ecd.centreName')}
                        onChange={(e) => {
                            const next = list.map((row, i) => (i === idx ? { ...row, name: e.target.value } : row));
                            onChange(next);
                        }}
                        className="!w-48"
                    />
                    <button
                        type="button"
                        className="text-sm text-red-600 hover:underline"
                        onClick={() => onChange(list.filter((_, i) => i !== idx))}
                    >
                        {t('acad.common.remove')}
                    </button>
                </div>
            ))}
            <button
                type="button"
                className="btn-outline"
                onClick={() =>
                    onChange([
                        ...list,
                        { key: `centre_${Date.now()}`, name: '', active: true },
                    ])
                }
            >
                {t('academics.ecd.addCentre')}
            </button>
        </div>
    );
}

function AddLevel({ ecd }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        code: '',
        teaching_model: '',
        teaching_arrangement: '',
        age_min_months: '',
        age_max_months: '',
        description: '',
        is_active: true,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('academics.levels.store'), {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                    transform: (d) => {
                        const payload = { name: d.name, code: d.code };
                        if (ecd) {
                            if (d.teaching_arrangement) payload.teaching_arrangement = d.teaching_arrangement;
                            if (d.age_min_months !== '') payload.age_min_months = Number(d.age_min_months);
                            if (d.age_max_months !== '') payload.age_max_months = Number(d.age_max_months);
                            if (d.description) payload.description = d.description;
                            payload.is_active = !!d.is_active;
                        } else if (d.teaching_model) {
                            payload.teaching_model = d.teaching_model;
                        }
                        return payload;
                    },
                });
            }}
            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
        >
            <ErrorSummary errors={errors} />
            <Field label={ecd ? t('academics.ecd.levelName') : t('academics.level.name')}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
            </Field>
            <Field label={t('acad.common.code')}>
                <Input value={data.code} onChange={(e) => setData('code', e.target.value)} className="!w-28" />
            </Field>
            {ecd ? (
                <>
                    <Field label={t('academics.ecd.ageMin')}>
                        <Input
                            type="number"
                            min="0"
                            value={data.age_min_months}
                            onChange={(e) => setData('age_min_months', e.target.value)}
                            className="!w-28"
                        />
                    </Field>
                    <Field label={t('academics.ecd.ageMax')}>
                        <Input
                            type="number"
                            min="0"
                            value={data.age_max_months}
                            onChange={(e) => setData('age_max_months', e.target.value)}
                            className="!w-28"
                        />
                    </Field>
                    <Field label={t('academics.ecd.arrangement')}>
                        <Select
                            value={data.teaching_arrangement}
                            onChange={(e) => setData('teaching_arrangement', e.target.value)}
                            className="!w-auto"
                        >
                            <option value="">{t('acad.common.choose')}</option>
                            {ARRANGEMENTS.map((a) => (
                                <option key={a} value={a}>
                                    {arrangementLabel(t, a)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('academics.ecd.description')}>
                        <Input value={data.description} onChange={(e) => setData('description', e.target.value)} className="!w-56" />
                    </Field>
                    <label className="inline-flex items-center gap-2 pb-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            className="rounded border-gray-300"
                            checked={!!data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        {t('academics.ecd.active')}
                    </label>
                </>
            ) : (
                <Field label={t('academics.level.model')}>
                    <Select value={data.teaching_model} onChange={(e) => setData('teaching_model', e.target.value)} className="!w-auto">
                        <option value="">{t('acad.common.choose')}</option>
                        <option value="homeroom">{t('academics.level.homeroomShort')}</option>
                        <option value="specialist">{t('academics.level.specialistShort')}</option>
                    </Select>
                </Field>
            )}
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('acad.common.add')}
            </button>
        </form>
    );
}

function LevelChip({ level, ecd, canManage }) {
    const { t } = useLocale();
    const age =
        ecd && level.age_min_months != null && level.age_max_months != null
            ? t('academics.ecd.ageRange', { min: level.age_min_months, max: level.age_max_months })
            : null;

    return (
        <span
            className={`inline-flex flex-wrap items-center gap-2 rounded-full px-3 py-1 text-sm text-navy ${
                ecd && level.is_active === false ? 'bg-slate-50 text-slate-400' : 'bg-slate-100'
            }`}
        >
            <span className="font-medium">{level.name}</span>
            {age ? <span className="text-xs text-slate-500">{age}</span> : null}
            {canManage ? (
                ecd ? (
                    <select
                        value={level.teaching_arrangement || 'homeroom'}
                        aria-label={t('academics.ecd.arrangement')}
                        className="max-w-[11rem] rounded-md border-0 bg-transparent py-0 pe-6 text-xs text-slate-600"
                        onChange={(e) =>
                            router.put(
                                route('academics.levels.update', level.id),
                                { teaching_arrangement: e.target.value },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {ARRANGEMENTS.map((a) => (
                            <option key={a} value={a}>
                                {arrangementLabel(t, a)}
                            </option>
                        ))}
                    </select>
                ) : (
                    <select
                        value={level.teaching_model === 'specialist' ? 'specialist' : 'homeroom'}
                        aria-label={t('academics.level.model')}
                        className="max-w-[9rem] rounded-md border-0 bg-transparent py-0 pe-6 text-xs text-slate-600"
                        onChange={(e) =>
                            router.put(
                                route('academics.levels.update', level.id),
                                { teaching_model: e.target.value },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <option value="homeroom">{t('academics.level.homeroomShort')}</option>
                        <option value="specialist">{t('academics.level.specialistShort')}</option>
                    </select>
                )
            ) : (
                <span className="text-xs text-slate-500">
                    {ecd
                        ? arrangementLabel(t, level.teaching_arrangement)
                        : t(level.teaching_model === 'homeroom' ? 'academics.level.homeroomShort' : 'academics.level.specialistShort')}
                </span>
            )}
            {canManage ? (
                <button
                    type="button"
                    aria-label={t('acad.common.remove')}
                    className="text-slate-400 hover:text-red-600"
                    onClick={() => remove(t, route('academics.levels.destroy', level.id))}
                >
                    ×
                </button>
            ) : null}
        </span>
    );
}

function ClassRow({ item, teachers, canManage, ecd, labels }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors } = useForm({
        name: item.name,
        stream: item.stream ?? '',
        capacity: item.capacity ?? '',
        class_teacher_id: item.class_teacher_id ?? '',
        teaching_arrangement: item.teaching_arrangement ?? '',
        assistant_ids: educatorIds(item, 'assistant'),
        specialist_ids: educatorIds(item, 'specialist'),
        learning_centres: item.learning_centres ?? [],
    });

    const assistants = (item.educators ?? []).filter((e) => e.role === 'assistant').map((e) => e.name).filter(Boolean);
    const specialists = (item.educators ?? []).filter((e) => e.role === 'specialist').map((e) => e.name).filter(Boolean);
    const showCentres = ecd && (data.teaching_arrangement || item.teaching_arrangement) === 'learning_centre';

    if (!editing) {
        return (
            <tr className="border-t border-slate-100">
                <td className="px-3 py-2 font-medium text-navy">{item.name}</td>
                <td className="px-3 py-2 text-slate-600">{item.level ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">{item.stream ?? '—'}</td>
                <td className="px-3 py-2 text-slate-600">
                    <div>{item.teacher ?? '—'}</div>
                    {ecd && assistants.length ? (
                        <div className="text-xs text-slate-500">
                            {t('academics.ecd.assistants')}: {assistants.join(', ')}
                        </div>
                    ) : null}
                    {ecd && specialists.length ? (
                        <div className="text-xs text-slate-500">
                            {t('academics.ecd.specialists')}: {specialists.join(', ')}
                        </div>
                    ) : null}
                </td>
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
                            <button
                                type="button"
                                className="text-red-600 hover:underline"
                                onClick={() => remove(t, route('academics.classes.destroy', item.id))}
                            >
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
                        put(route('academics.classes.update', item.id), {
                            preserveScroll: true,
                            onSuccess: () => setEditing(false),
                            transform: (d) => {
                                const payload = {
                                    name: d.name,
                                    stream: d.stream,
                                    capacity: d.capacity,
                                    class_teacher_id: d.class_teacher_id || null,
                                };
                                if (ecd) {
                                    payload.teaching_arrangement = d.teaching_arrangement || null;
                                    payload.assistant_ids = d.assistant_ids;
                                    payload.specialist_ids = d.specialist_ids;
                                    payload.learning_centres = (d.learning_centres ?? [])
                                        .filter((c) => (c.name ?? '').trim())
                                        .map((c) => ({
                                            key: c.key || undefined,
                                            name: c.name.trim(),
                                            active: c.active !== false,
                                        }));
                                }
                                return payload;
                            },
                        });
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
                    <Field label={labels.classTeacher} error={errors.class_teacher_id}>
                        <Select value={data.class_teacher_id} onChange={(e) => setData('class_teacher_id', e.target.value)}>
                            <option value="">{t('acad.common.none')}</option>
                            {teachers.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {ecd ? (
                        <>
                            <Field label={t('academics.ecd.arrangement')} error={errors.teaching_arrangement}>
                                <Select
                                    value={data.teaching_arrangement}
                                    onChange={(e) => setData('teaching_arrangement', e.target.value)}
                                    className="!w-auto"
                                >
                                    <option value="">{t('acad.common.choose')}</option>
                                    {ARRANGEMENTS.map((a) => (
                                        <option key={a} value={a}>
                                            {arrangementLabel(t, a)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <MultiTeacherSelect
                                label={t('academics.ecd.assistants')}
                                value={data.assistant_ids}
                                onChange={(ids) => setData('assistant_ids', ids)}
                                teachers={teachers}
                                error={errors.assistant_ids}
                            />
                            <MultiTeacherSelect
                                label={t('academics.ecd.specialists')}
                                value={data.specialist_ids}
                                onChange={(ids) => setData('specialist_ids', ids)}
                                teachers={teachers}
                                error={errors.specialist_ids}
                            />
                            {showCentres ? (
                                <CentresEditor centres={data.learning_centres} onChange={(next) => setData('learning_centres', next)} />
                            ) : null}
                        </>
                    ) : null}
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

function AddClass({ levels, teachers, ecd, labels }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        level_id: '',
        name: '',
        stream: '',
        capacity: '',
        class_teacher_id: '',
        teaching_arrangement: '',
        assistant_ids: [],
        specialist_ids: [],
        learning_centres: [],
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('academics.classes.store'), {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                    transform: (d) => {
                        const payload = {
                            level_id: d.level_id,
                            name: d.name,
                            stream: d.stream,
                            capacity: d.capacity,
                            class_teacher_id: d.class_teacher_id || null,
                        };
                        if (ecd) {
                            if (d.teaching_arrangement) payload.teaching_arrangement = d.teaching_arrangement;
                            payload.assistant_ids = d.assistant_ids;
                            payload.specialist_ids = d.specialist_ids;
                            payload.learning_centres = (d.learning_centres ?? [])
                                .filter((c) => (c.name ?? '').trim())
                                .map((c) => ({
                                    key: c.key || undefined,
                                    name: c.name.trim(),
                                    active: c.active !== false,
                                }));
                        }
                        return payload;
                    },
                });
            }}
            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
        >
            <ErrorSummary errors={errors} />
            <Field label={labels.classLevel}>
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
            <Field label={labels.classTeacher} error={errors.class_teacher_id}>
                <Select value={data.class_teacher_id} onChange={(e) => setData('class_teacher_id', e.target.value)}>
                    <option value="">{t('acad.common.none')}</option>
                    {teachers.map((x) => (
                        <option key={x.id} value={x.id}>
                            {x.name}
                        </option>
                    ))}
                </Select>
            </Field>
            {ecd ? (
                <>
                    <Field label={t('academics.ecd.arrangement')}>
                        <Select
                            value={data.teaching_arrangement}
                            onChange={(e) => setData('teaching_arrangement', e.target.value)}
                            className="!w-auto"
                        >
                            <option value="">{t('acad.common.choose')}</option>
                            {ARRANGEMENTS.map((a) => (
                                <option key={a} value={a}>
                                    {arrangementLabel(t, a)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <MultiTeacherSelect
                        label={t('academics.ecd.assistants')}
                        value={data.assistant_ids}
                        onChange={(ids) => setData('assistant_ids', ids)}
                        teachers={teachers}
                    />
                    <MultiTeacherSelect
                        label={t('academics.ecd.specialists')}
                        value={data.specialist_ids}
                        onChange={(ids) => setData('specialist_ids', ids)}
                        teachers={teachers}
                    />
                    {data.teaching_arrangement === 'learning_centre' ? (
                        <CentresEditor centres={data.learning_centres} onChange={(next) => setData('learning_centres', next)} />
                    ) : null}
                </>
            ) : null}
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('acad.common.add')}
            </button>
        </form>
    );
}

function SubjectRow({ item, departments, canManage, ecd, labels }) {
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
                <td className="px-3 py-2 font-medium text-navy">
                    <span className="inline-flex flex-wrap items-center gap-2">
                        {item.name}
                        {ecd && item.domain_kind === 'learning_domain' ? (
                            <span className="rounded-full bg-teal/10 px-2 py-0.5 text-xs font-medium text-teal whitespace-nowrap">
                                {t('academics.ecd.domainBadge')}
                            </span>
                        ) : null}
                    </span>
                </td>
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
                            <button
                                type="button"
                                className="text-red-600 hover:underline"
                                onClick={() => remove(t, route('academics.subjects.destroy', item.id))}
                            >
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
                    <Field label={labels.subjectName} error={errors.name}>
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

function AddSubject({ departments, ecd, labels }) {
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
            <Field label={labels.subjectName}>
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
            {!ecd ? <p className="w-full text-xs text-slate-500">{t('academics.subject.lmsHint')}</p> : null}
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

export default function Index({
    classes = [],
    subjects = [],
    levels = [],
    departments = [],
    teachers = [],
    can = {},
    links = {},
    nav = [],
    user,
    school,
    academicYear,
    academicBand = 'standard',
}) {
    const { t } = useLocale();
    const ecd = academicBand === 'nursery';
    const labels = {
        title: ecd ? t('academics.ecd.title') : t('academics.title'),
        subtitle: ecd ? t('academics.ecd.subtitle') : t('academics.subtitle'),
        levels: ecd ? t('academics.ecd.levels') : t('academics.levels'),
        classes: ecd ? t('academics.ecd.classes') : t('academics.classes'),
        subjects: ecd ? t('academics.ecd.subjects') : t('academics.subjects'),
        classLevel: ecd ? t('academics.ecd.classLevel') : t('academics.class.level'),
        classTeacher: ecd ? t('academics.ecd.classTeacher') : t('academics.class.classTeacher'),
        subjectName: ecd ? t('academics.ecd.subjectName') : t('academics.subject.name'),
        subjectTeachers: ecd ? t('academics.ecd.subjectTeachers') : t('academics.subject.teachers'),
        allocation: ecd ? t('academics.ecd.link.allocation') : t('academics.link.allocation'),
        promotion: ecd ? t('academics.ecd.link.promotion') : t('academics.link.promotion'),
    };

    return (
        <AppShell title={labels.title} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={labels.title} />
            <PageHeader
                title={labels.title}
                subtitle={labels.subtitle}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Link href={links.allocation} className="btn-outline">
                            {labels.allocation}
                        </Link>
                        <Link href={links.calendar} className="btn-outline">
                            {t('academics.link.calendar')}
                        </Link>
                        {links.promotion ? (
                            <Link href={links.promotion} className="btn-outline">
                                {labels.promotion}
                            </Link>
                        ) : null}
                    </div>
                }
            />

            <div className="space-y-6">
                <Card title={labels.levels}>
                    <div className="flex flex-wrap gap-2">
                        {levels.length === 0 ? <p className="text-sm text-slate-500">{t('academics.noLevels')}</p> : null}
                        {levels.map((l) => (
                            <LevelChip key={l.id} level={l} ecd={ecd} canManage={can.manage} />
                        ))}
                    </div>
                    {can.manage ? <AddLevel ecd={ecd} /> : null}
                </Card>

                <Card title={labels.classes}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <Th>{t('academics.class.name')}</Th>
                                    <Th>{labels.classLevel}</Th>
                                    <Th>{t('academics.class.stream')}</Th>
                                    <Th>{labels.classTeacher}</Th>
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
                                    <ClassRow key={c.id} item={c} teachers={teachers} canManage={can.manage} ecd={ecd} labels={labels} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {can.manage ? <AddClass levels={levels} teachers={teachers} ecd={ecd} labels={labels} /> : null}
                </Card>

                <Card title={labels.subjects}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <Th>{labels.subjectName}</Th>
                                    <Th>{t('acad.common.code')}</Th>
                                    <Th>{t('academics.subject.department')}</Th>
                                    <Th>{t('academics.subject.classes')}</Th>
                                    <Th>{labels.subjectTeachers}</Th>
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
                                    <SubjectRow
                                        key={s.id}
                                        item={s}
                                        departments={departments}
                                        canManage={can.manage}
                                        ecd={ecd}
                                        labels={labels}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {can.manage ? <AddSubject departments={departments} ecd={ecd} labels={labels} /> : null}
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
