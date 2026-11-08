import CourseCard from '@/Components/Lms/CourseCard';
import ActivityIcon from '@/Components/Lms/ActivityIcon';
import LmsEmptyState from '@/Components/Lms/LmsEmptyState';
import LmsLayout from '@/Components/Lms/LmsLayout';
import {
    BookIcon,
    ClipboardIcon,
    CourseCover,
    EmptyIllustration,
    GridIcon,
    ListIcon,
    LmsHeader,
    ProgressIcon,
    QuizIcon,
    SearchIcon,
} from '@/Components/Lms/LmsChrome';
import WorkStatus from '@/Components/Lms/WorkStatus';
import { useLocale } from '@/i18n/LocaleProvider';
import { COURSE_STATUSES, fmt } from '@/lib/lms';
import { dashSubtitle, dashTitle, useLmsTone } from '@/lib/lmsTone';
import { enumLabel } from '@/lib/academic';
import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const HASH_VIEWS = {
    '#assignments': 'assignments',
    '#quizzes': 'quizzes',
    '#materials': 'materials',
    '#content': 'materials',
};

function viewFromHash() {
    if (typeof window === 'undefined') {
        return 'courses';
    }

    return HASH_VIEWS[window.location.hash] || 'courses';
}

function hashForView(view) {
    if (view === 'assignments') {
        return '#assignments';
    }
    if (view === 'quizzes') {
        return '#quizzes';
    }
    if (view === 'materials') {
        return '#materials';
    }

    return '';
}

function averageProgress(courses) {
    const values = courses.map((c) => c.progress_percent).filter((v) => v !== null && v !== undefined);
    if (!values.length) {
        return null;
    }

    return Math.round(values.reduce((n, v) => n + Number(v), 0) / values.length);
}

