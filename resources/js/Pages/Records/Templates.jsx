import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Templates({ templates = [], types = [], placeholders = [], defaults = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm({ type: types[0] ?? 'testimonial', name: '', body: '', is_default: false, is_active: true });

    function startNew() {
        reset();
        setData({ type: types[0] ?? 'testimonial', name: '', body: defaults[types[0]] ?? '', is_default: false, is_active: true });
        setEditing('new');
    }

    function startEdit(tpl) {
        setData({ type: tpl.type, name: tpl.name, body: tpl.body, is_default: tpl.is_default, is_active: tpl.is_active });
        setEditing(tpl.id);
    }

    function submit(e) {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing === 'new' ? post(route('records.templates.store'), opts) : put(route('records.templates.update', editing), opts);
    }

    return (
        <AppShell title={t('rec.templatesTitle')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('rec.templatesTitle')} />
            <PageHeader
                title={t('rec.templatesTitle')}
                subtitle={t('rec.templatesSubtitle')}
                actions={
                    <>
                        <button type="button" className="btn-teal" onClick={startNew}>
                            {t('rec.newTemplate')}
                        </button>
                        <Link href={route('records.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <p className="mb-6 text-sm text-slate-600">
                {t('rec.placeholders')}:{' '}
                {placeholders.map((p) => (
                    <code key={p} className="me-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs" dir="ltr">{`{{${p}}}`}</code>
                ))}
            </p>

            {editing !== null ? (
                <Card title={editing === 'new' ? t('rec.newTemplate') : t('rec.editTemplate')} className="mb-6">
                    <form onSubmit={submit} className="space-y-4">
                        <ErrorSummary errors={errors} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('rec.col.type')}>
                                <Select value={data.type} onChange={(e) => setData('type', e.target.value)} disabled={editing !== 'new'}>
                                    {types.map((x) => (
                                        <option key={x} value={x}>
                                            {enumLabel(t, 'rec.type', x)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('rec.templateName')}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required maxLength={150} />
                            </Field>
                        </div>
                        <Field label={t('rec.templateBody')}>
                            <Textarea rows={10} value={data.body} onChange={(e) => setData('body', e.target.value)} required maxLength={6000} />
                        </Field>
                        <div className="flex flex-wrap gap-6 text-sm">
                            <label className="flex items-center gap-2">
                                <input type="checkbox" className="rounded border-gray-300" checked={data.is_default} onChange={(e) => setData('is_default', e.target.checked)} />
                                {t('rec.templateDefault')}
                            </label>
                            <label className="flex items-center gap-2">
                                <input type="checkbox" className="rounded border-gray-300" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                                {t('ext.active')}
                            </label>
                        </div>
                        <div className="flex gap-2">
                            <button type="submit" className="btn-teal" disabled={processing}>
                                {t('acad.common.save')}
                            </button>
                            <button type="button" className="btn-outline" onClick={() => setEditing(null)}>
                                {t('acad.common.cancel')}
                            </button>
                        </div>
                    </form>
                </Card>
            ) : null}

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('rec.templateName')}</th>
                            <th className="px-4 py-3 text-start">{t('rec.col.type')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {templates.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                                    {t('rec.noTemplates')}
                                </td>
                            </tr>
                        ) : null}
                        {templates.map((x) => (
                            <tr key={x.id}>
                                <td className="px-4 py-3 font-medium text-navy">
                                    {x.name}
                                    {x.is_default ? <span className="ms-2 rounded bg-teal/10 px-1.5 py-0.5 text-xs text-teal">{t('rec.templateDefault')}</span> : null}
                                </td>
                                <td className="px-4 py-3">{enumLabel(t, 'rec.type', x.type)}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={x.is_active ? 'active' : 'inactive'} label={x.is_active ? t('ext.active') : t('ext.inactive')} />
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <button type="button" className="btn-outline" onClick={() => startEdit(x)}>
                                        {t('acad.common.edit')}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
