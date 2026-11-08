import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Create({ students = [], q = '', types = [], templates = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [search, setSearch] = useState(q);
    const { data, setData, post, processing, errors } = useForm({ type: types[0] ?? 'transcript_g9', student_id: '', template_id: '', custom_text: '' });
    const isLetter = ['testimonial', 'cover_letter'].includes(data.type);
    const choices = templates.filter((x) => x.type === data.type);

    function find(e) {
        e.preventDefault();
        router.get(route('records.create'), { q: search }, { preserveState: true, replace: true });
    }

    function submit(e) {
        e.preventDefault();
        post(route('records.store'));
    }

    return (
        <AppShell title={t('rec.new')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('rec.new')} />
            <PageHeader
                title={t('rec.new')}
                subtitle={t('rec.newSubtitle')}
                actions={
                    <Link href={route('records.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title={t('rec.step1')}>
                    <form onSubmit={find} className="mb-3 flex gap-2">
                        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('rec.studentSearch')} />
                        <button type="submit" className="btn-outline">
                            {t('acad.common.search')}
                        </button>
                    </form>
                    {q !== '' && students.length === 0 ? <p className="text-sm text-slate-500">{t('rec.noStudents')}</p> : null}
                    <ul className="divide-y divide-slate-100">
                        {students.map((s) => (
                            <li key={s.id}>
                                <label className="flex cursor-pointer items-center gap-3 px-2 py-2 hover:bg-slate-50">
                                    <input type="radio" name="student" checked={String(data.student_id) === String(s.id)} onChange={() => setData('student_id', s.id)} />
                                    <span>
                                        <span className="font-medium text-navy">{s.name}</span>
                                        <span className="block text-xs text-slate-500">
                                            {s.student_number}
                                            {s.class ? ` · ${s.class}` : ''} · {enumLabel(t, 'studentStatus', s.status)}
                                        </span>
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card title={t('rec.step2')}>
                    <form onSubmit={submit} className="space-y-4">
                        <ErrorSummary errors={errors} />
                        <Field label={t('rec.col.type')}>
                            <Select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                {types.map((x) => (
                                    <option key={x} value={x}>
                                        {enumLabel(t, 'rec.type', x)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <p className="text-sm text-slate-600">{data.type === 'transcript_g9' ? t('rec.help.g9') : data.type === 'transcript_g12' ? t('rec.help.g12') : t('rec.help.letter')}</p>
                        {isLetter ? (
                            <>
                                <Field label={t('rec.template')}>
                                    <Select value={data.template_id} onChange={(e) => setData('template_id', e.target.value)}>
                                        <option value="">{t('rec.defaultTemplate')}</option>
                                        {choices.map((x) => (
                                            <option key={x.id} value={x.id}>
                                                {x.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('rec.customText')} hint={t('rec.customTextHint')}>
                                    <Textarea rows={3} value={data.custom_text} onChange={(e) => setData('custom_text', e.target.value)} maxLength={3000} />
                                </Field>
                            </>
                        ) : null}
                        <button type="submit" className="btn-teal" disabled={processing || !data.student_id}>
                            {t('rec.createDraft')}
                        </button>
                    </form>
                </Card>
            </div>
        </AppShell>
    );
}
