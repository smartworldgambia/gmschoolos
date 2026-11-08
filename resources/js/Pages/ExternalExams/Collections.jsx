import { ErrorSummary, Field, Input, Pager, Select, Stat } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, todayIso } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Collections({
    sessions = [],
    sessionId,
    rows,
    status = '',
    tab = 'results',
    summary = {},
    can = {},
    estimateText,
    certificateStatuses = [],
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t } = useLocale();
    const [openRow, setOpenRow] = useState(null);
    const isResults = tab === 'results';
    const filters = isResults ? ['outstanding', 'collected'] : certificateStatuses;

    function go(params) {
        router.get(route('external-exams.collections.index'), { session: sessionId, tab, status, ...params }, { preserveState: true, replace: true });
    }

    return (
        <AppShell title={t('col.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('col.title')} />
            <PageHeader
                title={t('col.title')}
                subtitle={t('col.subtitle')}
                actions={
                    <>
                        <a href={route('external-exams.collections.print', { session: sessionId, tab, status })} target="_blank" rel="noreferrer" className="btn-navy">
                            {t('col.print')}
                        </a>
                        <Link href={route('external-exams.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('col.estimate')}</p>

            <div className="mb-6 grid grid-cols-3 gap-4">
                <Stat label={t('col.sum.outstanding')} value={summary.results_outstanding ?? 0} tone="red" />
                <Stat label={t('col.sum.collected')} value={summary.results_collected ?? 0} tone="green" />
                <Stat label={t('col.sum.certWaiting')} value={summary.certificates_waiting ?? 0} />
            </div>

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <Field label={t('col.session')}>
                    <Select value={sessionId ?? ''} onChange={(e) => go({ session: e.target.value, status: '' })}>
                        {sessions.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name} ({s.exam_year})
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex gap-2">
                    <button type="button" className={tab === 'results' ? 'btn-navy' : 'btn-outline'} onClick={() => go({ tab: 'results', status: '' })}>
                        {t('col.tab.results')}
                    </button>
                    <button type="button" className={tab === 'certificates' ? 'btn-navy' : 'btn-outline'} onClick={() => go({ tab: 'certificates', status: '' })}>
                        {t('col.tab.certificates')}
                    </button>
                </div>
                <Field label={t('ext.col.status')}>
                    <Select value={status} onChange={(e) => go({ status: e.target.value })}>
                        <option value="">{t('acad.common.all')}</option>
                        {filters.map((s) => (
                            <option key={s} value={s}>
                                {enumLabel(t, isResults ? 'ext.colStatus' : 'ext.certStatus', s)}
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>

            {sessions.length === 0 ? <p className="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">{t('col.noSessions')}</p> : null}

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('ext.col.student')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.officialIndex')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3 text-start">{t('col.received')}</th>
                            {!isResults ? <th className="px-4 py-3 text-start">{t('col.available')}</th> : null}
                            <th className="px-4 py-3 text-start">{t('col.collectedOn')}</th>
                            <th className="px-4 py-3 text-start">{t('col.collector')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.data.length === 0 ? (
                            <tr>
                                <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                                    {t('col.empty')}
                                </td>
                            </tr>
                        ) : null}
                        {rows.data.map((r) => {
                            const rec = isResults ? r.collection : r.certificate;
                            const st = rec?.status ?? (isResults ? 'outstanding' : 'awaiting');

                            return (
                                <RowBlock key={r.id} r={r} rec={rec} st={st} isResults={isResults} can={can} open={openRow === r.id} toggle={() => setOpenRow(openRow === r.id ? null : r.id)} certificateStatuses={certificateStatuses} />
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <Pager links={rows.links} />
        </AppShell>
    );
}

function RowBlock({ r, rec, st, isResults, can, open, toggle, certificateStatuses }) {
    const { t } = useLocale();
    const cols = isResults ? 7 : 8;
    const received = isResults ? Boolean(rec?.received_on) : null;

    return (
        <>
            <tr>
                <td className="px-4 py-3">
                    <p className="font-medium text-navy">{r.name}</p>
                    <p className="text-xs text-slate-500">{r.student_number}</p>
                </td>
                <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                    {r.official_index_number ?? <span className="font-sans text-slate-400">{t('ext.indexPending')}</span>}
                </td>
                <td className="px-4 py-3">
                    <StatusBadge status={st === 'outstanding' && isResults && !rec?.received_on ? 'pending' : st} label={isResults && !rec ? t('col.notReceived') : enumLabel(t, isResults ? 'ext.colStatus' : 'ext.certStatus', st)} />
                </td>
                <td className="px-4 py-3">{rec?.received_on ?? '—'}</td>
                {!isResults ? <td className="px-4 py-3">{rec?.available_on ?? '—'}</td> : null}
                <td className="px-4 py-3">{rec?.collected_on ?? '—'}</td>
                <td className="px-4 py-3 text-xs">
                    {rec?.collector_name ? (
                        <>
                            <p className="font-medium text-slate-700">{rec.collector_name}</p>
                            {rec.collector_id_masked ? (
                                <p className="font-mono text-slate-500" dir="ltr">
                                    {rec.collector_id_type ? `${rec.collector_id_type}: ` : ''}
                                    {rec.collector_id_masked}
                                </p>
                            ) : null}
                        </>
                    ) : (
                        '—'
                    )}
                </td>
                <td className="px-4 py-3 text-end">
                    {can.manage && st !== 'collected' ? (
                        <button type="button" className="btn-outline" onClick={toggle}>
                            {open ? t('acad.common.cancel') : t('col.update')}
                        </button>
                    ) : null}
                </td>
            </tr>
            {open ? (
                <tr className="bg-slate-50">
                    <td colSpan={cols + 1} className="px-4 py-4">
                        {isResults ? <ResultsActions r={r} received={received} done={toggle} /> : <CertificateActions r={r} rec={rec} st={st} statuses={certificateStatuses} done={toggle} />}
                    </td>
                </tr>
            ) : null}
        </>
    );
}

const emptyCollector = { collected_on: '', collector_name: '', collector_relationship: '', collector_id_type: '', collector_id_number: '', acknowledgement: '' };

function CollectorFields({ form, required }) {
    const { t } = useLocale();
    const { data, setData } = form;

    return (
        <div className="grid gap-3 sm:grid-cols-3">
            <Field label={t('col.collectedOn')}>
                <Input type="date" max={todayIso()} value={data.collected_on} onChange={(e) => setData('collected_on', e.target.value)} required={required} />
            </Field>
            <Field label={t('col.collectorName')}>
                <Input value={data.collector_name} onChange={(e) => setData('collector_name', e.target.value)} required={required} maxLength={150} />
            </Field>
            <Field label={t('col.relationship')}>
                <Input value={data.collector_relationship} onChange={(e) => setData('collector_relationship', e.target.value)} maxLength={80} />
            </Field>
            <Field label={t('col.idType')}>
                <Input value={data.collector_id_type} onChange={(e) => setData('collector_id_type', e.target.value)} maxLength={40} />
            </Field>
            <Field label={t('col.idNumber')} hint={t('col.idHint')}>
                <Input dir="ltr" value={data.collector_id_number} onChange={(e) => setData('collector_id_number', e.target.value)} maxLength={60} autoComplete="off" />
            </Field>
            <Field label={t('col.acknowledgement')}>
                <Input value={data.acknowledgement} onChange={(e) => setData('acknowledgement', e.target.value)} required={required} maxLength={200} />
            </Field>
        </div>
    );
}

function ResultsActions({ r, received, done }) {
    const { t } = useLocale();
    const recv = useForm({ received_on: todayIso() });
    const col = useForm(emptyCollector);

    return (
        <div className="space-y-4">
            {!received ? (
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        recv.post(route('external-exams.collections.received', r.id), { preserveScroll: true, onSuccess: done });
                    }}
                >
                    <ErrorSummary errors={recv.errors} />
                    <Field label={t('col.receivedOn')}>
                        <Input type="date" max={todayIso()} value={recv.data.received_on} onChange={(e) => recv.setData('received_on', e.target.value)} required />
                    </Field>
                    <button type="submit" className="btn-navy" disabled={recv.processing}>
                        {t('col.markReceived')}
                    </button>
                </form>
            ) : (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        col.post(route('external-exams.collections.collect', r.id), { preserveScroll: true, onSuccess: done });
                    }}
                >
                    <ErrorSummary errors={col.errors} />
                    <CollectorFields form={col} required />
                    <button type="submit" className="btn-teal" disabled={col.processing}>
                        {t('col.recordCollection')}
                    </button>
                </form>
            )}
        </div>
    );
}

function CertificateActions({ r, rec, st, statuses, done }) {
    const { t } = useLocale();
    const form = useForm({ status: st, received_on: rec?.received_on ?? '', available_on: rec?.available_on ?? '', notes: '', ...emptyCollector });
    const collecting = form.data.status === 'collected';

    return (
        <form
            className="space-y-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('external-exams.collections.certificate', r.id), { preserveScroll: true, onSuccess: done });
            }}
        >
            <ErrorSummary errors={form.errors} />
            <div className="grid gap-3 sm:grid-cols-4">
                <Field label={t('ext.col.status')}>
                    <Select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {enumLabel(t, 'ext.certStatus', s)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('col.received')}>
                    <Input type="date" value={form.data.received_on} onChange={(e) => form.setData('received_on', e.target.value)} />
                </Field>
                <Field label={t('col.available')}>
                    <Input type="date" value={form.data.available_on} onChange={(e) => form.setData('available_on', e.target.value)} />
                </Field>
                <Field label={t('acad.common.notes')}>
                    <Input value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} maxLength={1000} />
                </Field>
            </div>
            {collecting ? <CollectorFields form={form} required /> : null}
            <button type="submit" className="btn-teal" disabled={form.processing}>
                {t('col.saveCertificate')}
            </button>
        </form>
    );
}