function WorkList({ items, t, emptyTitle, emptyBody }) {
    if (!items.length) {
        return <LmsEmptyState title={emptyTitle} body={emptyBody} />;
    }

    return (
        <ul className="divide-y divide-slate-100">
            {items.map((item) => (
                <li key={`${item.kind}-${item.id}`}>
                    <Link href={item.url} className="lms-activity -mx-1">
                        <ActivityIcon type={item.kind} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium text-navy">{item.title}</p>
                            <p className="truncate text-xs text-slate-500">
                                {item.course}
                                {item.when ? ` · ${fmt(item.when)}` : ''}
                                {item.to_mark ? ` · ${t('lms.assignment.toMark')}: ${item.to_mark}` : ''}
                            </p>
                        </div>
                        <WorkStatus state={item.state} t={t} />
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function CourseWorkLinks({ courses, tone, t, hash, countKey, countKeyName, actionLabel, emptyTitle, emptyBody }) {
    const rows = courses.filter((c) => (c[countKey] || 0) > 0);
    if (!rows.length) {
        return <LmsEmptyState title={emptyTitle} body={emptyBody} />;
    }

    return (
        <ul className="grid gap-3 sm:grid-cols-2">
            {rows.map((course, i) => (
                <li key={course.id}>
                    <Link
                        href={`${course.url}${hash}`}
                        className="flex items-center gap-3 rounded-2xl border border-surface-muted bg-white p-3 shadow-soft transition hover:border-teal/40 hover:shadow-md"
                    >
                        <div className="w-20 shrink-0 overflow-hidden rounded-xl">
                            <CourseCover course={course} tone={tone} index={i} compact />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-semibold text-navy">{course.title}</p>
                            <p className="mt-0.5 text-xs text-slate-500">{t(countKeyName, { n: course[countKey] || 0 })}</p>
                        </div>
                        <span className="btn-outline">{actionLabel}</span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export default function Index({ courses = [], mode = 'none', can = {}, timeline = [], recent = [] }) {
    const { t } = useLocale();
    const tone = useLmsTone();
    const author = mode === 'author';
    const [q, setQ] = useState('');
    const [classFilter, setClassFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [scope, setScope] = useState('current');
    const [sort, setSort] = useState('title');
    const [layout, setLayout] = useState('grid');
    const [view, setView] = useState(viewFromHash);

    useEffect(() => {
        const sync = () => setView(viewFromHash());
        window.addEventListener('hashchange', sync);

        return () => window.removeEventListener('hashchange', sync);
    }, []);

    const goView = (next) => {
        const hash = hashForView(next);
        const path = window.location.pathname + window.location.search + hash;
        window.history.replaceState(null, '', path);
        setView(next);
    };

    const classOptions = useMemo(() => [...new Set(courses.flatMap((c) => c.classes ?? []).filter(Boolean))].sort(), [courses]);

    const shown = useMemo(() => {
        const needle = q.trim().toLowerCase();

        const rows = courses.filter((c) => {
            if (scope === 'current' && author && c.status !== 'published') return false;
            if (statusFilter && c.status !== statusFilter) return false;
            if (classFilter && !(c.classes ?? []).includes(classFilter)) return false;
            if (!needle) return true;
            const hay = [c.title, c.subject, c.teacher, c.code, c.category, ...(c.classes ?? [])].join(' ').toLowerCase();

            return hay.includes(needle);
        });

        const copy = [...rows];
        copy.sort((a, b) => {
            if (sort === 'progress') {
                return (Number(b.progress_percent) || 0) - (Number(a.progress_percent) || 0);
            }
            if (sort === 'title_desc') {
                return String(b.title || '').localeCompare(String(a.title || ''));
            }

            return String(a.title || '').localeCompare(String(b.title || ''));
        });

        return copy;
    }, [courses, q, classFilter, statusFilter, scope, sort, author]);

    const dueSoon = timeline.filter((i) => i.state === 'due' || i.state === 'to_mark').length;
    const assignmentOverdue = timeline.filter((i) => i.kind === 'assignment' && i.state === 'overdue').length;
    const assignmentDue = timeline.filter((i) => i.kind === 'assignment' && (i.state === 'due' || i.state === 'overdue')).length;
    const toMark = timeline.reduce((n, i) => n + (i.to_mark || 0), 0);
    const learners = courses.reduce((n, c) => n + (c.enrolled || 0), 0);
    const pendingItems = timeline.filter((i) => (i.to_mark || 0) > 0 || i.state === 'to_mark');
    const firstCourse = shown[0] || courses[0];
    const assignmentsTotal = courses.reduce((n, c) => n + (c.assignments_count || 0), 0);
    const quizzesTotal = courses.reduce((n, c) => n + (c.quizzes_count || 0), 0);
    const overall = author ? null : averageProgress(courses);
    const assignmentItems = timeline.filter((i) => i.kind === 'assignment');
    const quizItems = timeline.filter((i) => i.kind === 'quiz');

    const emptyTitle =
        tone.role === 'guardian' ? t('lms.dash.emptyGuardian') : author ? t('lms.index.emptyStaff') : tone.nursery ? t('lms.tone.nursery.emptyLearner') : t('lms.index.emptyLearner');
    const emptyBody =
        tone.role === 'guardian' ? t('lms.dash.emptyGuardianHelp') : author ? t('lms.dash.emptyStaffHelp') : t('lms.dash.emptyLearnerHelp');

    const pageTitle = dashTitle(t, { band: tone.band, role: tone.role, author });
    const pageSubtitle = author
        ? dashSubtitle(t, { band: tone.band, author, className: tone.className, userName: tone.userName })
        : tone.nursery
          ? dashSubtitle(t, { band: tone.band, author, className: tone.className, userName: tone.userName })
          : t('lms.index.subtitleLearner');

    const headerActions = (
        <>
            {can.create ? (
                <Link href={route('lms.courses.create')} className="btn-teal">
                    {t('lms.index.new')}
                </Link>
            ) : null}
            {can.banks && !tone.nursery ? (
                <Link href={route('lms.banks.index')} className="btn-outline">
                    {t('lms.index.banks')}
                </Link>
            ) : null}
            {can.external && !tone.simple ? (
                <Link href={route('lms.external.index')} className="btn-outline">
                    {t('lms.index.external')}
                </Link>
            ) : null}
        </>
    );

    const tabs = [
        { key: 'courses', label: t('lms.dash.myCourses') },
        { key: 'assignments', label: t('lms.tab.assignments') },
        { key: 'quizzes', label: t('lms.tab.quizzes'), hide: tone.nursery },
        { key: 'materials', label: t('lms.lesson.materials') },
    ].filter((tab) => !tab.hide);

    const assignmentTrend = !author && assignmentOverdue > 0 ? t('lms.dash.overdueCount', { n: assignmentOverdue }) : !author && assignmentDue > 0 ? t('lms.dash.dueCount', { n: assignmentDue }) : null;

    const learnerStats = [
        { title: t('lms.dash.statEnrolled'), value: courses.length, tone: 'navy', icon: <BookIcon /> },
        { title: t('lms.index.assignments'), value: assignmentsTotal, tone: 'orange', icon: <ClipboardIcon />, trend: assignmentTrend },
        { title: t('lms.index.quizzes'), value: quizzesTotal, tone: 'purple', icon: <QuizIcon /> },
        { title: t('lms.dash.statProgress'), value: overall === null ? '—' : `${overall}%`, tone: 'teal', icon: <ProgressIcon /> },
    ];
    const authorStats = [
        { title: t('lms.dash.statsCourses'), value: courses.length, tone: 'navy', icon: <BookIcon /> },
        { title: t('lms.dash.statsLearners'), value: learners, tone: 'teal', icon: <CapStatIcon /> },
        { title: t('lms.dash.statsPending'), value: toMark, tone: 'orange', icon: <ClipboardIcon /> },
        { title: t('lms.work.dueSoon'), value: dueSoon, tone: 'blue', icon: <ProgressIcon /> },
    ];
    const stats = author ? authorStats : learnerStats;

    return (
        <LmsLayout title={pageTitle} subtitle={pageSubtitle} actions={null} chrome="none">
            <LmsHeader title={pageTitle} subtitle={pageSubtitle} actions={headerActions} />

            <div className="lms-summary-grid mb-5">
                {stats.map((s) => (
                    <div key={s.title} className={`lms-stat lms-stat-${s.tone}`} role="group" aria-label={s.title}>
                        <div className="min-w-0">
                            <p className="sd-stat-label">{s.title}</p>
                            <p className="sd-stat-value">{s.value}</p>
                            {s.trend ? <p className="mt-2 text-xs font-medium text-amber-700">{s.trend}</p> : null}
                        </div>
                        <span className="sd-stat-icon bg-white/80 text-navy" aria-hidden="true">
                            {s.icon}
                        </span>
                    </div>
                ))}
            </div>

            <nav className="lms-dash-tabs" aria-label={t('lms.tab.label')}>
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        className={`lms-dash-tab ${view === tab.key ? 'lms-dash-tab-active' : 'lms-dash-tab-idle'}`}
                        aria-current={view === tab.key ? 'page' : undefined}
                        onClick={() => goView(tab.key)}
                    >
                        {tab.label}
                    </button>
                ))}
            </nav>

            {view === 'courses' ? (
                <>
                    {courses.length > 0 ? (
                        <div className="lms-toolbar">
                            <label className="flex min-w-[14rem] flex-1 items-center gap-2 rounded-xl border border-surface-muted bg-white px-3 py-2 shadow-soft focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                                <SearchIcon />
                                <span className="sr-only">{t('lms.dash.search')}</span>
                                <input
                                    type="search"
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder={t('lms.dash.searchCourses')}
                                    className="w-full border-0 bg-transparent p-0 text-sm text-navy placeholder:text-slate-400 focus:border-transparent focus:outline-none focus:ring-0"
                                    autoComplete="off"
                                />
                            </label>
                            <label className="block text-sm font-medium text-slate-700">
                                <span className="sr-only">{t('lms.dash.filterClass')}</span>
                                <select
                                    value={statusFilter || scope}
                                    onChange={(e) => {
                                        const v = e.target.value;
                                        if (v === 'current') {
                                            setScope('current');
                                            setStatusFilter('');
                                        } else if (v === 'all') {
                                            setScope('all');
                                            setStatusFilter('');
                                        } else {
                                            setScope('all');
                                            setStatusFilter(v);
                                        }
                                    }}
                                    className="ui-input min-w-[10rem]"
                                >
                                    <option value="current">{t('lms.dash.filterCurrent')}</option>
                                    {author ? <option value="all">{t('lms.dash.filterAll')}</option> : null}
                                    {author
                                        ? COURSE_STATUSES.map((s) => (
                                              <option key={s} value={s}>
                                                  {enumLabel(t, 'lms.status', s)}
                                              </option>
                                          ))
                                        : null}
                                </select>
                            </label>
                            {classOptions.length > 1 ? (
                                <label className="block text-sm font-medium text-slate-700">
                                    <span className="sr-only">{t('lms.dash.filterClass')}</span>
                                    <select value={classFilter} onChange={(e) => setClassFilter(e.target.value)} className="ui-input min-w-[10rem]">
                                        <option value="">{t('lms.dash.filterAll')}</option>
                                        {classOptions.map((name) => (
                                            <option key={name} value={name}>
                                                {name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            ) : null}
                            <label className="block text-sm font-medium text-slate-700">
                                <span className="sr-only">{t('lms.dash.sortLabel')}</span>
                                <select value={sort} onChange={(e) => setSort(e.target.value)} className="ui-input min-w-[11rem]">
                                    <option value="title">{t('lms.dash.sortTitle')}</option>
                                    <option value="title_desc">{t('lms.dash.sortTitleDesc')}</option>
                                    {!author ? <option value="progress">{t('lms.dash.sortProgress')}</option> : null}
                                </select>
                            </label>
                            <div className="lms-layout-toggle" role="group" aria-label={t('lms.dash.layout')}>
                                <button
                                    type="button"
                                    className={`lms-layout-btn ${layout === 'grid' ? 'lms-layout-btn-on' : ''}`}
                                    aria-pressed={layout === 'grid'}
                                    aria-label={t('lms.dash.layoutGrid')}
                                    onClick={() => setLayout('grid')}
                                >
                                    <GridIcon />
                                </button>
                                <button
                                    type="button"
                                    className={`lms-layout-btn ${layout === 'list' ? 'lms-layout-btn-on' : ''}`}
                                    aria-pressed={layout === 'list'}
                                    aria-label={t('lms.dash.layoutList')}
                                    onClick={() => setLayout('list')}
                                >
                                    <ListIcon />
                                </button>
                            </div>
                        </div>
                    ) : null}

                    {courses.length === 0 ? (
                        <div className="flex flex-col items-center rounded-[1.6rem] border border-surface-muted bg-white px-4 py-12 text-center shadow-soft sm:py-16">
                            <EmptyIllustration />
                            <p className="mt-6 text-lg font-bold text-navy">{emptyTitle}</p>
                            <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">{emptyBody}</p>
                            {can.create ? (
                                <Link href={route('lms.courses.create')} className="btn-teal mt-5">
                                    {t('lms.index.new')}
                                </Link>
                            ) : null}
                        </div>
                    ) : shown.length === 0 ? (
                        <LmsEmptyState icon="search" title={t('lms.dash.noMatches')} body={t('lms.dash.noMatchesHelp')} />
                    ) : (
                        <div
                            className={
                                layout === 'list'
                                    ? 'grid gap-4'
                                    : `lms-course-grid ${tone.nursery ? 'lms-course-grid-simple sm:grid-cols-1 lg:grid-cols-2' : ''}`
                            }
                        >
                            {shown.map((course, i) => (
                                <CourseCard key={course.id} course={course} author={author} t={t} tone={tone} index={i} layout={layout} />
                            ))}
                        </div>
                    )}

                    {author && pendingItems.length > 0 ? (
                        <div className="mt-6 rounded-[1.6rem] border border-surface-muted bg-white p-5 shadow-soft">
                            <h3 className="mb-3 text-sm font-semibold text-navy">{t('lms.dash.pendingGrade')}</h3>
                            <ul>
                                {pendingItems.slice(0, 5).map((item) => (
                                    <li key={`${item.kind}-${item.id}`} className="flex items-center justify-between gap-2 border-b border-slate-100 py-3 last:border-b-0">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-navy">{item.title}</p>
                                            <p className="truncate text-xs text-slate-500">
                                                {item.course}
                                                {item.to_mark ? ` · ${item.to_mark}` : ''}
                                            </p>
                                        </div>
                                        <Link href={item.url} className="btn-outline">
                                            {t('lms.assignment.mark')}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    {author && firstCourse ? (
                        <nav className="mt-6 flex flex-wrap gap-2" aria-label={t('lms.dash.quickActions')}>
                            <Link href={route('lms.lessons.create', firstCourse.id)} className="btn-outline">
                                {t('lms.dash.addContent')}
                            </Link>
                            <Link href={route('lms.assignments.create', firstCourse.id)} className="btn-outline">
                                {t('lms.dash.createAssignment')}
                            </Link>
                            {tone.nursery ? null : (
                                <Link href={route('lms.quizzes.create', firstCourse.id)} className="btn-outline">
                                    {t('lms.dash.createQuiz')}
                                </Link>
                            )}
                        </nav>
                    ) : null}

                    {!author && recent.length ? (
                        <div className="mt-6 rounded-[1.6rem] border border-surface-muted bg-white p-5 shadow-soft">
                            <h3 className="mb-3 text-sm font-semibold text-navy">{t('lms.dash.recent')}</h3>
                            <ul className="space-y-1">
                                {recent.map((a) => (
                                    <li key={a.id}>
                                        <Link href={a.url} className="lms-activity -mx-1">
                                            <ActivityIcon type="announcement" />
                                            <div className="min-w-0">
                                                <p className="truncate font-medium text-navy">{a.title}</p>
                                                <p className="text-xs text-slate-400">
                                                    {a.course}
                                                    {a.when ? ` · ${fmt(a.when)}` : ''}
                                                </p>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </>
            ) : (
                <section className="overflow-hidden rounded-[1.6rem] border border-surface-muted bg-white p-4 shadow-soft sm:p-6">
                    {view === 'assignments' ? (
                        assignmentItems.length ? (
                            <WorkList items={assignmentItems} t={t} emptyTitle={t('lms.dash.noAssignments')} emptyBody={t('lms.dash.noAssignmentsHelp')} />
                        ) : (
                            <CourseWorkLinks
                                courses={courses}
                                tone={tone}
                                t={t}
                                hash="#assignments"
                                countKey="assignments_count"
                                countKeyName="lms.dash.assignmentsCount"
                                actionLabel={t('lms.common.open')}
                                emptyTitle={t('lms.dash.noAssignments')}
                                emptyBody={t('lms.dash.noAssignmentsHelp')}
                            />
                        )
                    ) : null}

                    {view === 'quizzes' ? (
                        quizItems.length ? (
                            <WorkList items={quizItems} t={t} emptyTitle={t('lms.dash.noQuizzes')} emptyBody={t('lms.dash.noQuizzesHelp')} />
                        ) : (
                            <CourseWorkLinks
                                courses={courses}
                                tone={tone}
                                t={t}
                                hash="#quizzes"
                                countKey="quizzes_count"
                                countKeyName="lms.dash.quizzesCount"
                                actionLabel={t('lms.common.open')}
                                emptyTitle={t('lms.dash.noQuizzes')}
                                emptyBody={t('lms.dash.noQuizzesHelp')}
                            />
                        )
                    ) : null}

                    {view === 'materials' ? (
                        <CourseWorkLinks
                            courses={courses}
                            tone={tone}
                            t={t}
                            hash="#content"
                            countKey="lessons_count"
                            countKeyName="lms.dash.lessonsCount"
                            actionLabel={t('lms.dash.openMaterials')}
                            emptyTitle={t('lms.course.noMaterialsYet')}
                            emptyBody={t('lms.dash.noMaterialsHelp')}
                        />
                    ) : null}
                </section>
            )}
        </LmsLayout>
    );
}

function CapStatIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"
            />
        </svg>
    );
}
