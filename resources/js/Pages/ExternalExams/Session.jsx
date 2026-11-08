import InputError from '@/Components/InputError';
import { Card, ErrorSummary, Field, Input, Pager, Stat } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, messageText } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Session({
    session,
    candidates,
    resultCounts = {},
    eligibleStudents = [],
    subjects = [],
    batches = [],
    q = '',
    can = {},
    importHeaders = [],
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t } = useLocale();
    const [search, setSearch] = useState(q);
    const [openBatch, setOpenBatch] = useState(null);
    const reg = useForm({ student_ids: [], subject_ids: subjects.map((s) => s.id) });
    const imp = useForm({ file: null });
    const isWassce = session.type === 'wassce';

    function toggle(list, id) {
        return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
    }

    function submitRegister(e) {
        e.preventDefault();
        reg.post(route('external-exams.sessions.register', session.id), { preserveScroll: true, onSuccess: () => reg.setData('student_ids', []) });
    }

    function submitImport(e) {
        e.preventDefault();
        imp.post(route('external-exams.sessions.import', session.id), { forceFormData: true, preserveScroll: true, onSuccess: () => imp.reset('file') });
    }

    function runSearch(e) {
        e.preventDefault();
        router.get(route('external-exams.sessions.show', session.id), { q: search }, { preserveState: true, replace: true });
    }

    function post(name, extra = {}) {
        router.post(route(name, session.id), extra, { preserveScroll: true });
    }

    const sessionOpen = session.status === 'open';

    return (
        <AppShell title={session.name} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={session.name} />
            <PageHeader
                title={session.name}
                subtitle={`${enumLabel(t, 'ext.type', session.type)} · ${session.exam_year}`}
                actions={
                    <>
                        <StatusBadge status={session.status} label={enumLabel(t, 'ext.sessionStatus', session.status)} />
                        {can.manage ? (
                            <button
                                type="button"
                                className="btn-outline"
                                onClick={() => router.patch(route('external-exams.sessions.update', session.id), { status: sessionOpen ? 'closed' : 'open' }, { preserveScroll: true })}
                            >
                                {sessionOpen ? t('ext.closeSession') : t('ext.reopenSession')}
                            </button>
                        ) : null}
                        <Link href={route('external-exams.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{isWassce ? t('ext.indexNoteWassce') : t('ext.indexNoteGabece')}</p>

            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
                <Stat label={t('ext.col.candidates')} value={candidates.total ?? 0} />
                {['entered', 'imported', 'verified', 'published'].map((s) => (
                    <Stat key={s} label={enumLabel(t, 'ext.resultStatus', s)} value={resultCounts[s] ?? 0} tone={s === 'published' ? 'green' : 'navy'} />
                ))}
            </div>

            {can.verify ? (
                <Card title={t('ext.verifyPublish')} className="mb-6">
                    <p className="mb-3 text-sm text-slate-600">{t('ext.verifyPublishHelp')}</p>
                    <div className="flex flex-wrap gap-2">
                        <button type="button" className="btn-navy" onClick={() => post('external-exams.sessions.verify')}>
                            {t('ext.verifyAll')}
                        </button>
                        <button type="button" className="btn-teal" onClick={() => post('external-exams.sessions.publish')}>
                            {t('ext.publishVerified')}
                        </button>
                    </div>
                </Card>
            ) : null}

            {can.manage ? (
                <div className="mb-6 grid gap-6 lg:grid-cols-2">
                    <Card title={t('ext.registerTitle')}>
                        <form onSubmit={submitRegister} className="space-y-4">
                            <ErrorSummary errors={reg.errors} />
                            <p className="text-sm text-slate-600">{isWassce ? t('ext.registerHelpWassce') : t('ext.registerHelpGabece')}</p>
                            <div className="max-h-56 overflow-y-auto rounded-lg border border-slate-200 p-2">
                                {eligibleStudents.length === 0 ? <p className="p-2 text-sm text-slate-500">{t('ext.noEligible')}</p> : null}
                                {eligibleStudents.map((s) => (
                                    <label key={s.id} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50">
                                        <input type="checkbox" className="rounded border-gray-300" checked={reg.data.student_ids.includes(s.id)} onChange={() => reg.setData('student_ids', toggle(reg.data.student_ids, s.id))} />
                                        <span className="font-medium text-navy">{s.name}</span>
                                        <span className="text-xs text-slate-500">
                                            {s.student_number}
                                            {s.class ? ` · ${s.class}` : ''}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <fieldset>
                                <legend className="mb-1 text-sm font-medium text-slate-700">{t('ext.registerSubjects')}</legend>
                                {subjects.length === 0 ? (
                                    <p className="text-sm text-slate-500">{t('ext.noSubjects')}</p>
                                ) : (
                                    <div className="flex flex-wrap gap-x-4 gap-y-1">
                                        {subjects.map((s) => (
                                            <label key={s.id} className="flex items-center gap-1.5 text-sm">
                                                <input type="checkbox" className="rounded border-gray-300" checked={reg.data.subject_ids.includes(s.id)} onChange={() => reg.setData('subject_ids', toggle(reg.data.subject_ids, s.id))} />
                                                {s.name}
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </fieldset>
                            <button type="submit" className="btn-teal" disabled={reg.processing || reg.data.student_ids.length === 0}>
                                {t('ext.registerSelected', { n: reg.data.student_ids.length })}
                            </button>
                        </form>
                    </Card>

                    <Card title={t('ext.importTitle')}>
                        <p className="mb-2 text-sm text-slate-600">{t('ext.importHelp')}</p>
                        <p className="mb-3 break-words rounded bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700" dir="ltr">
                            {importHeaders.join(', ')}
                        </p>
                        <form onSubmit={submitImport} className="space-y-3">
                            <input type="file" accept=".csv,text/csv" onChange={(e) => imp.setData('file', e.target.files[0])} className="block w-full text-sm" />
                            <InputError message={messageText(t, imp.errors.file)} />
                            <div className="flex flex-wrap gap-2">
                                <button type="submit" className="btn-teal" disabled={imp.processing || !imp.data.file}>
                                    {t('ext.importUpload')}
                                </button>
                                <a href={route('external-exams.template')} className="btn-outline">
                                    {t('import.template')}
                                </a>
                            </div>
                        </form>
                        <div className="mt-4 space-y-2">
                            {batches.map((b) => (
                                <div key={b.id} className="rounded-lg border border-slate-100 p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium text-navy">{b.original_filename}</span>
                                        <span className="text-emerald-700">
                                            {t('import.imported')}: {b.success_rows ?? 0}
                                        </span>
                                        <span className="text-red-600">
                                            {t('import.rejected')}: {b.failed_rows ?? 0}
                                        </span>
                                        {(b.errors ?? []).length > 0 ? (
                                            <button type="button" className="text-teal hover:underline" onClick={() => setOpenBatch(openBatch === b.id ? null : b.id)}>
                                                {openBatch === b.id ? t('import.hideErrors') : t('import.showErrors', { n: b.errors.length })}
                                            </button>
                                        ) : null}
                                    </div>
                                    {openBatch === b.id ? (
                                        <ul className="mt-2 max-h-48 list-disc space-y-1 overflow-y-auto ps-5 text-red-700">
                                            {b.errors.map((e, i) => (
                                                <li key={i}>
                                                    {t('import.row')} {e.line}: {messageText(t, e.error)}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
            ) : null}

            <form onSubmit={runSearch} className="mb-4 flex flex-wrap gap-2">
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('ext.searchPlaceholder')} className="max-w-sm" />
                <button type="submit" className="btn-outline">
                    {t('acad.common.search')}
                </button>
            </form>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('ext.col.student')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.internalRef')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.officialIndex')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.subjectsCount')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.resultsCount')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {candidates.data.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                    {t('ext.noCandidates')}
                                </td>
                            </tr>
                        ) : null}
                        {candidates.data.map((c) => (
                            <tr key={c.id}>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-navy">{c.name}</p>
                                    <p className="text-xs text-slate-500">{c.student_number}</p>
                                </td>
                                <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                    {c.internal_reference}
                                </td>
                                <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                    {c.official_index_number ?? <span className="font-sans text-slate-400">{t('ext.indexPending')}</span>}
                                </td>
                                <td className="px-4 py-3">{c.subjects_count}</td>
                                <td className="px-4 py-3">{c.results_count}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={c.status} label={enumLabel(t, 'ext.candStatus', c.status)} />
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <Link href={route('external-exams.candidates.show', c.id)} className="btn-outline">
                                        {t('ext.open')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager links={candidates.links} />
        </AppShell>
    );
}
