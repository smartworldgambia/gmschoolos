import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmt } from '@/lib/lms';
import { Link, router, useForm } from '@inertiajs/react';

export default function Thread({ course, author, thread, posts = [] }) {
    const { t } = useLocale();
    const form = useForm({ body: '' });
    const act = (name, ...params) => router.post(route(name, params), {}, { preserveScroll: true });

    return (
        <LmsLayout
            title={thread.title}
            subtitle={`${thread.author ?? ''} · ${fmt(thread.created_at)}`}
            course={course}
            author={author}
            active="forum"
            actions={
                <>
                    {author ? (
                        <>
                            <button type="button" className="btn-outline" onClick={() => act('lms.threads.pin', thread.id)}>
                                {thread.is_pinned ? t('lms.forum.unpin') : t('lms.forum.pin')}
                            </button>
                            <button type="button" className="btn-outline" onClick={() => act('lms.threads.lock', thread.id)}>
                                {thread.is_locked ? t('lms.forum.unlock') : t('lms.forum.lock')}
                            </button>
                        </>
                    ) : null}
                    <Link href={thread.forum_url} className="btn-outline">
                        {t('lms.common.back')}
                    </Link>
                </>
            }
        >
            <div className="mb-3 flex flex-wrap gap-2">
                <StatusBadge status={thread.type === 'question' ? 'info' : 'draft'} label={enumLabel(t, 'lms.forum.type', thread.type)} />
                {thread.is_locked ? <StatusBadge status="inactive" label={t('lms.forum.locked')} /> : null}
            </div>
            <Card className="mb-6">
                <p className="whitespace-pre-wrap text-sm text-slate-800">{thread.body}</p>
            </Card>

            <h2 className="mb-3 text-base font-semibold text-navy">
                {t('lms.forum.replies')} ({posts.length})
            </h2>
            <ul className="mb-6 space-y-3">
                {posts.length === 0 ? <li className="text-sm text-slate-500">{t('lms.forum.noReplies')}</li> : null}
                {posts.map((p) => (
                    <li key={p.id}>
                        <Card className={thread.answer_post_id === p.id ? 'ring-2 ring-emerald-400' : ''}>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-xs text-slate-500">
                                    {p.author} · {fmt(p.created_at)}
                                </p>
                                <div className="flex flex-wrap items-center gap-2">
                                    {thread.answer_post_id === p.id ? <StatusBadge status="passed" label={t('lms.forum.bestAnswer')} /> : null}
                                    {thread.can_mark && !p.hidden ? (
                                        <button type="button" className="btn-outline" onClick={() => act('lms.threads.answer', thread.id, p.id)}>
                                            {thread.answer_post_id === p.id ? t('lms.forum.unmark') : t('lms.forum.markAnswer')}
                                        </button>
                                    ) : null}
                                    {author ? (
                                        <button type="button" className="btn-outline" onClick={() => act('lms.posts.hide', p.id)}>
                                            {p.hidden ? t('lms.forum.unhide') : t('lms.forum.hide')}
                                        </button>
                                    ) : null}
                                </div>
                            </div>
                            {p.hidden ? <p className="mt-1 text-xs font-semibold text-amber-700">{t('lms.forum.hiddenNotice')}</p> : null}
                            {p.body !== null ? <p className="mt-2 whitespace-pre-wrap text-sm text-slate-800">{p.body}</p> : <p className="mt-2 text-sm italic text-slate-400">{t('lms.forum.removed')}</p>}
                        </Card>
                    </li>
                ))}
            </ul>

            {thread.is_locked ? (
                <p className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">{t('lms.forum.lockedNotice')}</p>
            ) : (
                <Card title={t('lms.forum.reply')}>
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('lms.threads.reply', thread.id), { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                    >
                        <ErrorSummary errors={form.errors} />
                        <Textarea rows={4} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required maxLength={10000} />
                        <button type="submit" className="btn-teal" disabled={form.processing}>
                            {t('lms.forum.postReply')}
                        </button>
                    </form>
                </Card>
            )}
        </LmsLayout>
    );
}
