import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Input } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function People({ course, author, enrolments = [], candidates = [], audienceCount = 0 }) {
    const { t } = useLocale();
    const form = useForm({ student_ids: [] });
    const [q, setQ] = useState('');
    const shown = useMemo(() => {
        const needle = q.trim().toLowerCase();

        return needle ? candidates.filter((c) => `${c.name} ${c.number} ${c.class ?? ''}`.toLowerCase().includes(needle)) : candidates;
    }, [q, candidates]);
    const ids = form.data.student_ids;

    function toggle(id) {
        form.setData('student_ids', ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]);
    }

    return (
        <LmsLayout title={t('lms.people.title')} subtitle={t('lms.people.subtitle')} course={course} author={author} active="people">
            <div className="grid gap-6 lg:grid-cols-2">
                <Card
                    title={t('lms.people.enrolled')}
                    actions={
                        audienceCount > 0 ? (
                            <button type="button" className="btn-outline" onClick={() => router.post(route('lms.courses.audience.sync', course.id), {}, { preserveScroll: true })}>
                                {t('lms.people.sync')}
                            </button>
                        ) : null
                    }
                >
                    {audienceCount > 0 ? <p className="mb-3 text-xs text-slate-500">{t('lms.people.syncHelp')}</p> : null}
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-2 py-2 text-start">{t('lms.common.student')}</th>
                                    <th className="px-2 py-2 text-start">{t('lms.common.class')}</th>
                                    <th className="px-2 py-2 text-start">{t('lms.people.source')}</th>
                                    <th className="px-2 py-2 text-start">{t('lms.common.status')}</th>
                                    <th className="px-2 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {enrolments.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-2 py-6 text-center text-slate-500">
                                            {t('lms.people.none')}
                                        </td>
                                    </tr>
                                ) : null}
                                {enrolments.map((e) => (
                                    <tr key={e.id}>
                                        <td className="px-2 py-2">
                                            <p className="font-medium text-navy">{e.name}</p>
                                            <p className="text-xs text-slate-500">{e.number}</p>
                                        </td>
                                        <td className="px-2 py-2">{e.class ?? '—'}</td>
                                        <td className="px-2 py-2">{enumLabel(t, 'lms.people.sourceType', e.source)}</td>
                                        <td className="px-2 py-2">
                                            <StatusBadge status={e.status === 'withdrawn' ? 'inactive' : 'active'} label={enumLabel(t, 'lms.people.state', e.status)} />
                                        </td>
                                        <td className="px-2 py-2 text-end">
                                            {e.status === 'active' ? (
                                                <button
                                                    type="button"
                                                    className="btn-outline !text-red-700"
                                                    onClick={() => window.confirm(t('lms.people.confirmWithdraw')) && router.delete(route('lms.courses.withdraw', [course.id, e.id]), { preserveScroll: true })}
                                                >
                                                    {t('lms.people.withdraw')}
                                                </button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card title={t('lms.people.add')}>
                    <ErrorSummary errors={form.errors} />
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('lms.people.search')} className="mb-3" />
                    <div className="max-h-80 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2">
                        {shown.length === 0 ? <p className="p-2 text-sm text-slate-500">{t('lms.people.noCandidates')}</p> : null}
                        {shown.map((c) => (
                            <label key={c.id} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" className="rounded border-gray-300" checked={ids.includes(c.id)} onChange={() => toggle(c.id)} />
                                <span className="font-medium text-navy">{c.name}</span>
                                <span className="text-xs text-slate-500">
                                    {c.number}
                                    {c.class ? ` · ${c.class}` : ''}
                                </span>
                            </label>
                        ))}
                    </div>
                    <button
                        type="button"
                        className="btn-teal mt-3"
                        disabled={form.processing || ids.length === 0}
                        onClick={() => form.post(route('lms.courses.enrol', course.id), { preserveScroll: true, onSuccess: () => form.reset() })}
                    >
                        {t('lms.people.enrol')} ({ids.length})
                    </button>
                </Card>
            </div>
        </LmsLayout>
    );
}
