import {
    BooksIcon,
    CalendarMiniIcon,
    CapIcon,
    ClipboardIcon,
    formatHeroDate,
    SchoolHeroArt,
    StudentAvatar,
    TrophyIcon,
} from '@/Components/SchoolOS/DashboardChrome';
import { Card, Field, Textarea, Select } from '@/Components/SchoolOS/Form';
import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { reportingShell } from './shell';

export default function Show({ nav, user, school, academicYear, tabs = [], current, report, events = [], can = {}, disclaimer }) {
    const { t } = useLocale();
    if (can.family) {
        return <FamilyShow nav={nav} user={user} school={school} academicYear={academicYear} report={report} disclaimer={disclaimer} t={t} />;
    }
    const payload = report.payload ?? {};
    const subjects = payload.subjects ?? [];
    const { data, setData, put, processing } = useForm({
        class_teacher_comment: report.class_teacher_comment ?? '',
        headteacher_comment: report.headteacher_comment ?? '',
        behaviour: report.behaviour ?? '',
        participation: report.participation ?? '',
        promotion_status: report.promotion_status ?? '',
        promotion_decision: report.promotion_decision ?? '',
        allow_incomplete: !!report.allow_incomplete,
    });

    function post(name, extra = {}) {
        router.post(route(`reporting.${name}`, report.id), extra, { preserveScroll: true });
    }

    return (
        <ModuleLayout
            ns="rpt"
            title={report.student}
            subtitle={`${report.reference} · ${enumLabel(t, 'rpt.type', report.type)}`}
            tabs={tabs}
            current={current}
            shell={reportingShell({ nav, user, school, academicYear })}
            actions={
                <div className="flex flex-wrap gap-2">
                    <Link href={report.preview_url} className="btn-outline">
                        {t('rpt.action.preview')}
                    </Link>
                    <a href={report.pdf_url} className="btn-outline whitespace-nowrap">
                        {t('rpt.action.pdf')}
                    </a>
                </div>
            }
        >
            <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{disclaimer}</p>

            <div className="mb-5 flex flex-wrap items-center gap-3">
                <StatusBadge status={report.status} label={enumLabel(t, 'rpt.status', report.status)} />
                {report.incomplete ? <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{t('rpt.incomplete')}</span> : null}
                <span className="text-sm text-slate-500">{report.academic_year} {report.term ? `· ${report.term}` : ''}</span>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label={t('rpt.col.average')} value={report.average ?? '—'} />
                <Stat label={t('rpt.col.grade')} value={report.grade ?? '—'} />
                <Stat label={t('rpt.show.attendance')} value={payload.attendance?.percentage != null ? `${payload.attendance.percentage}%` : '—'} />
                <Stat label={t('rpt.col.number')} value={report.student_number} />
            </div>

            {report.type === 'annual' ? (
                <Card title={t('rpt.show.subjects')} className="mb-6">
                    <p className="mb-3 text-xs text-slate-500">{t(`rpt.weighting.${payload.weighting || 'equal_available'}`)}</p>
                    <div className="overflow-x-auto">
                        <table className="min-w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-navy text-white">
                                    <th className="px-3 py-2 text-start">{t('results.col.subject')}</th>
                                    {(payload.terms ?? []).map((term) => (
                                        <th key={term.id} className="px-3 py-2 text-start">
                                            {term.name}
                                        </th>
                                    ))}
                                    <th className="px-3 py-2 text-start">{t('rpt.show.annualAvg')}</th>
                                    <th className="px-3 py-2 text-start">{t('rpt.col.grade')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((row) => (
                                    <tr key={row.subject_id} className="border-b border-slate-100">
                                        <td className="px-3 py-2 font-medium text-navy">{row.name}</td>
                                        {(row.terms ?? []).map((tr) => (
                                            <td key={tr.term_id} className="px-3 py-2">
                                                {tr.status === 'missing' ? <span className="text-amber-700">—</span> : (tr.total ?? '—')}
                                            </td>
                                        ))}
                                        <td className="px-3 py-2">{row.incomplete ? '—' : (row.annual_average ?? '—')}</td>
                                        <td className="px-3 py-2">{row.grade ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            ) : report.type === 'termly' ? (
                <Card title={t('rpt.show.subjects')} className="mb-6">
                    <div className="overflow-x-auto">
                        <table className="min-w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-navy text-white">
                                    <th className="px-3 py-2 text-start">{t('results.col.subject')}</th>
                                    <th className="px-3 py-2 text-start">{t('results.col.ca')}</th>
                                    <th className="px-3 py-2 text-start">{t('results.col.exam')}</th>
                                    <th className="px-3 py-2 text-start">{t('results.col.total')}</th>
                                    <th className="px-3 py-2 text-start">{t('rpt.col.grade')}</th>
                                    <th className="px-3 py-2 text-start">{t('results.col.remark')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((row) => (
                                    <tr key={row.subject_id} className="border-b border-slate-100">
                                        <td className="px-3 py-2 font-medium text-navy">{row.name}</td>
                                        <td className="px-3 py-2">{row.ca_score ?? '—'}</td>
                                        <td className="px-3 py-2">{row.exam_score ?? '—'}</td>
                                        <td className="px-3 py-2">{row.status === 'missing' ? <span className="text-amber-700">{t('rpt.incomplete')}</span> : (row.total ?? '—')}</td>
                                        <td className="px-3 py-2">{row.grade ?? '—'}</td>
                                        <td className="px-3 py-2">{row.remark ?? ''}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            ) : null}

            {can.edit ? (
                <Card title={t('rpt.show.comments')} className="mb-6">
                    <form
                        className="grid gap-4 sm:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            put(route('reporting.update', report.id));
                        }}
                    >
                        <Field label={t('rpt.show.classTeacher')} className="sm:col-span-2">
                            <Textarea value={data.class_teacher_comment} onChange={(e) => setData('class_teacher_comment', e.target.value)} rows={3} />
                        </Field>
                        <Field label={t('rpt.show.headteacher')} className="sm:col-span-2">
                            <Textarea value={data.headteacher_comment} onChange={(e) => setData('headteacher_comment', e.target.value)} rows={3} />
                        </Field>
                        <Field label={t('rpt.show.behaviour')}>
                            <Select value={data.behaviour} onChange={(e) => setData('behaviour', e.target.value)}>
                                <option value="">—</option>
                                {['excellent', 'good', 'satisfactory', 'needs_improvement'].map((x) => (
                                    <option key={x} value={x}>
                                        {t(`rpt.beh.${x}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('rpt.show.participation')}>
                            <Select value={data.participation} onChange={(e) => setData('participation', e.target.value)}>
                                <option value="">—</option>
                                {['excellent', 'good', 'satisfactory', 'needs_improvement'].map((x) => (
                                    <option key={x} value={x}>
                                        {t(`rpt.beh.${x}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('rpt.show.promotion')}>
                            <Select value={data.promotion_status} onChange={(e) => setData('promotion_status', e.target.value)}>
                                <option value="">—</option>
                                {['promoted', 'repeat', 'graduated', 'pending'].map((x) => (
                                    <option key={x} value={x}>
                                        {t(`rpt.promo.${x}`)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <label className="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                            <input type="checkbox" checked={data.allow_incomplete} onChange={(e) => setData('allow_incomplete', e.target.checked)} />
                            {t('rpt.show.allowIncomplete')}
                        </label>
                        <div className="sm:col-span-2">
                            <button type="submit" className="btn-teal" disabled={processing}>
                                {t('acad.common.save')}
                            </button>
                        </div>
                    </form>
                </Card>
            ) : (
                <Card title={t('rpt.show.comments')} className="mb-6">
                    <p className="text-sm">
                        <strong>{t('rpt.show.classTeacher')}:</strong> {report.class_teacher_comment || '—'}
                    </p>
                    <p className="mt-2 text-sm">
                        <strong>{t('rpt.show.headteacher')}:</strong> {report.headteacher_comment || '—'}
                    </p>
                </Card>
            )}

            <div className="mb-6 flex flex-wrap gap-2">
                {can.submit ? (
                    <button type="button" className="btn-teal" onClick={() => post('submit')}>
                        {t('rpt.action.submit')}
                    </button>
                ) : null}
                {can.review ? (
                    <button type="button" className="btn-outline" onClick={() => post('review')}>
                        {t('rpt.action.review')}
                    </button>
                ) : null}
                {can.approve ? (
                    <button type="button" className="btn-teal" onClick={() => post('approve')}>
                        {t('rpt.action.approve')}
                    </button>
                ) : null}
                {can.return ? (
                    <button
                        type="button"
                        className="btn-outline"
                        onClick={() => {
                            const reason = window.prompt(t('rpt.show.reason'));
                            if (reason) post('return', { reason });
                        }}
                    >
                        {t('rpt.action.return')}
                    </button>
                ) : null}
                {can.publish ? (
                    <button type="button" className="btn-teal" onClick={() => post('publish')}>
                        {t('rpt.action.publish')}
                    </button>
                ) : null}
                {can.revise ? (
                    <button
                        type="button"
                        className="btn-outline"
                        onClick={() => {
                            const reason = window.prompt(t('rpt.show.reason'));
                            if (reason) post('revise', { reason });
                        }}
                    >
                        {t('rpt.action.revise')}
                    </button>
                ) : null}
            </div>

            <Card title={t('rpt.show.history')}>
                <ul className="space-y-2 text-sm">
                    {events.map((e) => (
                        <li key={e.id} className="flex flex-wrap gap-2 border-b border-slate-100 py-2">
                            <StatusBadge status={e.event} label={e.event} />
                            <span className="text-slate-600">{e.user}</span>
                            <span className="text-slate-400">{e.at}</span>
                            {e.comment ? <span className="text-slate-700">{e.comment}</span> : null}
                        </li>
                    ))}
                </ul>
            </Card>
        </ModuleLayout>
    );
}

function FamilyShow({ nav, user, school, academicYear, report, disclaimer, t }) {
    const { locale } = useLocale();
    const payload = report.payload ?? {};
    const subjects = payload.subjects ?? [];
    const schoolName = school?.name || '';

    return (
        <AppShell title={report.student} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={report.student} />

            <p className="mb-4">
                <Link href={route('reporting.index')} className="sd-panel-link">
                    ← {t('acad.common.back')}
                </Link>
            </p>

            <section className="sd-hero">
                <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                    <StudentAvatar name={report.student} photoUrl={report.photo_url} />
                    <div className="min-w-0">
                        <h1 className="truncate text-[1.55rem] font-bold tracking-tight text-navy sm:text-[1.85rem]">{report.student}</h1>
                        <p className="mt-0.5 truncate text-sm text-slate-500">
                            {[report.class, schoolName].filter(Boolean).join('  |  ')}
                        </p>
                        <p className="mt-0.5 truncate text-xs text-slate-400" dir="ltr">
                            {report.reference} · {enumLabel(t, 'rpt.type', report.type)}
                        </p>
                    </div>
                </div>
                <div className="hidden md:block" aria-hidden="true">
                    <SchoolHeroArt name={schoolName} />
                </div>
                <div className="sd-date-card">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/80 text-teal">
                        <CalendarMiniIcon />
                    </span>
                    <div className="min-w-0">
                        <p className="whitespace-nowrap text-sm font-bold text-navy">{formatHeroDate(new Date(), locale)}</p>
                        <p className="whitespace-nowrap text-[11px] font-medium text-slate-500">
                            {t('dash.termYear', { term: report.term || '—', year: report.academic_year || academicYear || '—' })}
                        </p>
                    </div>
                </div>
            </section>

            <p className="mb-5 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900">{disclaimer}</p>

            <div className="sd-stats">
                <article className="sd-stat sd-stat-courses">
                    <div>
                        <p className="sd-stat-label">{t('rpt.col.average')}</p>
                        <p className="sd-stat-value">{report.average ?? '—'}</p>
                    </div>
                    <span className="sd-stat-icon bg-sky-100 text-sky-700">
                        <TrophyIcon className="h-5 w-5" />
                    </span>
                </article>
                <article className="sd-stat sd-stat-work">
                    <div>
                        <p className="sd-stat-label">{t('rpt.col.grade')}</p>
                        <p className="sd-stat-value">{report.grade ?? '—'}</p>
                    </div>
                    <span className="sd-stat-icon bg-emerald-100 text-emerald-700">
                        <CapIcon className="h-5 w-5" />
                    </span>
                </article>
                <article className="sd-stat sd-stat-quiz">
                    <div>
                        <p className="sd-stat-label">{t('rpt.show.attendance')}</p>
                        <p className="sd-stat-value">{payload.attendance?.percentage != null ? `${payload.attendance.percentage}%` : '—'}</p>
                    </div>
                    <span className="sd-stat-icon bg-violet-100 text-violet-700">
                        <BooksIcon className="h-5 w-5" />
                    </span>
                </article>
                <article className="sd-stat sd-stat-fees">
                    <div>
                        <p className="sd-stat-label">{t('rpt.col.number')}</p>
                        <p className="sd-stat-value text-[1.35rem] sm:text-[1.5rem]" dir="ltr">
                            {report.student_number ?? '—'}
                        </p>
                    </div>
                    <span className="sd-stat-icon bg-amber-100 text-amber-700">
                        <ClipboardIcon className="h-5 w-5" />
                    </span>
                </article>
            </div>

            <div className="mb-5 flex flex-wrap gap-2">
                <Link href={report.preview_url} className="btn-outline whitespace-nowrap">
                    {t('rpt.action.preview')}
                </Link>
                <a href={report.pdf_url} className="btn-teal whitespace-nowrap">
                    {t('rpt.action.pdf')}
                </a>
            </div>

            <section className="sd-panel mb-5">
                <div className="sd-panel-head">
                    <h2 className="sd-panel-title">
                        <BooksIcon className="h-4 w-4 text-teal" />
                        {t('rpt.show.subjects')}
                    </h2>
                </div>
                {report.type === 'annual' ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-wide text-slate-500">
                                    <th className="px-2 py-2 text-start font-medium">{t('results.col.subject')}</th>
                                    {(payload.terms ?? []).map((term) => (
                                        <th key={term.id} className="px-2 py-2 text-start font-medium">
                                            {term.name}
                                        </th>
                                    ))}
                                    <th className="px-2 py-2 text-start font-medium">{t('rpt.show.annualAvg')}</th>
                                    <th className="px-2 py-2 text-start font-medium">{t('rpt.col.grade')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((row) => (
                                    <tr key={row.subject_id} className="border-t border-slate-100">
                                        <td className="px-2 py-2.5 font-semibold text-navy">{row.name}</td>
                                        {(row.terms ?? []).map((tr) => (
                                            <td key={tr.term_id} className="px-2 py-2.5 tabular-nums">
                                                {tr.status === 'missing' ? '—' : (tr.total ?? '—')}
                                            </td>
                                        ))}
                                        <td className="px-2 py-2.5 tabular-nums">{row.incomplete ? '—' : (row.annual_average ?? '—')}</td>
                                        <td className="px-2 py-2.5 font-semibold text-navy">{row.grade ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-wide text-slate-500">
                                    <th className="px-2 py-2 text-start font-medium">{t('results.col.subject')}</th>
                                    <th className="px-2 py-2 text-start font-medium">{t('results.col.ca')}</th>
                                    <th className="px-2 py-2 text-start font-medium">{t('results.col.exam')}</th>
                                    <th className="px-2 py-2 text-start font-medium">{t('results.col.total')}</th>
                                    <th className="px-2 py-2 text-start font-medium">{t('rpt.col.grade')}</th>
                                    <th className="px-2 py-2 text-start font-medium">{t('results.col.remark')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((row) => (
                                    <tr key={row.subject_id} className="border-t border-slate-100">
                                        <td className="px-2 py-2.5 font-semibold text-navy">{row.name}</td>
                                        <td className="px-2 py-2.5 tabular-nums">{row.ca_score ?? '—'}</td>
                                        <td className="px-2 py-2.5 tabular-nums">{row.exam_score ?? '—'}</td>
                                        <td className="px-2 py-2.5 tabular-nums font-semibold text-navy">{row.status === 'missing' ? '—' : (row.total ?? '—')}</td>
                                        <td className="px-2 py-2.5 font-semibold text-navy">{row.grade ?? '—'}</td>
                                        <td className="px-2 py-2.5 text-slate-600">{row.remark ?? ''}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <section className="sd-panel">
                <div className="sd-panel-head">
                    <h2 className="sd-panel-title">{t('rpt.show.comments')}</h2>
                </div>
                <p className="text-sm text-slate-600">
                    <strong className="text-navy">{t('rpt.show.classTeacher')}:</strong> {report.class_teacher_comment || '—'}
                </p>
                <p className="mt-2 text-sm text-slate-600">
                    <strong className="text-navy">{t('rpt.show.headteacher')}:</strong> {report.headteacher_comment || '—'}
                </p>
            </section>
        </AppShell>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-lg font-bold text-navy" dir="ltr">
                {value}
            </p>
        </div>
    );
}
