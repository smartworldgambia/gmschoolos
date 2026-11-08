import { Card, ErrorSummary, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

/** Mark sheet for one class + subject. Open for entry in draft; a publisher may correct published marks with a reason. */
function MarkSheet({ examination, categories, selected, sheet, urls, can }) {
    const { t } = useLocale();
    const published = examination.status === 'published';
    const editable = (examination.status === 'draft' && can.mark) || (published && can.publish);
    const { data, setData, post, processing, errors } = useForm({
        class_id: selected.class_id,
        subject_id: selected.subject_id,
        reason: '',
        rows: sheet.map((r) => ({ student_id: r.student_id, scores: { ...Object.fromEntries(categories.map((c) => [c.id, r.scores[c.id] ?? ''])) }, remark: r.remark ?? '' })),
    });

    const setScore = (i, id, value) => setData('rows', data.rows.map((r, j) => (j === i ? { ...r, scores: { ...r.scores, [id]: value } } : r)));
    const setRemark = (i, value) => setData('rows', data.rows.map((r, j) => (j === i ? { ...r, remark: value } : r)));

    // Preview only — the server recomputes and is authoritative.
    const preview = (row) => {
        let total = 0;
        let any = false;
        categories.forEach((c) => {
            const v = row.scores[c.id];
            if (v !== '' && v !== null && v !== undefined && Number(c.max_score) > 0) {
                total += (Number(v) / Number(c.max_score)) * Number(c.weight);
                any = true;
            }
        });

        return any ? Math.round(total * 100) / 100 : null;
    };

    function submit(e) {
        e.preventDefault();
        post(urls.marks, { preserveScroll: true });
    }

    if (sheet.length === 0) {
        return <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('results.noLearners')}</p>;
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            <ErrorSummary errors={errors} />
            {published && can.publish ? <p className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">{t('results.correctionNotice')}</p> : null}
            {!editable ? <p className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">{t('results.readOnly')}</p> : null}

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('students.col.name')}</th>
                            {categories.map((c) => (
                                <th key={c.id} className="px-4 py-3 text-start whitespace-nowrap">
                                    {c.name}
                                    <span className="block text-[10px] font-normal normal-case text-slate-400">
                                        {t('results.outOf', { max: Number(c.max_score) })} · {Number(c.weight)}%
                                    </span>
                                </th>
                            ))}
                            <th className="px-4 py-3 text-start">{t('results.total')}</th>
                            <th className="px-4 py-3 text-start">{t('results.grade')}</th>
                            <th className="px-4 py-3 text-start">{t('results.remark')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sheet.map((r, i) => {
                            const row = data.rows[i];
                            const live = preview(row);

                            return (
                                <tr key={r.student_id} className="border-t border-slate-100">
                                    <td className="px-4 py-2">
                                        <p className="font-medium text-navy">{r.name}</p>
                                        <p className="text-xs text-slate-400">{r.student_number}</p>
                                    </td>
                                    {categories.map((c) => (
                                        <td key={c.id} className="px-4 py-2">
                                            <Input type="number" inputMode="decimal" step="0.01" min="0" max={c.max_score} value={row.scores[c.id]} onChange={(e) => setScore(i, c.id, e.target.value)} disabled={!editable} className="!w-24" />
                                        </td>
                                    ))}
                                    <td className="px-4 py-2 font-semibold">{live ?? r.total ?? '—'}</td>
                                    <td className="px-4 py-2">
                                        {r.letter ?? '—'}
                                        {r.revised ? <span className="ms-1 text-xs text-amber-600">{t('results.revised')}</span> : null}
                                    </td>
                                    <td className="px-4 py-2">
                                        <Input value={row.remark} onChange={(e) => setRemark(i, e.target.value)} disabled={!editable} maxLength={255} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {editable ? (
                <div className="flex flex-wrap items-end gap-3">
                    {published ? (
                        <div className="min-w-[16rem] flex-1">
                            <label className="mb-1 block text-sm font-medium text-slate-700">{t('results.correctionReason')}</label>
                            <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} minLength={5} maxLength={255} required />
                        </div>
                    ) : null}
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {published ? t('results.saveCorrection') : t('results.saveMarks')}
                    </button>
                </div>
            ) : null}
        </form>
    );
}

function Workflow({ examination, urls, can }) {
    const { t } = useLocale();
    const [reason, setReason] = useState('');
    const post = (url, body = {}) => router.post(url, body, { preserveScroll: true });
    const s = examination.status;

    return (
        <Card title={t('results.workflow')} className="mb-6">
            <div className="flex flex-wrap items-center gap-3">
                <StatusBadge status={s} label={enumLabel(t, 'examStatus', s)} />
                {s === 'draft' && can.mark ? (
                    <button type="button" className="btn-teal" onClick={() => confirm(t('results.confirm.submit')) && post(urls.submit)}>
                        {t('results.submit')}
                    </button>
                ) : null}
                {s === 'submitted' && can.manage ? (
                    <button type="button" className="btn-teal" onClick={() => post(urls.approve)}>
                        {t('acad.common.approve')}
                    </button>
                ) : null}
                {s === 'approved' && can.publish ? (
                    <button type="button" className="btn-cta" onClick={() => confirm(t('results.confirm.publish')) && post(urls.publish)}>
                        {t('results.publish')}
                    </button>
                ) : null}
                {s === 'approved' && can.manage && !can.publish ? <span className="text-sm text-slate-500">{t('results.awaitingPublisher')}</span> : null}
            </div>

            {(s === 'submitted' || s === 'approved') && can.manage ? (
                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t('results.returnReason')} className="!w-72" maxLength={255} />
                    <button type="button" className="btn-outline" disabled={reason.trim().length < 5} onClick={() => post(urls.return, { reason })}>
                        {t('results.return')}
                    </button>
                </div>
            ) : null}
            {examination.returned_reason && s === 'draft' ? (
                <p className="mt-3 text-sm text-amber-700">
                    {t('results.returnedBecause')}: {examination.returned_reason}
                </p>
            ) : null}
            <p className="mt-3 text-xs text-slate-400">{t(`results.hint.${s}`)}</p>
        </Card>
    );
}

export default function Show({ examination, categories = [], pairs = [], selected, sheet = [], review = [], can = {}, urls = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const value = selected ? `${selected.class_id}:${selected.subject_id}` : '';

    function pick(v) {
        const [class_id, subject_id] = v.split(':');
        router.get(route('examinations.show', examination.id), { class_id, subject_id }, { preserveScroll: true });
    }

    return (
        <AppShell title={examination.name} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={examination.name} />
            <PageHeader
                title={examination.name}
                subtitle={[examination.term, enumLabel(t, 'examType', examination.type)].filter(Boolean).join(' · ')}
                actions={
                    <>
                        {urls.history ? (
                            <Link href={urls.history} className="btn-outline">
                                {t('results.history')}
                            </Link>
                        ) : null}
                        <Link href={route('examinations.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <Workflow examination={examination} urls={urls} can={can} />

            <Card title={t('results.markSheet')} className="mb-6">
                {pairs.length === 0 ? (
                    <p className="text-sm text-slate-500">{t('results.noAllocation')}</p>
                ) : (
                    <>
                        <div className="mb-4">
                            <Select value={value} onChange={(e) => pick(e.target.value)} className="!w-auto" aria-label={t('results.markSheet')}>
                                {pairs.map((p) => (
                                    <option key={`${p.class_id}:${p.subject_id}`} value={`${p.class_id}:${p.subject_id}`}>
                                        {p.class} — {p.subject}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        {selected ? <MarkSheet key={`${value}-${examination.status}-${sheet.length}`} examination={examination} categories={categories} selected={selected} sheet={sheet} urls={urls} can={can} /> : null}
                    </>
                )}
            </Card>

            {review.length > 0 ? (
                <Card title={t('results.review')}>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-start">{t('students.col.name')}</th>
                                    <th className="px-3 py-2 text-start">{t('results.subject')}</th>
                                    <th className="px-3 py-2 text-start">{t('results.total')}</th>
                                    <th className="px-3 py-2 text-start">{t('results.grade')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {review.map((g) => (
                                    <tr key={g.id} className="border-t border-slate-100">
                                        <td className="px-3 py-1.5">{g.student}</td>
                                        <td className="px-3 py-1.5">{g.subject}</td>
                                        <td className="px-3 py-1.5">{g.total ?? '—'}</td>
                                        <td className="px-3 py-1.5">
                                            {g.letter_grade ?? '—'}
                                            {g.revised ? <span className="ms-1 text-xs text-amber-600">{t('results.revised')}</span> : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            ) : null}
        </AppShell>
    );
}
