import LmsEmptyState from '@/Components/Lms/LmsEmptyState';
import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmt } from '@/lib/lms';
import { Link, useForm } from '@inertiajs/react';

export default function Forum({ course, author, threads = [] }) {
    const { t } = useLocale();
    const form = useForm({ type: 'discussion', title: '', body: '' });

    return (
        <LmsLayout title={t('lms.forum.title')} subtitle={t('lms.forum.subtitle')} course={course} author={author} active="forum">
            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="space-y-3">
                    {threads.length === 0 ? <LmsEmptyState title={t('lms.forum.empty')} body={t('lms.forum.emptyHelp')} /> : null}
                    {threads.map((th) => (
                        <Link key={th.id} href={th.url} className="block rounded-xl bg-white p-4 shadow-sm transition hover:ring-2 hover:ring-teal/40">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <p className="font-semibold text-navy">
                                    {th.is_pinned ? '📌 ' : ''}
                                    {th.title}
                                </p>
                                <div className="flex flex-wrap gap-1">
                                    <StatusBadge status={th.type === 'question' ? 'info' : 'draft'} label={enumLabel(t, 'lms.forum.type', th.type)} />
                                    {th.answered ? <StatusBadge status="passed" label={t('lms.forum.answered')} /> : null}
                                    {th.is_locked ? <StatusBadge status="inactive" label={t('lms.forum.locked')} /> : null}
                                </div>
                            </div>
                            <p className="mt-1 text-xs text-slate-500">
                                {th.author} · {th.replies} {t('lms.forum.replies')} · {fmt(th.last_post_at)}
                            </p>
                        </Link>
                    ))}
                </div>
                <Card title={t('lms.forum.new')}>
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('lms.threads.store', course.id), { preserveScroll: true });
                        }}
                    >
                        <ErrorSummary errors={form.errors} />
                        <Field label={t('lms.forum.kind')}>
                            <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                <option value="discussion">{t('lms.forum.type.discussion')}</option>
                                <option value="question">{t('lms.forum.type.question')}</option>
                            </Select>
                        </Field>
                        <Field label={t('lms.common.title')}>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('lms.forum.body')}>
                            <Textarea rows={5} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required maxLength={10000} />
                        </Field>
                        <button type="submit" className="btn-teal" disabled={form.processing}>
                            {t('lms.forum.post')}
                        </button>
                    </form>
                </Card>
            </div>
        </LmsLayout>
    );
}
