import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function Copy({ classes, selected }) {
    const { t } = useLocale();
    const [ids, setIds] = useState([]);
    const others = classes.filter((c) => c.id !== selected);

    function submit(e) {
        e.preventDefault();
        if (ids.length === 0 || !confirm(t('allocation.confirmCopy'))) return;
        router.post(route('academics.curriculum.copy', selected), { class_ids: ids }, { preserveScroll: true, onSuccess: () => setIds([]) });
    }

    return (
        <Card title={t('allocation.copyTitle')}>
            <p className="mb-3 text-xs text-slate-500">{t('allocation.copyHint')}</p>
            <form onSubmit={submit}>
                <div className="flex flex-wrap gap-3">
                    {others.map((c) => (
                        <label key={c.id} className="inline-flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="rounded border-gray-300"
                                checked={ids.includes(c.id)}
                                onChange={(e) => setIds(e.target.checked ? [...ids, c.id] : ids.filter((x) => x !== c.id))}
                            />
                            {c.name}
                        </label>
                    ))}
                </div>
                <button type="submit" className="btn-outline mt-3" disabled={ids.length === 0}>
                    {t('allocation.copy')}
                </button>
            </form>
        </Card>
    );
}

function AllocationInner({ classes = [], selected, curriculum = [], subjects = [], teachers = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, put, processing, errors, reset } = useForm({
        rows: curriculum.map((r) => ({ subject_id: r.subject_id, teacher_id: r.teacher_id ?? '', periods_per_week: r.periods_per_week ?? '' })),
    });
    const [addId, setAddId] = useState('');
    const subjectName = (id) => subjects.find((s) => s.id === Number(id))?.name ?? id;
    const available = subjects.filter((s) => !data.rows.some((r) => Number(r.subject_id) === s.id));
    const currentClass = classes.find((c) => c.id === selected);

    function pickClass(id) {
        router.get(route('academics.allocation'), { class_id: id }, { preserveScroll: true });
    }

    function setRow(i, key, value) {
        setData('rows', data.rows.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    }

    function save(e) {
        e.preventDefault();
        put(route('academics.curriculum.save', selected), { preserveScroll: true });
    }

    return (
        <AppShell title={t('allocation.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('allocation.title')} />
            <PageHeader
                title={t('allocation.title')}
                subtitle={t('allocation.subtitle')}
                actions={
                    <Link href={route('academics.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Select value={selected ?? ''} onChange={(e) => pickClass(e.target.value)} aria-label={t('academics.class.name')} className="!w-auto">
                    {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </Select>
                {currentClass ? (
                    <span className="text-sm text-slate-500">
                        {t('academics.class.classTeacher')}: <span className="font-medium text-navy">{currentClass.class_teacher ?? '—'}</span>
                    </span>
                ) : null}
            </div>

            {selected ? (
                <div className="space-y-6">
                    <form onSubmit={save}>
                        <Card title={t('allocation.curriculum')}>
                            <ErrorSummary errors={errors} />
                            <p className="mb-3 text-xs text-slate-500">{t('allocation.hint')}</p>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase text-slate-500">
                                            <th className="px-3 py-2 text-start">{t('academics.subject.name')}</th>
                                            <th className="px-3 py-2 text-start">{t('allocation.teacher')}</th>
                                            <th className="px-3 py-2 text-start">{t('allocation.periods')}</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.rows.length === 0 ? (
                                            <tr>
                                                <td colSpan={4} className="px-3 py-6 text-center text-slate-500">
                                                    {t('allocation.empty')}
                                                </td>
                                            </tr>
                                        ) : null}
                                        {data.rows.map((r, i) => (
                                            <tr key={r.subject_id} className="border-t border-slate-100">
                                                <td className="px-3 py-2 font-medium text-navy">{subjectName(r.subject_id)}</td>
                                                <td className="px-3 py-2">
                                                    <Select value={r.teacher_id} onChange={(e) => setRow(i, 'teacher_id', e.target.value)} disabled={!can.manage}>
                                                        <option value="">{t('allocation.unassigned')}</option>
                                                        {teachers.map((x) => (
                                                            <option key={x.id} value={x.id}>
                                                                {x.name}
                                                            </option>
                                                        ))}
                                                    </Select>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Input type="number" min="1" max="40" value={r.periods_per_week} onChange={(e) => setRow(i, 'periods_per_week', e.target.value)} className="!w-24" disabled={!can.manage} />
                                                </td>
                                                <td className="px-3 py-2 text-end">
                                                    {can.manage ? (
                                                        <button type="button" className="text-red-600 hover:underline" onClick={() => setData('rows', data.rows.filter((_, j) => j !== i))}>
                                                            {t('acad.common.remove')}
                                                        </button>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {can.manage ? (
                                <div className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                                    <Field label={t('allocation.addSubject')}>
                                        <Select value={addId} onChange={(e) => setAddId(e.target.value)}>
                                            <option value="">{t('acad.common.choose')}</option>
                                            {available.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.name}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <button
                                        type="button"
                                        className="btn-outline"
                                        disabled={!addId}
                                        onClick={() => {
                                            setData('rows', [...data.rows, { subject_id: Number(addId), teacher_id: '', periods_per_week: '' }]);
                                            setAddId('');
                                        }}
                                    >
                                        {t('acad.common.add')}
                                    </button>
                                    <button type="submit" className="btn-teal ms-auto" disabled={processing}>
                                        {t('allocation.save')}
                                    </button>
                                </div>
                            ) : null}
                        </Card>
                    </form>
                    {can.manage ? <Copy classes={classes} selected={selected} /> : null}
                </div>
            ) : (
                <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('allocation.noClass')}</p>
            )}
        </AppShell>
    );
}

export default function Allocation(props) {
    // remount per class so the editable rows always start from that class
    return <AllocationInner key={props.selected ?? 'none'} {...props} />;
}
