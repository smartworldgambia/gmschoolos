import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

function Inner({ classes = [], selected, students = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        action: 'promote',
        from_class_id: selected ?? '',
        to_class_id: '',
        student_ids: students.map((s) => s.id),
        reason: '',
    });
    const target = classes.find((c) => c.id === Number(data.to_class_id));
    const free = target?.capacity ? target.capacity - target.students_count : null;

    function toggle(id, on) {
        setData('student_ids', on ? [...data.student_ids, id] : data.student_ids.filter((x) => x !== id));
    }

    function submit(e) {
        e.preventDefault();
        if (!confirm(t(`promotion.confirm.${data.action}`, { n: data.student_ids.length }))) return;
        post(route('academics.promotion.store'), { preserveScroll: true, onSuccess: () => reset('reason') });
    }

    return (
        <AppShell title={t('promotion.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('promotion.title')} />
            <PageHeader
                title={t('promotion.title')}
                subtitle={t('promotion.subtitle')}
                actions={
                    <Link href={route('academics.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <div className="mb-4">
                <Field label={t('promotion.from')}>
                    <Select value={selected ?? ''} onChange={(e) => router.get(route('academics.promotion'), { class_id: e.target.value })} className="!w-auto">
                        <option value="">{t('acad.common.choose')}</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name} ({c.students_count})
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>

            {selected ? (
                <form onSubmit={submit} className="space-y-6">
                    <ErrorSummary errors={errors} />
                    <Card title={t('promotion.learners')}>
                        <div className="mb-3 flex gap-3 text-sm">
                            <button type="button" className="text-teal hover:underline" onClick={() => setData('student_ids', students.map((s) => s.id))}>
                                {t('promotion.selectAll')}
                            </button>
                            <button type="button" className="text-teal hover:underline" onClick={() => setData('student_ids', [])}>
                                {t('promotion.selectNone')}
                            </button>
                            <span className="ms-auto text-slate-500">{t('promotion.selected', { n: data.student_ids.length })}</span>
                        </div>
                        {students.length === 0 ? <p className="text-sm text-slate-500">{t('promotion.noLearners')}</p> : null}
                        <ul className="grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                            {students.map((s) => (
                                <li key={s.id}>
                                    <label className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                                        <input type="checkbox" className="rounded border-gray-300" checked={data.student_ids.includes(s.id)} onChange={(e) => toggle(s.id, e.target.checked)} />
                                        <span className="font-medium text-navy">{s.name}</span>
                                        <span className="text-xs text-slate-400">{s.student_number}</span>
                                    </label>
                                </li>
                            ))}
                        </ul>
                    </Card>

                    <Card title={t('promotion.action')}>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label={t('promotion.action')}>
                                <Select value={data.action} onChange={(e) => setData('action', e.target.value)}>
                                    {['promote', 'repeat', 'graduate'].map((a) => (
                                        <option key={a} value={a}>
                                            {enumLabel(t, 'promotion', a)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            {data.action === 'promote' ? (
                                <Field label={t('promotion.to')} hint={free !== null ? t('promotion.freePlaces', { n: free }) : undefined}>
                                    <Select value={data.to_class_id} onChange={(e) => setData('to_class_id', e.target.value)} required>
                                        <option value="">{t('acad.common.choose')}</option>
                                        {classes.filter((c) => c.id !== selected).map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            ) : null}
                            <Field label={t('acad.common.reason')} hint={t('acad.common.optional')}>
                                <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
                            </Field>
                        </div>
                        <p className="mt-3 text-xs text-slate-500">{t('promotion.note')}</p>
                        <button type="submit" className="btn-navy mt-4" disabled={processing || data.student_ids.length === 0}>
                            {t('promotion.apply')}
                        </button>
                    </Card>
                </form>
            ) : null}
        </AppShell>
    );
}

export default function Promotion(props) {
    return <Inner key={props.selected ?? 'none'} {...props} />;
}
