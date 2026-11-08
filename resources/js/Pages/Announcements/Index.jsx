import {
    AnnouncementsActions,
    AnnouncementsHeader,
    EmptyIllustration,
    inferKind,
    kindTone,
    SearchIcon,
} from '@/Components/Comm/AnnouncementsChrome';
import DataTable from '@/Components/SchoolOS/DataTable';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtWhen } from '@/lib/comm';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const KIND_FILTERS = ['academic', 'meeting', 'sports'];

export default function Index({ feed = [], mine = [], canAuthor = false, nav = [], user, school, academicYear }) {
    const { t, locale } = useLocale();
    const [tab, setTab] = useState('feed');
    const [q, setQ] = useState('');
    const [filter, setFilter] = useState('all');

    const unreadCount = feed.filter((a) => a.unread).length;

    const feedRows = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return feed.filter((a) => {
            const kind = inferKind(a.title, a.body);
            if (filter === 'unread' && !a.unread) {
                return false;
            }
            if (KIND_FILTERS.includes(filter) && kind !== filter) {
                return false;
            }
            if (!needle) {
                return true;
            }
            const hay = `${a.title ?? ''} ${a.body ?? ''} ${a.author ?? ''}`.toLowerCase();

            return hay.includes(needle);
        });
    }, [feed, q, filter]);

    const mineRows = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) {
            return mine;
        }

        return mine.filter((a) => `${a.title ?? ''} ${a.author ?? ''} ${a.audience ?? ''}`.toLowerCase().includes(needle));
    }, [mine, q]);

    const columns = [
        { key: 'title', label: t('ann.col.title'), cellClassName: 'max-w-xs truncate', render: (a) => <span className="font-medium">{a.title}</span> },
        { key: 'audience', label: t('ann.col.audience'), render: (a) => enumLabel(t, 'ann.audience', a.audience) },
        { key: 'status', label: t('acct.col.status'), render: (a) => <StatusBadge status={a.status} label={enumLabel(t, 'ann.status', a.status)} /> },
        { key: 'when', label: t('ann.col.when'), render: (a) => fmtWhen(a.published_at ?? a.publish_at, locale) },
        {
            key: 'reach',
            label: t('ann.col.reach'),
            render: (a) => (a.stats ? t('ann.reach', { read: a.stats.read, total: a.stats.recipients }) : '—'),
        },
        {
            key: 'actions',
            label: t('acct.col.actions'),
            render: (a) => (
                <div className="flex items-center gap-2">
                    {a.editable ? (
                        <Link href={route('announcements.edit', a.id)} className="btn-outline !px-3 !py-1.5 text-xs">
                            {t('ann.edit')}
                        </Link>
                    ) : null}
                    {a.status !== 'cancelled' ? (
                        <button
                            type="button"
                            className="btn-outline !px-3 !py-1.5 text-xs !text-red-700"
                            onClick={() => window.confirm(t('ann.confirmCancel')) && router.post(route('announcements.cancel', a.id), {}, { preserveScroll: true })}
                        >
                            {t('ann.cancel')}
                        </button>
                    ) : null}
                </div>
            ),
        },
    ];

    const filters = [
        { id: 'all', label: t('ann.filter.all') },
        { id: 'unread', label: t('ann.filter.unread'), count: unreadCount },
        { id: 'academic', label: t('ann.filter.academic') },
        { id: 'meeting', label: t('ann.filter.meeting') },
        { id: 'sports', label: t('ann.filter.sports') },
    ];

    return (
        <AppShell title={t('ann.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('ann.title')} />
            <AnnouncementsHeader title={t('ann.title')} subtitle={t('ann.subtitle')} actions={<AnnouncementsActions canAuthor={canAuthor} />} />

            <div className="mb-5 flex flex-col gap-3 rounded-[1.4rem] border border-surface-muted bg-white p-3 shadow-soft sm:p-4">
                <label className="flex items-center gap-2 rounded-full border border-surface-muted bg-white px-3.5 py-2.5 focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                    <SearchIcon />
                    <span className="sr-only">{t('ann.search')}</span>
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('ann.search')}
                        className="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-navy placeholder:text-slate-400 focus:ring-0"
                    />
                </label>
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-1.5" role="tablist" aria-label={t('ann.title')}>
                        {filters.map((item) => {
                            const on = filter === item.id;

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={on}
                                    onClick={() => {
                                        setFilter(item.id);
                                        setTab('feed');
                                    }}
                                    className={`inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold transition ${
                                        on ? 'bg-teal text-white shadow-sm' : 'bg-[#F0F4F9] text-navy hover:bg-slate-100'
                                    }`}
                                >
                                    {item.label}
                                    {item.id === 'unread' && item.count > 0 ? (
                                        <span className={`inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold ${on ? 'bg-white/20 text-white' : 'bg-teal text-white'}`}>
                                            {item.count}
                                        </span>
                                    ) : null}
                                </button>
                            );
                        })}
                    </div>
                    {canAuthor ? (
                        <div className="flex flex-wrap gap-1.5" role="tablist">
                            {['feed', 'mine'].map((k) => (
                                <button
                                    key={k}
                                    type="button"
                                    role="tab"
                                    aria-selected={tab === k}
                                    onClick={() => setTab(k)}
                                    className={`inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold transition ${
                                        tab === k ? 'bg-navy text-white' : 'bg-[#F0F4F9] text-navy hover:bg-slate-100'
                                    }`}
                                >
                                    {t(`ann.tab.${k}`)}
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>
            </div>

            {tab === 'feed' ? (
                feedRows.length === 0 ? (
                    <section className="overflow-hidden rounded-[1.6rem] border border-surface-muted bg-white shadow-soft">
                        <div className="flex flex-col items-center px-6 py-16 text-center sm:px-12 sm:py-20">
                            <EmptyIllustration />
                            <p className="mt-6 text-lg font-bold text-navy sm:text-xl">{t('ann.empty')}</p>
                            <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">{t('ann.emptyHelp')}</p>
                        </div>
                    </section>
                ) : (
                    <div className="space-y-4">
                        {feedRows.map((a) => {
                            const kind = inferKind(a.title, a.body);

                            return (
                                <article
                                    key={a.id}
                                    className={`relative overflow-hidden rounded-[1.4rem] border bg-white p-5 shadow-soft sm:p-6 ${
                                        a.unread ? 'border-teal/25' : 'border-surface-muted'
                                    }`}
                                >
                                    {a.unread ? <span className="absolute inset-y-0 start-0 w-1 bg-teal" aria-hidden="true" /> : null}
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {kind ? (
                                                    <span className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${kindTone(kind)}`}>
                                                        {kind === 'sports' ? t('ann.kind.sports') : kind === 'meeting' ? t('ann.kind.meeting') : t('ann.kind.academic')}
                                                    </span>
                                                ) : null}
                                                {a.unread ? <StatusBadge status="info" label={t('ann.new.badge')} /> : null}
                                            </div>
                                            <h2 className="mt-2 text-lg font-bold tracking-tight text-navy">{a.title}</h2>
                                        </div>
                                        {a.unread ? <span className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-teal" aria-hidden="true" /> : null}
                                    </div>
                                    <p className="mt-3 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-600">{a.body}</p>
                                    <p className="mt-4 text-xs text-slate-400">
                                        {a.author ? `${t('ann.publishedBy', { name: a.author })} · ` : ''}
                                        {fmtWhen(a.at, locale)}
                                    </p>
                                </article>
                            );
                        })}
                    </div>
                )
            ) : (
                <DataTable columns={columns} rows={mineRows} emptyMessage={t('ann.mine.empty')} />
            )}
        </AppShell>
    );
}
