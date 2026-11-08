import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Index({ sessions = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        type: 'wassce',
        name: '',
        exam_year: new Date().getFullYear(),
        registration_opens_on: '',
        registration_closes_on: '',
    });

    function submit(e) {
        e.preventDefault();
        post(route('external-exams.sessions.store'), { preserveScroll: true, onSuccess: () => reset('name') });
    }

    return (
        <AppShell title={t('ext.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('ext.title')} />
            <PageHeader
                title={t('ext.title')}
                subtitle={t('ext.subtitle')}
                actions={
                    <>
                        <Link href={route('external-exams.collections.index')} className="btn-outline">
                            {t('ext.collections')}
                        </Link>
                        <Link href={route('external-exams.subjects.index')} className="btn-outline">
                            {t('ext.subjects')}
                        </Link>
                    </>
                }
            />

            <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('ext.notOfficial')}</p>

            {can.manage ? (
                <Card title={t('ext.newSession')} className="mb-6">
                    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="sm:col-span-2 lg:col-span-5">
                            <ErrorSummary errors={errors} />
                        </div>
                        <Field label={t('ext.col.exam')}>
                            <Select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                <option value="wassce">{t('ext.type.wassce')}</option>
                                <option value="gabece">{t('ext.type.gabece')}</option>
                            </Select>
                        </Field>
                        <Field label={t('ext.col.name')}>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required maxLength={150} />
                        </Field>
                        <Field label={t('ext.col.year')}>
                            <Input type="number" min="2000" max="2100" value={data.exam_year} onChange={(e) => setData('exam_year', e.target.value)} required />
                        </Field>
                        <Field label={t('ext.col.opens')}>
                            <Input type="date" value={data.registration_opens_on} onChange={(e) => setData('registration_opens_on', e.target.value)} />
                        </Field>
                        <Field label={t('ext.col.closes')}>
                            <Input type="date" value={data.registration_closes_on} onChange={(e) => setData('registration_closes_on', e.target.value)} />
                        </Field>
                        <div className="sm:col-span-2 lg:col-span-5">
                            <button type="submit" className="btn-teal" disabled={processing}>
                                {t('ext.createSession')}
                            </button>
                        </div>
                    </form>
                </Card>
            ) : null}

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('ext.col.name')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.exam')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.year')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.window')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.candidates')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {sessions.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                    {t('ext.noSessions')}
                                </td>
                            </tr>
                        ) : null}
                        {sessions.map((s) => (
                            <tr key={s.id}>
                                <td className="px-4 py-3 font-medium text-navy">{s.name}</td>
                                <td className="px-4 py-3">{enumLabel(t, 'ext.type', s.type)}</td>
                                <td className="px-4 py-3">{s.exam_year}</td>
                                <td className="px-4 py-3 text-slate-600">{s.registration_opens_on || s.registration_closes_on ? `${s.registration_opens_on ?? '…'} → ${s.registration_closes_on ?? '…'}` : '—'}</td>
                                <td className="px-4 py-3">{s.candidates_count}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={s.status} label={enumLabel(t, 'ext.sessionStatus', s.status)} />
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <Link href={route('external-exams.sessions.show', s.id)} className="btn-outline">
                                        {t('ext.open')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
