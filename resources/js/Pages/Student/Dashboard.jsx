import {
    BoltIcon,
    BooksIcon,
    CalendarMiniIcon,
    CapIcon,
    ClipboardIcon,
    CoinsIcon,
    formatEventDay,
    formatEventTime,
    formatHeroDate,
    MegaphoneMini,
    PinIcon,
    PlayIcon,
    ProgressRing,
    QuizMarkIcon,
    SchoolHeroArt,
    StudentAvatar,
    TodayLesson,
    TrophyIcon,
} from '@/Components/SchoolOS/DashboardChrome';
import { inferKind } from '@/Components/Comm/AnnouncementsChrome';
import { money } from '@/lib/fees';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

function eventIcon(category) {
    if (category === 'sports') {
        return <TrophyIcon className="h-5 w-5" />;
    }
    if (category === 'meeting') {
        return <MegaphoneMini className="h-5 w-5" />;
    }

    return <CalendarMiniIcon className="h-5 w-5" />;
}

function eventTone(category) {
    if (category === 'sports') {
        return 'bg-sky-100 text-sky-700';
    }
    if (category === 'meeting') {
        return 'bg-teal/15 text-teal-800';
    }
    if (category === 'academic') {
        return 'bg-brand-blue/10 text-brand-blue';
    }

    return 'bg-amber-100 text-amber-800';
}

