import DataTable from '@/Components/SchoolOS/DataTable';
import { Card } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtWhen } from '@/lib/comm';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';

export default function Moderation({ reports = [], handled = [], nav = [], user, school, academicYear }) {
    const { t, locale } = useLocale();

    const hide = (r) => {
        const reason = window.prompt(t('msg.prompt.hide'));
        if (reason && reason.trim()) {
            router.post(route('messages.hide', r.message_id), { reason: reason.trim() }, { preserveScroll: true });
        }
    };

    return (
        <AppShell title={t('msg.moderation')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('msg.moderation')} />
            <PageHeader
                title={t('msg.moderation')}
                subtitle={t('msg.moderationSubtitle')}
                actions={
                    <Link href={route('messages.index')} className="btn-outline">
                        {t('msg.back')}
                    </Link>
                }
            />

            {reports.length === 0 ? (
                <p className="rounded-xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm">{t('msg.mod.empty')}</p>
            ) : (
                <div className="space-y-4">
                    {reports.map((r) => (
                        <Card
                            key={r.id}
                            title={`${t('msg.mod.reportedBy')} ${r.reporter ?? t('msg.removedUser')}`}
                            actions={<span className="text-xs text-slate-400">{fmtWhen(r.at, locale)}</span>}
                        >
                            <p className="text-sm text-slate-500">
                                {t('msg.mod.author')}: <span className="font-medium text-navy">{r.author ?? t('msg.removedUser')}</span>
                            </p>
                            <blockquote className="my-3 whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-sm text-slate-800">{r.excerpt}</blockquote>
                            <p className="text-sm">
                                <span className="font-semibold text-navy">{t('msg.mod.reason')}:</span> {r.reason}
                            </p>
                            <div className="mt-4 flex flex-wrap gap-2">
                                <Link href={route('messages.show', r.conversation_id)} className="btn-outline">
                                    {t('msg.mod.open')}
                                </Link>
                                <button type="button" className="btn-navy" onClick={() => hide(r)}>
                                    {t('msg.hide')}
                                </button>
                                <button type="button" className="btn-outline" onClick={() => router.post(route('messages.dismiss', r.id), {}, { preserveScroll: true })}>
                                    {t('msg.mod.dismiss')}
                                </button>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <h2 className="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('msg.mod.history')}</h2>
            <DataTable
                columns={[
                    { key: 'author', label: t('msg.mod.author') },
                    { key: 'reason', label: t('msg.mod.reason'), cellClassName: 'max-w-xs truncate' },
                    { key: 'status', label: t('acct.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'msg.mod.status', r.status)} /> },
                    { key: 'at', label: t('msg.mod.handledAt'), render: (r) => fmtWhen(r.at, locale) },
                ]}
                rows={handled}
                emptyMessage={t('msg.mod.noHistory')}
            />
        </AppShell>
    );
}
