import LmsEmptyState from '@/Components/Lms/LmsEmptyState';
import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Field, Input, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { fmt } from '@/lib/lms';
import { router, useForm } from '@inertiajs/react';

export default function Announcements({ course, author, announcements = [] }) {
    const { t } = useLocale();
    const form = useForm({ title: '', body: '', publish_at: '', is_pinned: false });

    return (
        <LmsLayout title={t('lms.ann.title')} subtitle={t('lms.ann.subtitle')} course={course} author={author} active="announcements">
            <div className={author ? 'grid gap-6 lg:grid-cols-[2fr_1fr]' : ''}>
                <div className="space-y-4">
                    {announcements.length === 0 ? <LmsEmptyState title={t('lms.ann.empty')} body={t('lms.ann.emptyHelp')} /> : null}
                    {announcements.map((a) => (
                        <Card
                            key={a.id}
                            title={`${a.is_pinned ? '📌 ' : ''}${a.title}`}
                            actions={
                                <>
                                    {a.scheduled ? <StatusBadge status="scheduled" label={`${t('lms.ann.scheduledFor')} ${fmt(a.publish_at)}`} /> : null}
                                    {a.delete_url ? (
                                        <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('lms.ann.confirmDelete')) && router.delete(a.delete_url, { preserveScroll: true })}>
                                            {t('lms.common.delete')}
                                        </button>
                                    ) : null}
                                </>
                            }
                        >
                            <p className="whitespace-pre-wrap text-sm text-slate-800">{a.body}</p>
                            <p className="mt-2 text-xs text-slate-400">
                                {a.author ? `${a.author} · ` : ''}
                                {fmt(a.publish_at ?? a.created_at)}
                            </p>
                        </Card>
                    ))}
                </div>
                {author ? (
                    <Card title={t('lms.ann.new')}>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post(route('lms.announcements.store', course.id), { preserveScroll: true, onSuccess: () => form.reset() });
                            }}
                        >
                            <ErrorSummary errors={form.errors} />
                            <Field label={t('lms.common.title')}>
                                <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                            </Field>
                            <Field label={t('lms.ann.body')}>
                                <Textarea rows={6} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required maxLength={10000} />
                            </Field>
                            <Field label={t('lms.ann.publishAt')} hint={t('lms.ann.publishAtHelp')}>
                                <Input type="datetime-local" value={form.data.publish_at} onChange={(e) => form.setData('publish_at', e.target.value)} />
                            </Field>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" className="rounded border-gray-300" checked={form.data.is_pinned} onChange={(e) => form.setData('is_pinned', e.target.checked)} />
                                {t('lms.ann.pin')}
                            </label>
                            <p className="text-xs text-slate-500">{t('lms.ann.notifyNote')}</p>
                            <button type="submit" className="btn-teal" disabled={form.processing}>
                                {t('lms.ann.post')}
                            </button>
                        </form>
                    </Card>
                ) : null}
            </div>
        </LmsLayout>
    );
}
