import ActivityIcon from '@/Components/Lms/ActivityIcon';
import LmsEmptyState from '@/Components/Lms/LmsEmptyState';
import LmsLayout from '@/Components/Lms/LmsLayout';
import { CalendarIcon, ChevronIcon, InfoIcon, MiniCalendar } from '@/Components/Lms/LmsChrome';
import WorkStatus from '@/Components/Lms/WorkStatus';
import { Card, Field, Input } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { courseDisplayTitle, dateKey, fmt, fmtFriendly, num, parseSchoolDate } from '@/lib/lms';
import { useLmsTone } from '@/lib/lmsTone';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function workChip(item, t) {
    if (item.completed) {
        return { label: t('lms.work.completed'), tone: 'done' };
    }
    if (item.type === 'quiz' && (!item.work || item.work === 'upcoming' || item.work === 'missing')) {
        return { label: t('lms.work.notAttempted'), tone: 'idle' };
    }
    if (item.type === 'assignment' && (item.work === 'missing' || item.work === 'upcoming' || item.work === 'due') && item.when) {
        return { label: `${t('lms.assignment.due')} ${fmtFriendly(item.when)}`, tone: 'due' };
    }
    if (item.work === 'overdue') {
        return { label: t('lms.work.overdue'), tone: 'overdue' };
    }
    if (item.work === 'graded' || item.work === 'submitted' || item.work === 'returned') {
        return null;
    }

    return null;
}

function TopicRow({ href, type, title, meta, status = null, work = null, actions = null, done = false, t, large = false, when = null }) {
    const chip = !status ? workChip({ type, work, completed: done, when }, t) : null;

    return (
        <li>
            <div className={`lms-topic-row ${large ? 'py-3' : ''}`}>
                <ActivityIcon type={type} large={large} />
                <div className="min-w-0 flex-1">
                    <Link href={href} className={`${large ? 'text-base' : 'text-sm'} font-medium text-navy hover:underline`}>
                        {title}
                    </Link>
                    {meta ? <p className="text-xs text-slate-500">{meta}</p> : null}
                    <div className="mt-1 flex flex-wrap items-center gap-2">
                        {status}
                        {work && !chip ? <WorkStatus state={work} t={t} /> : null}
                    </div>
                </div>
                {chip ? <span className={`lms-work-chip lms-work-chip-${chip.tone}`}>{chip.label}</span> : null}
                {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
            </div>
        </li>
    );
}

function LessonRow({ lesson, author, t, large }) {
    return (
        <TopicRow
            href={lesson.url}
            type={lesson.content_type || 'lesson'}
            title={lesson.title}
            done={lesson.completed}
            large={large}
            t={t}
            meta={
                author
                    ? [
                          enumLabel(t, 'lms.ctype', lesson.content_type),
                          lesson.duration_minutes ? `${lesson.duration_minutes} ${t('lms.common.minutesShort')}` : null,
                          lesson.restricted ? t('lms.lesson.restricted') : null,
                          lesson.publish_at && lesson.status === 'published' ? `${t('lms.lesson.opensAt')} ${fmt(lesson.publish_at)}` : null,
                      ]
                          .filter(Boolean)
                          .join(' · ')
                    : null
            }
            status={author ? <StatusBadge status={lesson.status} label={enumLabel(t, 'lms.status', lesson.status)} /> : null}
            actions={
                author ? (
                    <Link href={route('lms.lessons.edit', lesson.id)} className="btn-outline">
                        {t('lms.common.edit')}
                    </Link>
                ) : null
            }
        />
    );
}

function Topic({ title, children, actions = null, id = null }) {
    const [open, setOpen] = useState(true);

    return (
        <details
            id={id}
            className="lms-topic lms-anchor"
            open={open}
            onToggle={(e) => {
                const next = e.currentTarget.open;
                if (next !== open) {
                    setOpen(next);
                }
            }}
        >
            <summary>
                <ChevronIcon className="h-4 w-4 shrink-0 text-slate-400 transition" />
                <span className="min-w-0 flex-1 text-base font-bold">{title}</span>
            </summary>
            <div className="border-t border-slate-100 px-5 py-4">
                {actions ? <div className="mb-3 flex flex-wrap justify-end gap-2">{actions}</div> : null}
                {children}
            </div>
        </details>
    );
}

function SectionBlock({ section, author, t, large }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ title: section.title, description: section.description ?? '' });

    return (
        <Topic
            title={section.title}
            actions={
                author ? (
                    <>
                        <button type="button" className="btn-outline" onClick={() => setEditing(!editing)}>
                            {t('lms.common.edit')}
                        </button>
                        <button
                            type="button"
                            className="btn-outline !text-red-700"
                            onClick={() => window.confirm(t('lms.section.confirmDelete')) && router.delete(route('lms.sections.destroy', section.id), { preserveScroll: true })}
                        >
                            {t('lms.common.delete')}
                        </button>
                    </>
                ) : null
            }
        >
            {editing ? (
                <form
                    className="mb-3 grid gap-2 sm:grid-cols-[1fr_2fr_auto]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('lms.sections.update', section.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
                    }}
                >
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                    <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={1000} placeholder={t('lms.common.description')} />
                    <button type="submit" className="btn-teal" disabled={form.processing}>
                        {t('lms.common.save')}
                    </button>
                </form>
            ) : null}
            {section.description ? <p className="mb-3 text-sm text-slate-600">{section.description}</p> : null}
            {section.lessons.length === 0 ? (
                <p className="text-sm text-slate-500">{t('lms.section.empty')}</p>
            ) : (
                <ul>
                    {section.lessons.map((l) => (
                        <LessonRow key={l.id} lesson={l} author={author} t={t} large={large} />
                    ))}
                </ul>
            )}
        </Topic>
    );
}

