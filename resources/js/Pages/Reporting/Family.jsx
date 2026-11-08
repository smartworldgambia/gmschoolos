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
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';

export default function Family({
    nav,
    user,
    school,
    academicYear,
    learners = [],
    isLearner = true,
    termName = null,
    disclaimer,
    filters = {},
}) {
    const { t, locale } = useLocale();
    const primary = learners[0] ?? null;
    const title = t(isLearner ? 'rpt.family.title' : 'rpt.family.titleParent');
    const schoolName = school?.name || '';

    function go(params) {
        router.get(
            route('reporting.index'),
            {
                academic_year_id: filters.academic_year_id || undefined,
                term_id: filters.term_id || undefined,
                ...params,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppShell title={title} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={title} />

            <section className="sd-hero">
                <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                    {isLearner ? (
                        <StudentAvatar name={primary?.name || user?.name} photoUrl={primary?.photo_url} />
                    ) : (
                        <span className="flex h-[4.5rem] w-[4.5rem] shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700 ring-4 ring-white shadow-md sm:h-20 sm:w-20">
                            <CapIcon className="h-8 w-8" />
                        </span>
                    )}
                    <div className="min-w-0">
                        {isLearner ? (
                            <>
                                <p className="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-slate-500 sm:text-base">
                                    {t('dash.student.welcomeBack')}
                                    <span className="text-lg sm:text-xl" aria-hidden="true">
                                        👋
                                    </span>
                                </p>
                                <h1 className="truncate text-[1.55rem] font-bold tracking-tight text-navy sm:text-[1.85rem]">{primary?.name || title}</h1>
                                <p className="mt-0.5 truncate text-sm text-slate-500">{[primary?.class, schoolName].filter(Boolean).join('  |  ')}</p>
                            </>
                        ) : (
                            <>
                                <h1 className="truncate text-[1.55rem] font-bold tracking-tight text-navy sm:text-[1.85rem]">{title}</h1>
                                <p className="mt-0.5 text-sm text-slate-500">{t('rpt.family.subtitleParent')}</p>
                            </>
                        )}
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
                            {t('dash.termYear', { term: termName || '—', year: academicYear || '—' })}
                        </p>
                    </div>
                </div>
            </section>

            <p className="mb-5 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900">{disclaimer}</p>

            {learners.length === 0 ? (
                <section className="sd-panel">
                    <p className="py-8 text-center text-sm text-slate-500">{t('rpt.family.emptyProfile')}</p>
                </section>
            ) : (
                learners.map((learner, i) => (
                    <LearnerBlock
                        key={learner.id}
                        learner={learner}
                        t={t}
                        showIdentity={!isLearner || learners.length > 1}
                        periodBar={i === 0 ? <PeriodFilters filters={filters} t={t} onChange={go} className="mb-4" /> : null}
                    />
                ))
            )}
        </AppShell>
    );
}

function LearnerBlock({ learner, t, showIdentity, periodBar = null }) {
    const results = learner.results;
    const reports = learner.reports ?? [];
    const subjectCount = results?.subjects?.length ?? 0;

    return (
        <div className="mb-6">
            {showIdentity ? (
                <div className="mb-4 flex items-center gap-3">
                    <StudentAvatar name={learner.name} photoUrl={learner.photo_url} />
                    <div className="min-w-0">
                        <h2 className="truncate text-lg font-bold text-navy">{learner.name}</h2>
                        <p className="truncate text-sm text-slate-500">{[learner.number, learner.class].filter(Boolean).join(' · ')}</p>
                    </div>
                </div>
            ) : null}

            <div className="sd-stats">
                <article className="sd-stat sd-stat-courses">
                    <div>
                        <p className="sd-stat-label">{t('rpt.col.average')}</p>
                        <p className="sd-stat-value">{results?.average ?? '—'}</p>
                    </div>
                    <span className="sd-stat-icon bg-sky-100 text-sky-700">
                        <TrophyIcon className="h-5 w-5" />
                    </span>
                </article>
                <article className="sd-stat sd-stat-work">
                    <div>
                        <p className="sd-stat-label">{t('rpt.col.grade')}</p>
                        <p className="sd-stat-value">{results?.grade ?? '—'}</p>
                    </div>
                    <span className="sd-stat-icon bg-emerald-100 text-emerald-700">
                        <CapIcon className="h-5 w-5" />
                    </span>
                </article>
                <article className="sd-stat sd-stat-quiz">
                    <div>
                        <p className="sd-stat-label">{t('results.col.subjects')}</p>
                        <p className="sd-stat-value">{subjectCount}</p>
                    </div>
                    <span className="sd-stat-icon bg-violet-100 text-violet-700">
                        <BooksIcon className="h-5 w-5" />
                    </span>
                </article>
                <article className="sd-stat sd-stat-fees">
                    <div>
                        <p className="sd-stat-label">{t('rpt.family.official')}</p>
                        <p className="sd-stat-value">{reports.length}</p>
                    </div>
                    <span className="sd-stat-icon bg-amber-100 text-amber-700">
                        <ClipboardIcon className="h-5 w-5" />
                    </span>
                </article>
            </div>

            <div className="sd-low">
                <div className="min-w-0">
                    {periodBar}
                    <section className="sd-panel">
                        <div className="sd-panel-head">
                            <h2 className="sd-panel-title">
                                <BooksIcon className="h-4 w-4 text-teal" />
                                {t('rpt.family.latestResults')}
                            </h2>
                            {results ? (
                                <span className="text-xs font-medium text-slate-500">
                                    {results.academic_year}
                                    {results.term ? ` · ${results.term}` : ''}
                                </span>
                            ) : null}
                        </div>
                        {!results ? (
                            <p className="py-8 text-center text-sm text-slate-500">{t('rpt.family.emptyResults')}</p>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-xs uppercase tracking-wide text-slate-500">
                                                <th className="px-2 py-2 text-start font-medium">{t('results.col.subject')}</th>
                                                <th className="px-2 py-2 text-start font-medium">{t('results.col.ca')}</th>
                                                <th className="px-2 py-2 text-start font-medium">{t('results.col.exam')}</th>
                                                <th className="px-2 py-2 text-start font-medium">{t('results.col.total')}</th>
                                                <th className="px-2 py-2 text-start font-medium">{t('rpt.col.grade')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {results.subjects.map((row) => (
                                                <tr key={row.subject_id} className="border-t border-slate-100">
                                                    <td className="px-2 py-2.5 font-semibold text-navy">{row.name}</td>
                                                    <td className="px-2 py-2.5 tabular-nums">{row.ca_score ?? '—'}</td>
                                                    <td className="px-2 py-2.5 tabular-nums">{row.exam_score ?? '—'}</td>
                                                    <td className="px-2 py-2.5 tabular-nums font-semibold text-navy">{row.total ?? '—'}</td>
                                                    <td className="px-2 py-2.5">
                                                        <GradeChip letter={row.grade} />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {reports.length === 0 ? <p className="mt-3 text-sm text-slate-500">{t('rpt.family.waitingCard')}</p> : null}
                            </>
                        )}
                    </section>
                </div>

                <section className="sd-panel">
                    <div className="sd-panel-head">
                        <h2 className="sd-panel-title">
                            <ClipboardIcon className="h-4 w-4 text-teal" />
                            {t('rpt.family.official')}
                        </h2>
                    </div>
                    {reports.length === 0 ? (
                        <p className="py-8 text-center text-sm text-slate-500">{t('rpt.family.emptyReports')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {reports.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-surface px-3 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-navy">{enumLabel(t, 'rpt.type', r.type)}</p>
                                        <p className="truncate text-xs text-slate-500">{[r.reference, r.academic_year, r.term].filter(Boolean).join(' · ')}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Link href={route('reporting.show', r.id)} className="btn-outline whitespace-nowrap !px-3 !py-1.5 text-xs">
                                            {t('rpt.action.view')}
                                        </Link>
                                        <a href={route('reporting.pdf', r.id)} className="btn-teal whitespace-nowrap !px-3 !py-1.5 text-xs">
                                            {t('rpt.action.pdf')}
                                        </a>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </div>
    );
}

function PeriodFilters({ filters = {}, t, onChange, className = '' }) {
    return (
        <div className={`flex flex-nowrap items-center gap-2 ${className}`}>
            <div className="min-w-0 flex-1 max-w-[12rem]">
                <select
                    value={filters.academic_year_id ?? ''}
                    onChange={(e) => onChange({ academic_year_id: e.target.value || undefined, term_id: undefined })}
                    className="ui-input"
                    aria-label={t('rpt.col.year')}
                >
                    {(filters.years ?? []).map((y) => (
                        <option key={y.id} value={y.id}>
                            {y.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="min-w-0 flex-1 max-w-[11rem]">
                <select
                    value={filters.term_id ?? ''}
                    onChange={(e) => onChange({ term_id: e.target.value || undefined })}
                    className="ui-input"
                    aria-label={t('rpt.col.term')}
                >
                    <option value="">{t('rpt.col.term')}</option>
                    {(filters.terms ?? []).map((term) => (
                        <option key={term.id} value={term.id}>
                            {term.name}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
}

function GradeChip({ letter }) {
    if (!letter) {
        return <span className="text-slate-400">—</span>;
    }
    const tone =
        letter === 'A' || letter === 'A+'
            ? 'bg-emerald-100 text-emerald-800'
            : letter.startsWith('B')
              ? 'bg-sky-100 text-sky-800'
              : letter.startsWith('C')
                ? 'bg-amber-100 text-amber-800'
                : 'bg-slate-100 text-slate-700';

    return <span className={`inline-flex min-w-[1.75rem] justify-center rounded-full px-2 py-0.5 text-xs font-bold ${tone}`}>{letter}</span>;
}
