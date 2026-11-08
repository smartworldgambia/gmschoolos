import InputError from '@/Components/InputError';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, messageText } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Candidate({
    candidate,
    session,
    subjects = [],
    registeredSubjectIds = [],
    results = [],
    history = [],
    documents = [],
    documentTypes = [],
    grades = [],
    collection,
    certificate,
    can = {},
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t } = useLocale();
    const index = useForm({ official_index_number: candidate.official_index_number ?? '', reason: '' });
    const subj = useForm({ subject_ids: registeredSubjectIds });
    const entry = useForm({ subject_id: '', grade: '' });
    const doc = useForm({ type: documentTypes[0] ?? 'other', file: null });
    const isWassce = session.type === 'wassce';
    const hasIndex = candidate.official_index_number !== null;
    const locked = candidate.status === 'withdrawn';
    const registered = subjects.filter((s) => registeredSubjectIds.includes(s.id));
    const unverified = results.some((r) => r.status === 'entered' || r.status === 'imported');

    function submitIndex(e) {
        e.preventDefault();
        index.post(route('external-exams.candidates.index-number', candidate.id), { preserveScroll: true, onSuccess: () => index.setData('reason', '') });
    }

    function toggleSubject(id) {
        subj.setData('subject_ids', subj.data.subject_ids.includes(id) ? subj.data.subject_ids.filter((x) => x !== id) : [...subj.data.subject_ids, id]);
    }

    function submitSubjects(e) {
        e.preventDefault();
        subj.put(route('external-exams.candidates.subjects', candidate.id), { preserveScroll: true });
    }

    function submitResult(e) {
        e.preventDefault();
        entry.post(route('external-exams.candidates.results.store', candidate.id), { preserveScroll: true, onSuccess: () => entry.reset('grade') });
    }

    function submitDoc(e) {
        e.preventDefault();
        doc.post(route('external-exams.documents.store', candidate.id), { forceFormData: true, preserveScroll: true, onSuccess: () => doc.reset('file') });
    }

    const title = candidate.student.name;

    return (
        <AppShell title={title} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={title} />
            <PageHeader
                title={title}
                subtitle={`${session.name} · ${candidate.student.student_number}`}
                actions={
                    <>
                        <StatusBadge status={candidate.status} label={enumLabel(t, 'ext.candStatus', candidate.status)} />
                        <Link href={route('external-exams.sessions.show', session.id)} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
                <Card title={t('ext.identity')}>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                        <dt className="text-slate-500">{t('ext.col.internalRef')}</dt>
                        <dd className="font-mono" dir="ltr">
                            {candidate.internal_reference}
                        </dd>
                        <dt className="text-slate-500">{t('ext.col.officialIndex')}</dt>
                        <dd className="font-mono" dir="ltr">
                            {candidate.official_index_number ?? <span className="font-sans text-slate-400">{t('ext.indexPending')}</span>}
                        </dd>
                        <dt className="text-slate-500">{t('ext.col.registeredOn')}</dt>
                        <dd>{candidate.registered_on ?? '—'}</dd>
                    </dl>
                    <p className="mt-3 text-xs text-slate-500">{t('ext.internalRefNote')}</p>

                    {can.manage && !locked ? (
                        <form onSubmit={submitIndex} className="mt-4 space-y-3 border-t border-slate-100 pt-4">
                            <ErrorSummary errors={index.errors} />
                            <Field label={isWassce ? t('ext.officialIndexWassce') : t('ext.officialIndexGabece')} hint={t('ext.indexExact')}>
                                <Input dir="ltr" value={index.data.official_index_number} onChange={(e) => index.setData('official_index_number', e.target.value)} required maxLength={60} />
                            </Field>
                            {hasIndex ? (
                                <Field label={t('ext.indexChangeReason')}>
                                    <Input value={index.data.reason} onChange={(e) => index.setData('reason', e.target.value)} maxLength={500} required />
                                </Field>
                            ) : null}
                            <button type="submit" className="btn-teal" disabled={index.processing}>
                                {t('ext.saveIndex')}
                            </button>
                        </form>
                    ) : null}
                </Card>

                <Card title={t('ext.status')}>
                    {can.manage ? (
                        <div className="flex flex-wrap gap-2">
                            {['registered', 'confirmed', 'withdrawn'].map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    disabled={candidate.status === s}
                                    className={s === 'withdrawn' ? 'btn-outline !text-red-700' : 'btn-outline'}
                                    onClick={() => router.patch(route('external-exams.candidates.status', candidate.id), { status: s }, { preserveScroll: true })}
                                >
                                    {t(`ext.setStatus.${s}`)}
                                </button>
                            ))}
                        </div>
                    ) : (
                        <StatusBadge status={candidate.status} label={enumLabel(t, 'ext.candStatus', candidate.status)} />
                    )}
                    <div className="mt-4 space-y-1 text-sm text-slate-600">
                        <p>
                            {t('ext.collectionState')}: <strong>{collection ? enumLabel(t, 'ext.colStatus', collection.status) : t('ext.notStarted')}</strong>
                        </p>
                        <p>
                            {t('ext.certificateState')}: <strong>{certificate ? enumLabel(t, 'ext.certStatus', certificate.status) : enumLabel(t, 'ext.certStatus', 'awaiting')}</strong>
                        </p>
                        <Link href={route('external-exams.collections.index', { session: session.id })} className="inline-block text-teal hover:underline">
                            {t('ext.openRegister')}
                        </Link>
                    </div>
                </Card>
            </div>

            <Card title={t('ext.subjectsTitle')} className="mb-6">
                {can.manage && !locked ? (
                    <form onSubmit={submitSubjects} className="space-y-3">
                        <ErrorSummary errors={subj.errors} />
                        {subjects.length === 0 ? <p className="text-sm text-slate-500">{t('ext.noSubjects')}</p> : null}
                        <div className="flex flex-wrap gap-x-5 gap-y-2">
                            {subjects.map((s) => (
                                <label key={s.id} className="flex items-center gap-1.5 text-sm">
                                    <input type="checkbox" className="rounded border-gray-300" checked={subj.data.subject_ids.includes(s.id)} onChange={() => toggleSubject(s.id)} />
                                    {s.name}
                                </label>
                            ))}
                        </div>
                        <button type="submit" className="btn-navy" disabled={subj.processing}>
                            {t('ext.saveSubjects')}
                        </button>
                    </form>
                ) : (
                    <p className="text-sm text-slate-700">{registered.map((s) => s.name).join(', ') || '—'}</p>
                )}
            </Card>

            <Card
                title={t('ext.resultsTitle')}
                className="mb-6"
                actions={
                    can.verify && unverified ? (
                        <button type="button" className="btn-navy" onClick={() => router.post(route('external-exams.candidates.verify', candidate.id), {}, { preserveScroll: true })}>
                            {t('ext.verifyCandidate')}
                        </button>
                    ) : null
                }
            >
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-3 py-2 text-start">{t('ext.col.subject')}</th>
                                <th className="px-3 py-2 text-start">{t('ext.col.grade')}</th>
                                <th className="px-3 py-2 text-start">{t('ext.col.status')}</th>
                                <th className="px-3 py-2 text-start">{t('ext.col.source')}</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {results.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-3 py-6 text-center text-slate-500">
                                        {t('ext.noResults')}
                                    </td>
                                </tr>
                            ) : null}
                            {results.map((r) => (
                                <tr key={r.id}>
                                    <td className="px-3 py-2">{r.subject}</td>
                                    <td className="px-3 py-2 font-semibold text-navy">{r.grade}</td>
                                    <td className="px-3 py-2">
                                        <StatusBadge status={r.status} label={enumLabel(t, 'ext.resultStatus', r.status)} />
                                    </td>
                                    <td className="px-3 py-2 text-slate-600">{enumLabel(t, 'ext.source', r.source)}</td>
                                    <td className="px-3 py-2 text-end">
                                        {can.verify && ['verified', 'published'].includes(r.status) ? <CorrectGrade result={r} grades={grades} /> : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {can.manage && !locked ? (
                    <form onSubmit={submitResult} className="mt-4 grid items-end gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[1fr_10rem_auto]">
                        <div className="sm:col-span-3">
                            <ErrorSummary errors={entry.errors} />
                        </div>
                        <Field label={t('ext.col.subject')}>
                            <Select value={entry.data.subject_id} onChange={(e) => entry.setData('subject_id', e.target.value)} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {registered.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('ext.col.grade')}>
                            <Select value={entry.data.grade} onChange={(e) => entry.setData('grade', e.target.value)} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {grades.map((g) => (
                                    <option key={g} value={g}>
                                        {g}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <button type="submit" className="btn-teal" disabled={entry.processing}>
                            {t('ext.enterResult')}
                        </button>
                    </form>
                ) : null}
            </Card>

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
                <Card title={t('ext.documentsTitle')}>
                    <ul className="mb-4 divide-y divide-slate-100 text-sm">
                        {documents.length === 0 ? <li className="py-2 text-slate-500">{t('ext.noDocuments')}</li> : null}
                        {documents.map((d) => (
                            <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span>
                                    <span className="me-2 rounded bg-slate-100 px-2 py-0.5 text-xs">{enumLabel(t, 'ext.docType', d.type)}</span>
                                    <a href={d.url} className="text-teal hover:underline">
                                        {d.name}
                                    </a>
                                </span>
                                {can.manage ? (
                                    <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => router.delete(route('external-exams.documents.destroy', [candidate.id, d.id]), { preserveScroll: true })}>
                                        {t('acad.common.remove')}
                                    </button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                    {can.manage ? (
                        <form onSubmit={submitDoc} className="space-y-3">
                            <Select value={doc.data.type} onChange={(e) => doc.setData('type', e.target.value)}>
                                {documentTypes.map((d) => (
                                    <option key={d} value={d}>
                                        {enumLabel(t, 'ext.docType', d)}
                                    </option>
                                ))}
                            </Select>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => doc.setData('file', e.target.files[0])} className="block w-full text-sm" />
                            <InputError message={messageText(t, doc.errors.file)} />
                            <button type="submit" className="btn-navy" disabled={doc.processing || !doc.data.file}>
                                {t('ext.uploadDocument')}
                            </button>
                        </form>
                    ) : null}
                </Card>

                <Card title={t('ext.historyTitle')}>
                    <ul className="max-h-72 space-y-2 overflow-y-auto text-sm">
                        {history.length === 0 ? <li className="text-slate-500">{t('ext.noHistory')}</li> : null}
                        {history.map((h) => (
                            <li key={h.id} className="rounded-lg border border-slate-100 p-2">
                                <p>
                                    <strong>{enumLabel(t, 'ext.event', h.event)}</strong> · {h.subject}
                                    {h.old_grade || h.new_grade ? (
                                        <span className="ms-2 font-mono text-xs" dir="ltr">
                                            {h.old_grade ?? '—'} → {h.new_grade ?? '—'}
                                        </span>
                                    ) : null}
                                </p>
                                {h.reason ? <p className="text-slate-600">{h.reason}</p> : null}
                                <p className="text-xs text-slate-500">
                                    {h.user ?? '—'} · {h.at}
                                </p>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AppShell>
    );
}

/** Correcting a verified/published grade needs a new grade and a recorded reason. */
function CorrectGrade({ result, grades }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ grade: result.grade, reason: '' });
    const [open, setOpen] = useState(false);

    if (!open) {
        return (
            <button type="button" className="btn-outline" onClick={() => setOpen(true)}>
                {t('ext.correct')}
            </button>
        );
    }

    return (
        <form
            className="ms-auto w-full max-w-xs space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-start"
            onSubmit={(e) => {
                e.preventDefault();
                post(route('external-exams.results.correct', result.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        reset();
                        setOpen(false);
                    },
                });
            }}
        >
            <ErrorSummary errors={errors} />
            <Select value={data.grade} onChange={(e) => setData('grade', e.target.value)}>
                {grades.map((g) => (
                    <option key={g} value={g}>
                        {g}
                    </option>
                ))}
            </Select>
            <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} placeholder={t('ext.correctReason')} required maxLength={500} />
            <div className="flex gap-2">
                <button type="submit" className="btn-teal" disabled={processing}>
                    {t('ext.correct')}
                </button>
                <button type="button" className="btn-outline" onClick={() => setOpen(false)}>
                    {t('acad.common.cancel')}
                </button>
            </div>
        </form>
    );
}
