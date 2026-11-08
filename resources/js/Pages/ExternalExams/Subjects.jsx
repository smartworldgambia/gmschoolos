import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Subjects({ subjects = [], grades = {}, can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const add = useForm({ exam_type: 'wassce', code: '', name: '', is_active: true });
    const [editing, setEditing] = useState(null);
    const edit = useForm({ code: '', name: '', is_active: true });

    function submitAdd(e) {
        e.preventDefault();
        add.post(route('external-exams.subjects.store'), { preserveScroll: true, onSuccess: () => add.reset('code', 'name') });
    }

    function startEdit(s) {
        edit.setData({ code: s.code, name: s.name, is_active: s.is_active });
        setEditing(s.id);
    }

    function submitEdit(e) {
        e.preventDefault();
        edit.put(route('external-exams.subjects.update', editing), { preserveScroll: true, onSuccess: () => setEditing(null) });
    }

    return (
        <AppShell title={t('ext.subjectsPage')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('ext.subjectsPage')} />
            <PageHeader
                title={t('ext.subjectsPage')}
                subtitle={t('ext.subjectsSubtitle')}
                actions={
                    <Link href={route('external-exams.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            {can.manage ? (
                <Card title={t('ext.addSubject')} className="mb-6">
                    <form onSubmit={submitAdd} className="grid gap-4 sm:grid-cols-4">
                        <div className="sm:col-span-4">
                            <ErrorSummary errors={add.errors} />
                        </div>
                        <Field label={t('ext.col.exam')}>
                            <Select value={add.data.exam_type} onChange={(e) => add.setData('exam_type', e.target.value)}>
                                <option value="wassce">{t('ext.type.wassce')}</option>
                                <option value="gabece">{t('ext.type.gabece')}</option>
                            </Select>
                        </Field>
                        <Field label={t('acad.common.code')}>
                            <Input dir="ltr" value={add.data.code} onChange={(e) => add.setData('code', e.target.value)} required maxLength={30} />
                        </Field>
                        <Field label={t('ext.col.subject')} className="sm:col-span-2">
                            <Input value={add.data.name} onChange={(e) => add.setData('name', e.target.value)} required maxLength={150} />
                        </Field>
                        <div className="sm:col-span-4">
                            <button type="submit" className="btn-teal" disabled={add.processing}>
                                {t('ext.addSubjectBtn')}
                            </button>
                        </div>
                    </form>
                </Card>
            ) : null}

            <div className="mb-6 overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('ext.col.exam')}</th>
                            <th className="px-4 py-3 text-start">{t('acad.common.code')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.subject')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {subjects.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                                    {t('ext.noSubjectsYet')}
                                </td>
                            </tr>
                        ) : null}
                        {subjects.map((s) =>
                            editing === s.id ? (
                                <tr key={s.id} className="bg-slate-50">
                                    <td className="px-4 py-3">{enumLabel(t, 'ext.type', s.exam_type)}</td>
                                    <td className="px-4 py-3">
                                        <Input dir="ltr" value={edit.data.code} onChange={(e) => edit.setData('code', e.target.value)} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <Input value={edit.data.name} onChange={(e) => edit.setData('name', e.target.value)} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" className="rounded border-gray-300" checked={edit.data.is_active} onChange={(e) => edit.setData('is_active', e.target.checked)} />
                                            {t('ext.active')}
                                        </label>
                                    </td>
                                    <td className="px-4 py-3 text-end">
                                        <form onSubmit={submitEdit} className="flex justify-end gap-2">
                                            <button type="submit" className="btn-teal" disabled={edit.processing}>
                                                {t('acad.common.save')}
                                            </button>
                                            <button type="button" className="btn-outline" onClick={() => setEditing(null)}>
                                                {t('acad.common.cancel')}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            ) : (
                                <tr key={s.id}>
                                    <td className="px-4 py-3">{enumLabel(t, 'ext.type', s.exam_type)}</td>
                                    <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                        {s.code}
                                    </td>
                                    <td className="px-4 py-3 font-medium text-navy">{s.name}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={s.is_active ? 'active' : 'inactive'} label={s.is_active ? t('ext.active') : t('ext.inactive')} />
                                    </td>
                                    <td className="px-4 py-3 text-end">
                                        {can.manage ? (
                                            <div className="flex justify-end gap-2">
                                                <button type="button" className="btn-outline" onClick={() => startEdit(s)}>
                                                    {t('acad.common.edit')}
                                                </button>
                                                {!s.in_use ? (
                                                    <button type="button" className="btn-outline !text-red-700" onClick={() => router.delete(route('external-exams.subjects.destroy', s.id), { preserveScroll: true })}>
                                                        {t('acad.common.remove')}
                                                    </button>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </td>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
            </div>
            {edit.errors && Object.keys(edit.errors).length ? <ErrorSummary errors={edit.errors} /> : null}

            {can.verify ? (
                <div className="grid gap-6 lg:grid-cols-2">
                    {['wassce', 'gabece'].map((type) => (
                        <GradeList key={type} type={type} grades={grades[type] ?? []} />
                    ))}
                </div>
            ) : null}
        </AppShell>
    );
}

/** The accepted grade symbols per exam, editable per school. The GABECE list is only a starting point. */
function GradeList({ type, grades }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({ exam_type: type, grades: grades.join(', ') });

    return (
        <Card title={t('ext.gradeListTitle', { exam: t(`ext.type.${type}`) })}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('external-exams.subjects.grades'), { preserveScroll: true });
                }}
                className="space-y-3"
            >
                <ErrorSummary errors={errors} />
                <p className="text-sm text-slate-600">{type === 'gabece' ? t('ext.gradeListGabeceNote') : t('ext.gradeListWassceNote')}</p>
                <Input dir="ltr" value={data.grades} onChange={(e) => setData('grades', e.target.value)} required maxLength={300} />
                <button type="submit" className="btn-navy" disabled={processing}>
                    {t('ext.saveGrades')}
                </button>
            </form>
        </Card>
    );
}
