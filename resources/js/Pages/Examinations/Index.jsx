import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Index({ examinations = [], terms = [], types = [], storeUrl, can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', term_id: '', type: types[0] ?? 'end_of_term', starts_on: '', ends_on: '' });

    function submit(e) {
        e.preventDefault();
        post(storeUrl, { preserveScroll: true, onSuccess: () => reset() });
    }

    return (
        <AppShell title={t('results.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('results.title')} />
            <PageHeader
                title={t('results.title')}
                subtitle={t('results.subtitle')}
                actions={
                    can.manage ? (
                        <Link href={route('examinations.settings')} className="btn-outline">
                            {t('results.settings')}
                        </Link>
                    ) : null
                }
            />

            {can.manage ? (
                <Card title={t('results.new')} className="mb-6">
                    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="sm:col-span-2 lg:col-span-5">
                            <ErrorSummary errors={errors} />
                        </div>
                        <Field label={t('results.col.name')} className="lg:col-span-2">
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required maxLength={150} />
                        </Field>
                        <Field label={t('results.col.term')}>
                            <Select value={data.term_id} onChange={(e) => setData('term_id', e.target.value)}>
                                <option value="">—</option>
                                {terms.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('results.col.type')}>
                            <Select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                {types.map((x) => (
                                    <option key={x} value={x}>
                                        {enumLabel(t, 'examType', x)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <div className="flex items-end">
                            <button type="submit" className="btn-teal" disabled={processing}>
                                {t('results.create')}
                            </button>
                        </div>
                    </form>
                </Card>
            ) : null}

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('results.col.name')}</th>
                            <th className="px-4 py-3 text-start">{t('results.col.term')}</th>
                            <th className="px-4 py-3 text-start">{t('results.col.type')}</th>
                            <th className="px-4 py-3 text-start">{t('results.col.marks')}</th>
                            <th className="px-4 py-3 text-start">{t('students.col.status')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {examinations.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                                    {t('results.empty')}
                                </td>
                            </tr>
                        ) : null}
                        {examinations.map((x) => (
                            <tr key={x.id} className="border-t border-slate-100">
                                <td className="px-4 py-2 font-medium">
                                    <Link href={x.url} className="text-navy hover:underline">
                                        {x.name}
                                    </Link>
                                </td>
                                <td className="px-4 py-2">{x.term ?? '—'}</td>
                                <td className="px-4 py-2">{enumLabel(t, 'examType', x.type)}</td>
                                <td className="px-4 py-2">{x.grades_count}</td>
                                <td className="px-4 py-2">
                                    <StatusBadge status={x.status} label={enumLabel(t, 'examStatus', x.status)} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
