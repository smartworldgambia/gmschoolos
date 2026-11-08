import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Application({ application: a, round, subjects = [], gradeOptions = [], results = [], documents = [], history = [], letters = [], templates = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const initial = Object.fromEntries(subjects.map((s) => [s.code, results.find((r) => r.code === s.code)?.grade ?? '']));
    const verify = useForm({ grades: initial, note: '' });
    const decide = useForm({ decision: 'shortlisted', reason: '' });
    const letter = useForm({ template_id: templates[0]?.id ?? '', custom_text: '' });
    const closed = ['approved', 'rejected', 'withdrawn'].includes(a.status) || round.status === 'finalised';
    const name = `${a.first_name} ${a.last_name}`;
    const assessment = a.assessment ?? {};

    function submitVerify(e) {
        e.preventDefault();
        verify.post(route('senior-secondary.applications.verify', a.id), { preserveScroll: true });
    }

    function submitDecision(e) {
        e.preventDefault();
        decide.post(route('senior-secondary.applications.decide', a.id), { preserveScroll: true, onSuccess: () => decide.reset('reason') });
    }

    function submitLetter(e) {
        e.preventDefault();
        letter.post(route('senior-secondary.applications.letter', a.id));
    }

    const rows = [
        ['ss.field.dob', a.date_of_birth],
        ['ss.field.gender', a.gender ? enumLabel(t, 'ss.gender', a.gender) : null],
        ['ss.field.nationality', a.nationality],
        ['ss.field.previousSchool', a.previous_school],
        ['ss.field.gabeceIndex', a.gabece_index_number],
        ['ss.field.gabeceYear', a.gabece_year],
        ['ss.field.programme', a.programme_choice],
        ['ss.field.guardian', a.guardian_name],
        ['ss.field.guardianPhone', a.guardian_phone],
        ['ss.field.guardianEmail', a.guardian_email],
        ['ss.field.address', a.address],
        ['ss.field.source', enumLabel(t, 'ss.source', a.source)],
        ['ss.field.submitted', a.created_at],
    ];

    return (
        <AppShell title={name} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={name} />
            <PageHeader
                title={name}
                subtitle={`${a.reference} · ${round.name}`}
                actions={
                    <>
                        <StatusBadge status={a.status} label={enumLabel(t, 'ss.appStatus', a.status)} />
                        {a.is_override ? <span className="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{t('ss.override')}</span> : null}
                        <Link href={route('senior-secondary.rounds.show', round.id)} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
                <Card title={t('ss.detailsTitle')}>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                        {rows.map(([k, v]) => (
                            <div key={k} className="contents">
                                <dt className="text-slate-500">{t(k)}</dt>
                                <dd className="break-words">{v ?? '—'}</dd>
                            </div>
                        ))}
                    </dl>
                    {a.notes ? <p className="mt-3 rounded bg-slate-50 p-2 text-sm text-slate-700">{a.notes}</p> : null}
                    <div className="mt-4 border-t border-slate-100 pt-3">
                        <p className="mb-2 text-sm font-medium text-slate-700">{t('ss.documentsTitle')}</p>
                        <ul className="space-y-1 text-sm">
                            {documents.length === 0 ? <li className="text-slate-500">{t('ext.noDocuments')}</li> : null}
                            {documents.map((d) => (
                                <li key={d.id}>
                                    <span className="me-2 rounded bg-slate-100 px-2 py-0.5 text-xs">{enumLabel(t, 'ss.docType', d.type)}</span>
                                    <a href={d.url} className="text-teal hover:underline">
                                        {d.name}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                </Card>

                <Card title={t('ss.assessmentTitle')}>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                        <dt className="text-slate-500">{t('ss.col.aggregate')}</dt>
                        <dd className="font-semibold text-navy">{a.aggregate ?? '—'}</dd>
                        <dt className="text-slate-500">{t('ss.col.eligible')}</dt>
                        <dd>{a.eligible === null ? '—' : a.eligible ? t('acad.common.yes') : t('acad.common.no')}</dd>
                        <dt className="text-slate-500">{t('ss.col.rank')}</dt>
                        <dd>{a.rank ?? '—'}</dd>
                        <dt className="text-slate-500">{t('ss.col.recommendation')}</dt>
                        <dd>{a.recommendation ? enumLabel(t, 'ss.recommendation', a.recommendation) : '—'}</dd>
                        <dt className="text-slate-500">{t('ss.col.verified')}</dt>
                        <dd>{a.verified ? t('acad.common.yes') : t('acad.common.no')}</dd>
                    </dl>
                    {Array.isArray(assessment.reasons) && assessment.reasons.length > 0 ? (
                        <ul className="mt-3 list-disc space-y-1 ps-5 text-sm text-amber-800">
                            {assessment.reasons.map((r, i) => {
                                const [code, subject] = String(r).split(':');

                                return <li key={i}>{t(`ss.reason.${code}`, { subject: subject ?? '' })}</li>;
                            })}
                        </ul>
                    ) : null}
                    {a.decision_reason ? (
                        <p className="mt-3 rounded bg-slate-50 p-2 text-sm">
                            <strong>{t('ss.decisionReason')}:</strong> {a.decision_reason}
                        </p>
                    ) : null}
                    <p className="mt-3 text-xs text-slate-500">{t('ss.humanReview')}</p>
                </Card>
            </div>

            <Card title={t('ss.resultsTitle')} className="mb-6">
                <p className="mb-3 text-sm text-slate-600">{a.verified ? t('ss.resultsVerifiedNote') : t('ss.resultsDeclaredNote')}</p>
                <form onSubmit={submitVerify} className="space-y-4">
                    <ErrorSummary errors={verify.errors} />
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {subjects.map((s) => (
                            <Field key={s.code} label={`${s.name} (${s.code})`}>
                                <Select disabled={!can.manage || closed} value={verify.data.grades[s.code] ?? ''} onChange={(e) => verify.setData('grades', { ...verify.data.grades, [s.code]: e.target.value })}>
                                    <option value="">—</option>
                                    {gradeOptions.map((g) => (
                                        <option key={g} value={g}>
                                            {g}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        ))}
                    </div>
                    {can.manage && !closed ? (
                        <>
                            <Field label={t('ss.verifyNote')}>
                                <Input value={verify.data.note} onChange={(e) => verify.setData('note', e.target.value)} maxLength={500} />
                            </Field>
                            <button type="submit" className="btn-navy" disabled={verify.processing}>
                                {a.verified ? t('ss.reverify') : t('ss.verifyResults')}
                            </button>
                        </>
                    ) : null}
                </form>
            </Card>

            {can.manage && !closed ? (
                <Card title={t('ss.decisionTitle')} className="mb-6">
                    <form onSubmit={submitDecision} className="grid gap-4 sm:grid-cols-[14rem_1fr_auto] sm:items-end">
                        <div className="sm:col-span-3">
                            <ErrorSummary errors={decide.errors} />
                        </div>
                        <Field label={t('ss.decision')}>
                            <Select value={decide.data.decision} onChange={(e) => decide.setData('decision', e.target.value)}>
                                {['shortlisted', 'waitlisted', 'approved', 'rejected'].map((d) => (
                                    <option key={d} value={d}>
                                        {enumLabel(t, 'ss.appStatus', d)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('ss.decisionReasonLabel')} hint={t('ss.overrideHint')}>
                            <Input value={decide.data.reason} onChange={(e) => decide.setData('reason', e.target.value)} maxLength={500} />
                        </Field>
                        <button type="submit" className="btn-teal" disabled={decide.processing}>
                            {t('ss.recordDecision')}
                        </button>
                    </form>
                    <div className="mt-4 border-t border-slate-100 pt-4">
                        <ReasonAction url={route('senior-secondary.applications.withdraw', a.id)} label={t('ss.withdraw')} tone="danger" required={false} />
                    </div>
                </Card>
            ) : null}

            {a.status === 'approved' && can.manage ? (
                <Card title={t('ss.letterTitle')} className="mb-6">
                    <form onSubmit={submitLetter} className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('rec.template')}>
                            <Select value={letter.data.template_id} onChange={(e) => letter.setData('template_id', e.target.value)}>
                                <option value="">{t('rec.defaultTemplate')}</option>
                                {templates.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('rec.customText')} className="sm:col-span-2">
                            <Textarea rows={2} value={letter.data.custom_text} onChange={(e) => letter.setData('custom_text', e.target.value)} maxLength={3000} />
                        </Field>
                        <div>
                            <button type="submit" className="btn-teal" disabled={letter.processing}>
                                {t('ss.createLetter')}
                            </button>
                        </div>
                    </form>
                </Card>
            ) : null}

            {letters.length > 0 ? (
                <Card title={t('ss.lettersTitle')} className="mb-6">
                    <ul className="space-y-1 text-sm">
                        {letters.map((l) => (
                            <li key={l.id} className="flex items-center gap-3">
                                <Link href={l.url} className="font-mono text-teal hover:underline" dir="ltr">
                                    {l.reference}
                                </Link>
                                <StatusBadge status={l.status} label={enumLabel(t, 'rec.status', l.status)} />
                            </li>
                        ))}
                    </ul>
                </Card>
            ) : null}

            <Card title={t('ss.historyTitle')}>
                <ul className="space-y-2 text-sm">
                    {history.map((h) => (
                        <li key={h.id} className="rounded-lg border border-slate-100 p-2">
                            <p>
                                <strong>{enumLabel(t, 'ss.event', h.event)}</strong>
                                {h.from || h.to ? (
                                    <span className="ms-2 text-slate-600">
                                        {h.from ? enumLabel(t, 'ss.appStatus', h.from) : '—'} → {h.to ? enumLabel(t, 'ss.appStatus', h.to) : '—'}
                                    </span>
                                ) : null}
                                {h.data?.subject ? (
                                    <span className="ms-2 font-mono text-xs" dir="ltr">
                                        {h.data.subject}: {h.data.from ?? '—'} → {h.data.to ?? '—'}
                                    </span>
                                ) : null}
                            </p>
                            {h.note ? <p className="text-slate-600">{h.note}</p> : null}
                            <p className="text-xs text-slate-500">
                                {h.user ?? t('ss.system')} · {h.at}
                            </p>
                        </li>
                    ))}
                </ul>
            </Card>
        </AppShell>
    );
}