function announcementTone(kind) {
    if (kind === 'sports') {
        return 'bg-teal/15 text-teal-800';
    }
    if (kind === 'meeting') {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-brand-blue/10 text-brand-blue';
}

export default function Dashboard({
    stats = {},
    timetable = [],
    assignments = [],
    results = [],
    announcements = [],
    events = [],
    progress = {},
    student,
    term = null,
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t, locale } = useLocale();
    const name = student?.full_name || user?.name || '';
    const schoolName = school?.name || '';
    const continueUrl = progress.continue_url || (typeof route === 'function' ? route('lms.courses.index') : '/lms/courses');
    const assignmentsTotal = stats.assignments_total ?? stats.pending_assignments ?? 0;
    const dueCount = stats.pending_assignments ?? 0;
    const quizzesTotal = stats.quizzes_total ?? stats.upcoming_quizzes ?? 0;
    const fees = stats.fees_outstanding;
    const percent = progress.percent;
    void assignments;
    void results;

    const actions = [
        { href: continueUrl, label: t('dash.quick.continue'), icon: <PlayIcon />, tone: 'bg-emerald-50 text-emerald-800' },
        { href: route('timetable.index'), label: t('dash.quick.viewTimetable'), icon: <CalendarMiniIcon />, tone: 'bg-sky-50 text-sky-800' },
        { href: `${route('lms.courses.index')}#assignments`, label: t('dash.quick.assignments'), icon: <ClipboardIcon className="h-5 w-5" />, tone: 'bg-violet-50 text-violet-800' },
        { href: route('fees.index'), label: t('dash.quick.payFees'), icon: <CoinsIcon className="h-5 w-5" />, tone: 'bg-amber-50 text-amber-800' },
    ];

    return (
        <AppShell title={t('dash.student.pageTitle')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('dash.student.pageTitle')} />

            {!student ? <p className="mb-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{t('dash.student.noRecord')}</p> : null}

            <section className="sd-hero">
                <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                    <StudentAvatar name={name} photoUrl={student?.photo_url} />
                    <div className="min-w-0">
                        <p className="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-slate-500 sm:text-base">
                            {t('dash.student.welcomeBack')}
                            <span className="text-lg sm:text-xl" aria-hidden="true">
                                👋
                            </span>
                        </p>
                        <h1 className="truncate text-[1.55rem] font-bold tracking-tight text-navy sm:text-[1.85rem]">{name || t('dash.student.title')}</h1>
                        <p className="mt-0.5 truncate text-sm text-slate-500">
                            {[student?.class, schoolName].filter(Boolean).join('  |  ')}
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
                            {t('dash.termYear', { term: term || '—', year: academicYear || '—' })}
                        </p>
                    </div>
                </div>
            </section>

            <div className="sd-stats">
                <article className="sd-stat sd-stat-courses">
                    <div>
                        <p className="sd-stat-label">{t('dash.stat.myCourses')}</p>
                        <p className="sd-stat-value">{stats.classes ?? 0}</p>
                    </div>
                    <div className="flex gap-1.5">
                        <span className="sd-stat-icon bg-sky-100 text-sky-700">
                            <CapIcon className="h-5 w-5" />
                        </span>
                        <span className="sd-stat-icon bg-sky-50 text-sky-600">
                            <BooksIcon className="h-5 w-5" />
                        </span>
                    </div>
                </article>
                <article className="sd-stat sd-stat-work">
                    <div>
                        <p className="sd-stat-label">{t('dash.stat.assignments')}</p>
                        <p className="sd-stat-value">{assignmentsTotal}</p>
                    </div>
                    <div className="flex items-start gap-2">
                        {dueCount > 0 ? <span className="sd-due">{t('dash.stat.due', { n: dueCount })}</span> : null}
                        <span className="sd-stat-icon bg-emerald-100 text-emerald-700">
                            <ClipboardIcon className="h-5 w-5" />
                        </span>
                    </div>
                </article>
                <article className="sd-stat sd-stat-quiz">
                    <div>
                        <p className="sd-stat-label">{t('dash.stat.quizzes')}</p>
                        <p className="sd-stat-value">{quizzesTotal}</p>
                    </div>
                    <span className="sd-stat-icon bg-violet-100 text-violet-700">
                        <QuizMarkIcon className="h-5 w-5" />
                    </span>
                </article>
                <article className="sd-stat sd-stat-fees">
                    <div>
                        <p className="sd-stat-label">{t('dash.stat.feesBalance')}</p>
                        <p className="sd-stat-value">{fees == null ? '—' : money(fees)}</p>
                    </div>
                    <span className="sd-stat-icon bg-amber-100 text-amber-700">
                        <CoinsIcon className="h-5 w-5" />
                    </span>
                </article>
            </div>

            <div className="sd-mid">
                <section className="sd-panel">
                    <div className="sd-panel-head">
                        <h2 className="sd-panel-title">{t('dash.todayClasses')}</h2>
                        <Link href={route('timetable.index')} className="sd-panel-link">
                            {t('dash.viewTimetable')}
                            <span aria-hidden="true"> →</span>
                        </Link>
                    </div>
                    {timetable.length === 0 ? (
                        <p className="py-8 text-center text-sm text-slate-500">{t('dash.todayEmptyHelp')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {timetable.map((slot, i) => (
                                <TodayLesson key={slot.id ?? i} slot={slot} />
                            ))}
                        </ul>
                    )}
                </section>

                <section className="sd-panel">
                    <div className="sd-panel-head">
                        <h2 className="sd-panel-title">{t('dash.progress.title')}</h2>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-xs font-medium text-slate-500">{t('dash.progress.overall')}</p>
                            <p className="text-3xl font-bold tabular-nums text-navy">{percent == null ? '—' : `${percent}%`}</p>
                        </div>
                        <ProgressRing value={percent} />
                    </div>
                    <ul className="mt-4 space-y-2 text-sm">
                        <li className="flex items-center justify-between gap-2 text-slate-600">
                            <span>{t('dash.progress.lessons')}</span>
                            <span className="font-semibold tabular-nums text-navy">
                                {progress.lessons_done ?? 0}/{progress.lessons_total ?? 0}
                            </span>
                        </li>
                        <li className="flex items-center justify-between gap-2 text-slate-600">
                            <span>{t('dash.progress.assignments')}</span>
                            <span className="font-semibold tabular-nums text-navy">
                                {progress.assignments_done ?? 0}/{progress.assignments_total ?? 0}
                            </span>
                        </li>
                        <li className="flex items-center justify-between gap-2 text-slate-600">
                            <span>{t('dash.progress.quizzes')}</span>
                            <span className="font-semibold tabular-nums text-navy">
                                {progress.quizzes_done ?? 0}/{progress.quizzes_total ?? 0}
                            </span>
                        </li>
                    </ul>
                </section>

                <section className="sd-panel">
                    <div className="sd-panel-head">
                        <h2 className="sd-panel-title">
                            <BoltIcon className="h-4 w-4 text-teal" />
                            {t('dash.quick.title')}
                        </h2>
                    </div>
                    <div className="sd-actions">
                        {actions.map((a) => (
                            <Link key={a.label} href={a.href} className={`sd-action ${a.tone}`}>
                                {a.icon}
                                <span>{a.label}</span>
                            </Link>
                        ))}
                    </div>
                </section>
            </div>

            <div className="sd-low">
                <section className="sd-panel">
                    <div className="sd-panel-head">
                        <h2 className="sd-panel-title">
                            <MegaphoneMini className="h-4 w-4 text-teal" />
                            {t('dash.recentAnnouncements')}
                    </h2>
                        <Link href={route('announcements.index')} className="sd-panel-link">
                            {t('dash.allAnnouncements')}
                            <span aria-hidden="true"> →</span>
                        </Link>
                    </div>
                    {announcements.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-500">{t('dash.noAnnouncements')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {announcements.map((a) => {
                                const kind = inferKind(a.title, a.body);

                                return (
                                    <li key={a.id}>
                                        <Link href={route('announcements.index')} className="flex items-start gap-3 rounded-2xl px-1 py-2 hover:bg-surface">
                                            <span className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${announcementTone(kind)}`}>
                                                <MegaphoneMini className="h-4 w-4" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold text-navy">{a.title}</p>
                                                {a.body ? <p className="line-clamp-1 text-xs text-slate-500">{a.body}</p> : null}
                                            </div>
                                            {a.published_at ? <span className="shrink-0 text-[11px] text-slate-400">{formatEventDay(a.published_at, locale)}</span> : null}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>

                <section className="sd-panel">
                    <div className="sd-panel-head">
                        <h2 className="sd-panel-title">
                            <CalendarMiniIcon className="h-4 w-4 text-teal" />
                            {t('dash.upcomingEvents')}
                    </h2>
                        <Link href={route('events.index')} className="sd-panel-link">
                            {t('dash.allEvents')}
                            <span aria-hidden="true"> →</span>
                        </Link>
                    </div>
                    {events.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-500">{t('dash.noEvents')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {events.map((e) => (
                                <li key={e.id}>
                                    <Link href={route('events.show', e.id)} className="flex items-start gap-3 rounded-2xl px-1 py-2 hover:bg-surface">
                                        <span className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${eventTone(e.category)}`}>
                                            {eventIcon(e.category)}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-navy">{e.title}</p>
                                            <p className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                                <span>
                                                    {formatEventDay(e.starts_at, locale)}
                                                    {e.starts_at ? `  ${formatEventTime(e.starts_at, locale)}` : ''}
                                                </span>
                                                {e.venue ? (
                                                    <span className="inline-flex items-center gap-1">
                                                        <PinIcon />
                                                        {e.venue}
                                                    </span>
                                                ) : null}
                                            </p>
                                        </div>
                                    </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                </section>
            </div>
        </AppShell>
    );
}