export default function CourseShow({
    course,
    author,
    details = {},
    sections = [],
    looseLessons = [],
    assignments = [],
    quizzes = [],
    announcements = [],
    progressPercent = null,
    enrolledCount = null,
}) {
    const { t, locale } = useLocale();
    const tone = useLmsTone();
    const sectionForm = useForm({ title: '', description: '' });
    const large = tone.nursery;
    const showQuizzes = !tone.nursery || quizzes.length > 0;
    const displayTitle = courseDisplayTitle(course, details.classes);
    const lessonCount = sections.reduce((n, s) => n + (s.lessons?.length || 0), 0) + looseLessons.length;

    useEffect(() => {
        const id = window.location.hash.replace('#', '');
        if (!id) {
            return;
        }
        const el = document.getElementById(id);
        if (el) {
            requestAnimationFrame(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        }
    }, []);

    const marks = useMemo(() => {
        return [
            ...assignments.map((a) => dateKey(a.due_at)),
            ...quizzes.map((q) => dateKey(q.closes_at)),
        ].filter(Boolean);
    }, [assignments, quizzes]);

    const upcoming = useMemo(() => {
        const rows = [
            ...assignments.filter((a) => a.due_at).map((a) => ({ id: `a-${a.id}`, title: a.title, when: a.due_at, href: a.url, type: 'assignment' })),
            ...quizzes.filter((q) => q.closes_at).map((q) => ({ id: `q-${q.id}`, title: q.title, when: q.closes_at, href: q.url, type: 'quiz' })),
        ];
        rows.sort((a, b) => String(a.when).localeCompare(String(b.when)));
        const start = new Date();
        start.setHours(0, 0, 0, 0);
        const future = rows.filter((r) => {
            const d = parseSchoolDate(r.when);

            return d && d >= start;
        });

        return (future.length ? future : rows).slice(0, 5);
    }, [assignments, quizzes]);

    const actions = author ? (
        <Link href={route('lms.courses.edit', course.id)} className="btn-outline">
            {t('lms.course.edit')}
        </Link>
    ) : null;

    const subtitle = [course.subject, details.teacher, tone.className].filter(Boolean).join(' · ');

    return (
        <LmsLayout title={displayTitle} subtitle={subtitle} course={course} author={author} active="overview" actions={actions}>
            <div className="lms-course-body">
                <div className="min-w-0">
                    <Topic title={t('lms.course.general')} id="content">
                        <div className="mb-4 rounded-2xl bg-sky-50/80 px-4 py-4">
                            <p className="flex items-center gap-2 text-sm font-bold text-navy">
                                <ActivityIcon type="course" className="!h-8 !w-8" />
                                {t('lms.course.welcomeTitle', { title: displayTitle })}
                            </p>
                            {details.description ? (
                                <p className={`mt-2 whitespace-pre-wrap text-slate-700 ${large ? 'text-base leading-relaxed' : 'text-sm'}`}>{details.description}</p>
                            ) : null}
                            {details.objectives?.length ? (
                                <>
                                    <h3 className="mt-3 text-sm font-semibold text-navy">{t('lms.course.objectives')}</h3>
                                    <ul className="mt-1 list-disc ps-5 text-sm text-slate-700">
                                        {details.objectives.map((o, i) => (
                                            <li key={i}>{o}</li>
                                        ))}
                                    </ul>
                                </>
                            ) : null}
                </div>

                        <ul>
                            <li>
                                <div className="lms-topic-row">
                                    <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-800">
                                        <InfoIcon className="h-4 w-4" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-navy">{t('lms.course.courseInfo')}</p>
                                        <p className="text-xs text-slate-500">
                                            {[course.code, details.teacher, (details.classes || []).join(', ')].filter(Boolean).join(' · ') || '—'}
                                        </p>
                </div>
            </div>
                            </li>
                            <li>
                                <Link href={route('lms.courses.announcements', course.id)} className="lms-topic-row">
                                    <ActivityIcon type="announcement" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-navy">{tone.nursery ? t('lms.tone.nursery.news') : t('lms.tab.announcements')}</p>
                                        {announcements.length ? (
                                            <p className="text-xs text-slate-500">{announcements[0].title}</p>
                                        ) : (
                                            <p className="text-xs text-slate-500">{t('lms.ann.emptyHelp')}</p>
                                        )}
                                    </div>
                                    {announcements.length ? <span className="lms-work-chip lms-work-chip-idle">{announcements.length}</span> : null}
                                </Link>
                            </li>
                            {looseLessons.length ? (
                                <li>
                                    <div className="lms-topic-row">
                                        <ActivityIcon type="document" />
                                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-navy">{t('lms.course.resources')}</p>
                                            <p className="text-xs text-slate-500">{t('lms.dash.lessonsCount', { n: lessonCount })}</p>
                                        </div>
                                                        </div>
                                </li>
                                                        ) : null}
                        </ul>

                        {looseLessons.length && !sections.length ? (
                            <ul className="mt-2">
                                {looseLessons.map((l) => (
                                    <LessonRow key={l.id} lesson={l} author={author} t={t} large={large} />
                                                ))}
                                            </ul>
                        ) : null}

                        {author ? (
                            <div className="mt-4">
                                <Link href={route('lms.lessons.create', course.id)} className="btn-teal">
                                    {t('lms.course.addContent')}
                                </Link>
                        </div>
                        ) : null}
                    </Topic>

                    {sections.map((s) => (
                        <SectionBlock key={s.id} section={s} author={author} t={t} large={large} />
                    ))}

                    {looseLessons.length && sections.length ? (
                        <Topic title={t('lms.course.otherLessons')}>
                            <ul>
                                {looseLessons.map((l) => (
                                    <LessonRow key={l.id} lesson={l} author={author} t={t} large={large} />
                                ))}
                            </ul>
                        </Topic>
                    ) : null}

                    <Topic
                        title={t('lms.course.assignments')}
                        id="assignments"
                        actions={
                            author ? (
                                <Link href={route('lms.assignments.create', course.id)} className="btn-navy">
                                    {t('lms.course.create')}
                                </Link>
                            ) : null
                        }
                    >
                            {assignments.length === 0 ? (
                            <p className="py-4 text-center text-sm text-slate-500">{t('lms.course.noAssignments')}</p>
                        ) : (
                            <ul>
                                {assignments.map((a) => (
                                    <TopicRow
                                        key={`a-${a.id}`}
                                        href={a.url}
                                        type="assignment"
                                        title={a.title}
                                        large={large}
                                        t={t}
                                        when={a.due_at}
                                        work={a.work_state}
                                        meta={author ? `${t('lms.assignment.due')}: ${fmt(a.due_at)} · ${num(a.max_score)} ${t('lms.q.pointsShort')}` : null}
                                        status={
                                            author ? (
                                                <>
                                                    <StatusBadge status={a.status} label={enumLabel(t, 'lms.status', a.status)} />
                                                    {a.to_mark ? (
                                                        <span className="text-xs font-semibold text-amber-700">
                                                            {t('lms.assignment.toMark')}: {a.to_mark}
                                                        </span>
                                                    ) : null}
                                                </>
                                            ) : a.score !== null ? (
                                                <span className="text-xs font-semibold text-navy">
                                                    {num(a.score)} / {num(a.max_score)}
                                                </span>
                                            ) : null
                                        }
                                    />
                                    ))}
                                </ul>
                            )}
                    </Topic>

                    {showQuizzes ? (
                        <Topic
                            title={t('lms.course.quizzes')}
                            id="quizzes"
                            actions={
                                author && !tone.nursery ? (
                                    <Link href={route('lms.quizzes.create', course.id)} className="btn-navy">
                                        {t('lms.course.create')}
                                    </Link>
                                ) : null
                            }
                        >
                            {quizzes.length === 0 ? (
                                <p className="py-4 text-center text-sm text-slate-500">{t('lms.course.noQuizzes')}</p>
                            ) : (
                                <ul>
                                    {quizzes.map((q) => (
                                        <TopicRow
                                            key={`q-${q.id}`}
                                            href={q.url}
                                            type="quiz"
                                            title={q.title}
                                            large={large}
                                            t={t}
                                            when={q.closes_at}
                                            work={q.work_state}
                                            meta={
                                                author
                                                    ? [
                                                          q.closes_at ? `${t('lms.quiz.closes')}: ${fmt(q.closes_at)}` : null,
                                                          tone.nursery ? null : `${t('lms.quiz.attempts')}: ${q.attempts_used}/${q.max_attempts}`,
                                                      ]
                                                          .filter(Boolean)
                                                          .join(' · ')
                                                    : null
                                            }
                                            status={author ? <StatusBadge status={q.status} label={enumLabel(t, 'lms.status', q.status)} /> : null}
                                        />
                                    ))}
                                </ul>
                            )}
                        </Topic>
                    ) : (
                        <div id="quizzes" className="lms-anchor hidden" />
                    )}

                    {sections.length === 0 && looseLessons.length === 0 ? (
                        <div className="mb-4">
                            <LmsEmptyState title={t('lms.course.noLessons')} body={author ? t('lms.course.noLessonsHelp') : t('lms.course.noLessonsLearner')} />
                        </div>
                    ) : null}

                    {author ? (
                        <Card title={t('lms.section.add')}>
                            <form
                                className="grid gap-2 sm:grid-cols-[1fr_2fr_auto]"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    sectionForm.post(route('lms.sections.store', course.id), { preserveScroll: true, onSuccess: () => sectionForm.reset() });
                                }}
                            >
                                <Field error={sectionForm.errors.title}>
                                    <Input value={sectionForm.data.title} onChange={(e) => sectionForm.setData('title', e.target.value)} placeholder={t('lms.section.title')} required maxLength={190} />
                                </Field>
                                <Input value={sectionForm.data.description} onChange={(e) => sectionForm.setData('description', e.target.value)} placeholder={t('lms.common.description')} maxLength={1000} />
                                <button type="submit" className="btn-navy" disabled={sectionForm.processing}>
                                    {t('lms.section.addButton')}
                                </button>
                            </form>
                        </Card>
                    ) : null}

                    {author && enrolledCount !== null ? (
                        <p className="mt-4 text-sm text-slate-500">
                            {t('lms.index.enrolled')}: <span className="font-semibold text-navy">{enrolledCount}</span>
                            {details.classes?.length ? ` · ${t('lms.course.audience')}: ${details.classes.join(', ')}` : ''}
                        </p>
                    ) : null}
                </div>

                <aside className="space-y-4">
                    {progressPercent !== null ? (
                        <div className="lms-side-card">
                            <p className="text-sm font-bold text-navy">{tone.nursery ? t('lms.tone.nursery.progress') : t('lms.course.completion')}</p>
                            <div className="mt-3 flex items-center gap-3">
                                <div
                                    className={`min-w-0 flex-1 overflow-hidden rounded-full bg-slate-100 ${large ? 'h-3' : 'h-2'}`}
                                    role="progressbar"
                                    aria-valuenow={progressPercent}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                >
                                    <div className="h-full rounded-full bg-teal" style={{ width: `${progressPercent}%` }} />
                                </div>
                                <p className="shrink-0 text-sm font-bold text-teal">{progressPercent}%</p>
                            </div>
                        </div>
                    ) : null}

                    <div className="lms-side-card">
                        <p className="mb-1 flex items-center gap-2 text-sm font-bold text-navy">
                            <CalendarIcon className="h-4 w-4 text-teal" />
                            {t('lms.course.calendar')}
                        </p>
                        <MiniCalendar marks={marks} locale={locale} prevLabel={t('lms.course.prevMonth')} nextLabel={t('lms.course.nextMonth')} />
                    </div>

                    <div id="upcoming" className="lms-side-card lms-anchor">
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <p className="text-sm font-bold text-navy">{t('lms.course.upcoming')}</p>
                            <a href="#assignments" className="sd-panel-link text-xs">
                                {t('lms.course.goCalendar')}
                            </a>
                        </div>
                        {upcoming.length === 0 ? (
                            <p className="text-sm text-slate-500">{t('lms.dash.noTimeline')}</p>
                        ) : (
                            <ul className="space-y-2">
                                {upcoming.map((item) => (
                                    <li key={item.id}>
                                        <Link href={item.href} className="lms-topic-row !px-0">
                                            <ActivityIcon type={item.type} />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-navy">{item.title}</p>
                                                <p className="text-xs text-slate-500">{fmtFriendly(item.when, locale)}</p>
                                            </div>
                                        </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                </aside>
            </div>
        </LmsLayout>
    );
}
