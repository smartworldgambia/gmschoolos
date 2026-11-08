import ActivityIcon from '@/Components/Lms/ActivityIcon';
import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmt, fmtBytes } from '@/lib/lms';
import { useLmsTone } from '@/lib/lmsTone';
import { Link, router } from '@inertiajs/react';

function Material({ m, t }) {
    return (
        <li className="py-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-medium text-navy">{m.title}</p>
                    <p className="text-xs text-slate-500">
                        {enumLabel(t, 'lms.mtype', m.type)}
                        {m.size ? ` · ${fmtBytes(m.size)}` : ''}
                    </p>
                </div>
                {m.file_url || m.url ? (
                    <a href={m.file_url ?? m.url} target="_blank" rel="noreferrer noopener" className="btn-outline">
                        {m.file_url ? t('lms.common.download') : t('lms.common.open')}
                    </a>
                ) : null}
            </div>
            {m.inline && m.file_url ? (
                /^video\//.test(m.mime ?? '') ? (
                    <video controls className="mt-3 w-full max-w-2xl rounded-lg" src={m.file_url} />
                ) : /^audio\//.test(m.mime ?? '') ? (
                    <audio controls className="mt-3 w-full max-w-xl" src={m.file_url} />
                ) : /^image\//.test(m.mime ?? '') ? (
                    <img className="mt-3 max-h-96 rounded-lg" src={m.file_url} alt={m.title} />
                ) : null
            ) : null}
        </li>
    );
}

export default function LessonShow({ course, author, lesson, materials = [], completed = false, canComplete = false, previousUrl = null, nextUrl = null }) {
    const { t } = useLocale();
    const tone = useLmsTone();

    return (
        <LmsLayout
            title={lesson.title}
            subtitle={[lesson.section, enumLabel(t, 'lms.ctype', lesson.content_type), lesson.duration_minutes ? `${lesson.duration_minutes} ${t('lms.common.minutesShort')}` : null].filter(Boolean).join(' · ')}
            course={course}
            author={author}
            actions={
                author ? (
                    <Link href={route('lms.lessons.edit', lesson.id)} className="btn-outline">
                        {t('lms.common.edit')}
                    </Link>
                ) : null
            }
        >
            {author && lesson.status !== 'published' ? (
                <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <StatusBadge status={lesson.status} label={enumLabel(t, 'lms.status', lesson.status)} /> {t('lms.lesson.draftNotice')}
                    {lesson.publish_at ? ` ${t('lms.lesson.opensAt')} ${fmt(lesson.publish_at)}` : ''}
                </p>
            ) : null}

            {lesson.objectives?.length ? (
                <Card title={t('lms.course.objectives')} className="mb-6">
                    <ul className="list-disc ps-5 text-sm text-slate-700">
                        {lesson.objectives.map((o, i) => (
                            <li key={i}>{o}</li>
                        ))}
                    </ul>
                </Card>
            ) : null}

            {lesson.content ? (
                <Card className="mb-6">
                    <div className={`flex items-start gap-3 whitespace-pre-wrap leading-relaxed text-slate-800 ${tone.nursery ? 'text-base' : 'text-sm'}`}>
                        <ActivityIcon type={lesson.content_type || 'lesson'} large={tone.nursery} />
                        <div className="min-w-0 flex-1">{lesson.content}</div>
                    </div>
                </Card>
            ) : null}

            {lesson.external_url ? (
                <Card title={t('lms.lesson.external')} className="mb-6">
                    <p className="mb-3 text-sm text-slate-600">{t('lms.lesson.externalHelp')}</p>
                    <Link href={lesson.external_url} className="btn-navy">
                        {t('lms.lesson.openExternal')}
                    </Link>
                </Card>
            ) : null}

            {materials.length ? (
                <Card title={t('lms.lesson.materials')} className="mb-6">
                    <ul className="divide-y divide-slate-100">
                        {materials.map((m) => (
                            <Material key={m.id} m={m} t={t} />
                        ))}
                    </ul>
                </Card>
            ) : null}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-2">
                    {previousUrl ? (
                        <Link href={previousUrl} className="btn-outline">
                            {t('lms.lesson.previous')}
                        </Link>
                    ) : null}
                    {nextUrl ? (
                        <Link href={nextUrl} className="btn-outline">
                            {t('lms.lesson.next')}
                        </Link>
                    ) : null}
                </div>
                {canComplete ? (
                    completed ? (
                        <StatusBadge status="completed" label={t('lms.lesson.completed')} />
                    ) : (
                        <button type="button" className="btn-teal" onClick={() => router.post(route('lms.lessons.complete', lesson.id), {}, { preserveScroll: true })}>
                            {tone.nursery ? t('lms.tone.nursery.markDone') : t('lms.lesson.markDone')}
                        </button>
                    )
                ) : null}
            </div>
        </LmsLayout>
    );
}
