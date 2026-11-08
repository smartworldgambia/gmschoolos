import { Card, ErrorSummary, Field, Input, Textarea } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, todayIso } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({ document: d, events = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const body = useForm({ title: d.title ?? '', body: d.body ?? '' });
    const exc = useForm({ include_grade12_term3: !!d.exception?.include_grade12_term3, allow_incomplete: !!d.exception?.allow_incomplete, reason: '' });
    const issue = useForm({ issue_date: todayIso() });
    const payload = d.payload ?? {};
    const terms = payload.terms ?? [];
    const missing = payload.missing ?? [];
    const post = (name) => router.post(route(name, d.id), {}, { preserveScroll: true });

    return (
        <AppShell title={d.reference} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={d.reference} />
            <PageHeader
                title={enumLabel(t, 'rec.type', d.type)}
                subtitle={`${d.reference}${d.student ? ` · ${d.student}` : ''}`}
                actions={
                    <>
                        <StatusBadge status={d.status} label={enumLabel(t, 'rec.status', d.status)} />
                        <a href={route('records.pdf', d.id)} target="_blank" rel="noreferrer" className="btn-navy">
                            {t('rec.openPdf')}
                        </a>
                        <Link href={route('records.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('rec.notOfficial')}</p>

            {d.status === 'revoked' ? (
                <p className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {t('rec.revokedBanner')} {d.revoked_reason}
                </p>
            ) : null}

            <div className="mb-6 grid gap-6 lg:grid-cols-3">
                <Card title={t('rec.detailsTitle')} className="lg:col-span-2">
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                        <dt className="text-slate-500">{t('rec.col.reference')}</dt>
                        <dd className="font-mono" dir="ltr">
                            {d.reference}
                        </dd>
                        <dt className="text-slate-500">{t('rec.col.issueDate')}</dt>
                        <dd>{d.issue_date ?? '—'}</dd>
                        <dt className="text-slate-500">{t('rec.preparedBy')}</dt>
                        <dd>{d.prepared_by ?? '—'}</dd>
                        <dt className="text-slate-500">{t('rec.reprints')}</dt>
                        <dd>{d.reprint_count}</dd>
                        {d.verification_url ? (
                            <>
                                <dt className="text-slate-500">{t('rec.verifyUrl')}</dt>
                                <dd className="break-all font-mono text-xs" dir="ltr">
                                    <a href={d.verification_url} target="_blank" rel="noreferrer" className="text-teal underline">
                                        {d.verification_url}
                                    </a>
                                </dd>
                            </>
                        ) : null}
                    </dl>
                    {d.exception ? (
                        <p className="mt-3 rounded bg-amber-50 p-2 text-sm text-amber-900">
                            <strong>{t('rec.exceptionGranted')}:</strong> {d.exception.reason}
                            {d.exception.include_grade12_term3 ? ` · ${t('rec.exc.term3')}` : ''}
                            {d.exception.allow_incomplete ? ` · ${t('rec.exc.incomplete')}` : ''}
                        </p>
                    ) : null}
                </Card>

                <Card title={t('rec.workflow')}>
                    <ol className="mb-3 space-y-1 text-sm">
                        {['draft', 'submitted', 'approved', 'issued'].map((s) => (
                            <li key={s} className={`flex items-center gap-2 ${d.status === s ? 'font-semibold text-navy' : 'text-slate-500'}`}>
                                <span className={`inline-block h-2 w-2 rounded-full ${d.status === s ? 'bg-teal' : 'bg-slate-300'}`} />
                                {enumLabel(t, 'rec.status', s)}
                            </li>
                        ))}
                    </ol>
                    <div className="flex flex-wrap gap-2">
                        {can.submit ? (
                            <button type="button" className="btn-navy" onClick={() => post('records.submit')}>
                                {t('rec.act.submit')}
                            </button>
                        ) : null}
                        {can.approve ? (
                            <button type="button" className="btn-teal" onClick={() => post('records.approve')}>
                                {t('rec.act.approve')}
                            </button>
                        ) : null}
                        {can.return ? <ReasonAction url={route('records.return', d.id)} label={t('rec.act.return')} /> : null}
                        {can.reprint ? <ReasonAction url={route('records.reprint', d.id)} label={t('rec.act.reprint')} /> : null}
                        {can.revoke ? <ReasonAction url={route('records.revoke', d.id)} label={t('rec.act.revoke')} tone="danger" /> : null}
                    </div>
                    {can.issue ? (
                        <form
                            className="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                issue.post(route('records.issue', d.id), { preserveScroll: true });
                            }}
                        >
                            <ErrorSummary errors={issue.errors} />
                            <Field label={t('rec.issueDateLabel')}>
                                <Input type="date" max={todayIso()} value={issue.data.issue_date} onChange={(e) => issue.setData('issue_date', e.target.value)} />
                            </Field>
                            <button type="submit" className="btn-teal" disabled={issue.processing}>
                                {t('rec.act.issue')}
                            </button>
                        </form>
                    ) : null}
                </Card>
            </div>

            {d.is_letter ? (
                <Card title={t('rec.letterBody')} className="mb-6">
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            body.put(route('records.update', d.id), { preserveScroll: true });
                        }}
                    >
                        <ErrorSummary errors={body.errors} />
                        <Field label={t('rec.letterTitle')}>
                            <Input value={body.data.title} onChange={(e) => body.setData('title', e.target.value)} disabled={!can.edit} maxLength={200} />
                        </Field>
                        <Textarea rows={12} value={body.data.body} onChange={(e) => body.setData('body', e.target.value)} disabled={!can.edit} maxLength={6000} />
                        {can.edit ? (
                            <button type="submit" className="btn-teal" disabled={body.processing}>
                                {t('acad.common.save')}
                            </button>
                        ) : null}
                    </form>
                </Card>
            ) : (
                <>
                    <Card
                        title={t('rec.transcriptTitle')}
                        className="mb-6"
                        actions={
                            <>
                                {can.edit ? (
                                    <button type="button" className="btn-outline" onClick={() => post('records.recompile')}>
                                        {t('rec.recompile')}
                                    </button>
                                ) : null}
                                {can.history ? (
                                    <Link href={route('records.history.show', d.student_id)} className="btn-outline">
                                        {t('rec.historyLink')}
                                    </Link>
                                ) : null}
                            </>
                        }
                    >
                        <p className="mb-3 text-sm text-slate-600">
                            {t('rec.termsRecorded', { n: payload.summary?.terms_recorded ?? 0, total: payload.summary?.terms_required ?? 0 })}
                            {payload.summary?.overall_average !== null && payload.summary?.overall_average !== undefined ? ` · ${t('rec.overallAverage')}: ${payload.summary.overall_average}` : ''}
                        </p>
                        {missing.length > 0 ? (
                            <p className="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                                {t('rec.missingTerms')}: <span dir="ltr">{missing.join(', ')}</span>
                            </p>
                        ) : null}
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {terms.map((term) => (
                                <div key={term.label} className="rounded-lg border border-slate-200 p-3">
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <p className="font-semibold text-navy" dir="ltr">
                                            {t('rec.termLabel', { grade: term.grade_number, term: term.term_sequence })}
                                        </p>
                                        <span className="text-xs text-slate-500">{term.academic_year ?? ''}</span>
                                    </div>
                                    {term.entries.length === 0 ? (
                                        <p className="text-sm text-red-700">{t('rec.termMissing')}</p>
                                    ) : (
                                        <table className="w-full text-xs">
                                            <tbody className="divide-y divide-slate-100">
                                                {term.entries.map((e, i) => (
                                                    <tr key={i}>
                                                        <td className="py-1 pe-2">{e.subject}</td>
                                                        <td className="py-1 pe-2 text-end">{e.mark ?? '—'}</td>
                                                        <td className="py-1 text-end font-semibold">{e.grade ?? '—'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                    {term.average !== null ? (
                                        <p className="mt-2 text-xs text-slate-500">
                                            {t('rec.average')}: {term.average} · {enumLabel(t, 'rec.termSource', term.source)}
                                        </p>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </Card>

                    {can.exception ? (
                        <Card title={t('rec.exceptionTitle')} className="mb-6">
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    exc.post(route('records.exception', d.id), { preserveScroll: true, onSuccess: () => exc.reset('reason') });
                                }}
                            >
                                <ErrorSummary errors={exc.errors} />
                                <p className="text-sm text-slate-600">{t('rec.exceptionHelp')}</p>
                                {d.type === 'transcript_g12' ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="checkbox" className="rounded border-gray-300" checked={exc.data.include_grade12_term3} onChange={(e) => exc.setData('include_grade12_term3', e.target.checked)} />
                                        {t('rec.exc.term3')}
                                    </label>
                                ) : null}
                                <label className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" className="rounded border-gray-300" checked={exc.data.allow_incomplete} onChange={(e) => exc.setData('allow_incomplete', e.target.checked)} />
                                    {t('rec.exc.incomplete')}
                                </label>
                                <Field label={t('acad.common.reason')}>
                                    <Input value={exc.data.reason} onChange={(e) => exc.setData('reason', e.target.value)} required maxLength={500} />
                                </Field>
                                <button type="submit" className="btn-navy" disabled={exc.processing}>
                                    {t('rec.exc.grant')}
                                </button>
                            </form>
                        </Card>
                    ) : null}
                </>
            )}

            <Card title={t('rec.eventsTitle')}>
                <ul className="space-y-2 text-sm">
                    {events.map((e) => (
                        <li key={e.id} className="rounded-lg border border-slate-100 p-2">
                            <p>
                                <strong>{enumLabel(t, 'rec.event', e.event)}</strong>
                            </p>
                            {e.note ? <p className="text-slate-600">{e.note}</p> : null}
                            <p className="text-xs text-slate-500">
                                {e.user ?? '—'} · {e.at}
                            </p>
                        </li>
                    ))}
                </ul>
            </Card>
        </AppShell>
    );
}
