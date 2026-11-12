import { CourseCover, PlayMini } from '@/Components/Lms/LmsChrome';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { enumLabel } from '@/lib/academic';
import { courseDisplayTitle } from '@/lib/lms';
import { Link } from '@inertiajs/react';

export default function CourseCard({ course, author, t, tone, index = 0, layout = 'grid' }) {
    const progress = course.progress_percent;
    const classes = (course.classes ?? []).filter(Boolean);
    const contentHref = `${course.url}#content`;
    const activityHref = author ? route('lms.courses.progress', course.id) : null;
    const showProgress = !author && progress !== null && progress !== undefined;
    const title = courseDisplayTitle(course, classes);
    const list = layout === 'list';

    return (
        <article className={`lms-course-card ${list ? 'lms-course-card-list' : ''}`}>
            <CourseCover course={course} tone={tone} index={index} />
            <div className={`flex min-w-0 flex-1 flex-col px-5 ${tone.nursery ? 'py-5' : 'py-4'}`}>
                <div className="flex items-start justify-between gap-2">
                    <h2 className={`font-bold leading-snug text-navy ${tone.nursery ? 'text-xl' : 'text-lg'}`}>{title}</h2>
                    {author ? <StatusBadge status={course.status} label={enumLabel(t, 'lms.status', course.status)} /> : null}
                </div>
                <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    {(() => {
                        const classroom = course.classroom || classes[0];
                        const line = [course.subject, classroom, course.teacher].filter(Boolean).join(' · ');
                        return line ? <span>{line}</span> : null;
                    })()}
                    {author || course.enrolled ? <span>{t('lms.card.participants', { n: course.enrolled || 0 })}</span> : null}
                </p>

                {showProgress ? (
                    <div className="mt-3">
                        <div
                            className={`overflow-hidden rounded-full bg-slate-100 ${tone.nursery ? 'h-2.5' : 'h-1.5'}`}
                            role="progressbar"
                            aria-valuenow={progress}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={t('lms.index.progress')}
                        >
                            <div className="h-full rounded-full bg-teal" style={{ width: `${progress}%` }} />
                        </div>
                        <p className="mt-1 text-end text-xs font-medium text-slate-500">{t('lms.card.complete', { n: progress })}</p>
                    </div>
                ) : null}

                <div className="mt-auto flex flex-wrap gap-2 pt-4">
                    {author ? (
                        <>
                            <Link href={course.url} className="btn-navy">
                                {t('lms.card.manage')}
                            </Link>
                            <Link href={contentHref} className="btn-outline">
                                {t('lms.card.content')}
                            </Link>
                            <Link href={activityHref} className="btn-outline">
                                {t('lms.card.activity')}
                            </Link>
                        </>
                    ) : (
                        <Link href={course.url} className="btn-navy gap-1.5">
                            <PlayMini />
                            {t('lms.card.continue')}
                        </Link>
                    )}
                </div>
            </div>
        </article>
    );
}
