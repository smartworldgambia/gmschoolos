import { Pager, Select } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtAgo } from '@/lib/comm';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';

function BellIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M15 17h5l-1.4-1.4A2 2 0 0118 14.172V11a6 6 0 10-12 0v3.172a2 2 0 01-.586 1.414L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
    );
}

function GearIcon() {
    return (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
            />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    );
}

function CheckIcon() {
    return (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.2} d="M5 13l4 4L19 7" />
        </svg>
    );
}

function GridIcon() {
    return (
        <svg className="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 6h6v6H4V6zm10 0h6v6h-6V6zM4 16h6v6H4v-6zm10 0h6v6h-6v-6z" />
        </svg>
    );
}

function HeaderDeco() {
    return (
        <svg viewBox="0 0 180 110" className="h-[5.5rem] w-auto" aria-hidden="true">
            <ellipse cx="100" cy="96" rx="52" ry="8" fill="#E3EAF2" opacity="0.8" />
            <rect x="82" y="36" width="74" height="54" rx="12" fill="#FFFFFF" stroke="#E3EAF2" strokeWidth="2" />
            <circle cx="98" cy="52" r="6" fill="#08B7A5" />
            <path d="M96 52l1.5 1.5L101 50" fill="none" stroke="#FFFFFF" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" />
            <rect x="108" y="49" width="36" height="5" rx="2.5" fill="#C9D6E5" />
            <circle cx="98" cy="68" r="6" fill="#08B7A5" />
            <path d="M96 68l1.5 1.5L101 66" fill="none" stroke="#FFFFFF" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" />
            <rect x="108" y="65" width="28" height="5" rx="2.5" fill="#C9D6E5" />
            <path d="M62 24c-14 0-26 12-26 26v12c0 2.4-1.1 4.6-2.9 6L28 72h68l-5.1-4A7.5 7.5 0 0190 62V50c0-14-12-26-28-26z" fill="#F59E0B" />
            <path d="M62 28c-12 0-22 10-22 22v11c0 2-1 3.8-2.4 5L34 70h56l-3.6-4A6.5 6.5 0 0186 61V50c0-12-10-22-24-22z" fill="#FBBF24" />
            <rect x="56" y="20" width="12" height="10" rx="5" fill="#F59E0B" />
            <circle cx="62" cy="48" r="5" fill="#FDE68A" opacity="0.55" />
            <path d="M138 16l2.2 5.2 5.2 2.2-5.2 2.2-2.2 5.2-2.2-5.2-5.2-2.2 5.2-2.2 2.2-5.2z" fill="#08B7A5" />
            <circle cx="34" cy="22" r="3" fill="#2878D0" />
        </svg>
    );
}

function EmptyIllustration() {
    return (
        <svg viewBox="0 0 240 180" className="mx-auto h-40 w-auto sm:h-48" aria-hidden="true">
            <circle cx="120" cy="86" r="68" fill="#EAF3FF" />
            <path d="M196 46l2.4 5.6 5.6 2.4-5.6 2.4-2.4 5.6-2.4-5.6-5.6-2.4 5.6-2.4 2.4-5.6z" fill="#2878D0" opacity="0.75" />
            <circle cx="42" cy="48" r="4" fill="#08B7A5" opacity="0.45" />
            <path d="M42 124h156c8 0 14-6 14-14V86L120 122 28 86v24c0 8 6 14 14 14z" fill="#1F6FD0" />
            <path d="M28 78c0-8 6-14 14-14h156c8 0 14 6 14 14v8L120 122 28 86V78z" fill="#3B8BEA" />
            <path d="M42 64h156c8 0 14 6 14 14v8L120 122 28 78c0-8 6-14 14-14z" fill="#5BA3F5" />
            <rect x="86" y="44" width="68" height="56" rx="7" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2.2" />
            <rect x="100" y="60" width="40" height="5" rx="2.5" fill="#C9D6E5" />
            <rect x="100" y="71" width="32" height="5" rx="2.5" fill="#C9D6E5" />
            <rect x="100" y="82" width="24" height="5" rx="2.5" fill="#08B7A5" opacity="0.6" />
            <ellipse cx="120" cy="154" rx="52" ry="8" fill="#E3EAF2" />
        </svg>
    );
}

export default function Index({ notifications, unreadCount = 0, filters = {}, categories = [], nav = [], user, school, academicYear }) {
    const { t, locale } = useLocale();
    const rows = notifications?.data ?? [];

    const filter = (patch) => router.get(route('notifications.index'), { ...filters, ...patch }, { preserveState: true, replace: true });

    return (
        <AppShell title={t('notif.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('notif.title')} />

            <div className="relative mb-5 overflow-hidden">
                <div
                    className="pointer-events-none absolute -end-8 -top-10 hidden h-40 w-72 rounded-full bg-teal/10 blur-2xl lg:block"
                    aria-hidden="true"
                />
                <div
                    className="pointer-events-none absolute end-24 top-0 hidden h-32 w-48 rounded-full bg-brand-blue/10 blur-2xl lg:block"
                    aria-hidden="true"
                />
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex min-w-0 items-start gap-3 sm:gap-4">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal text-white shadow-sm">
                            <BellIcon />
                        </span>
                        <div className="min-w-0">
                            <h1 className="text-[26px] font-bold tracking-tight text-navy sm:text-[30px]">{t('notif.title')}</h1>
                            <p className="mt-1 max-w-xl text-sm leading-relaxed text-slate-500 sm:text-[15px]">{t('notif.subtitle')}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 lg:ps-4">
                        <div className="me-2 hidden lg:block">
                            <HeaderDeco />
                        </div>
                        <Link href={route('notifications.preferences')} className="btn-outline gap-2">
                            <GearIcon />
                            {t('notif.preferences')}
                        </Link>
                        <button
                            type="button"
                            className="btn-teal gap-2"
                            disabled={unreadCount === 0}
                            onClick={() => router.post(route('notifications.read-all'))}
                        >
                            <CheckIcon />
                            {t('notif.markAll')}
                        </button>
                    </div>
                </div>
            </div>

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <label className="inline-flex min-w-0 items-center gap-2 rounded-xl border border-surface-muted bg-white px-3 py-1.5 shadow-soft focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                    <GridIcon />
                    <span className="sr-only">{t('notif.category')}</span>
                    <Select
                        className="!w-auto min-w-[12rem] border-0 bg-transparent py-1 shadow-none focus:border-transparent focus:ring-0"
                        aria-label={t('notif.category')}
                        value={filters.category ?? ''}
                        onChange={(e) => filter({ category: e.target.value || undefined })}
                    >
                        <option value="">{t('notif.allCategories')}</option>
                        {categories.map((c) => (
                            <option key={c} value={c}>
                                {enumLabel(t, 'notif.category', c)}
                            </option>
                        ))}
                    </Select>
                </label>
                <label className="inline-flex items-center gap-2 text-sm text-slate-600 sm:ms-3">
                    <input
                        type="checkbox"
                        className="h-4 w-4 rounded border-slate-300 text-teal focus:ring-teal"
                        checked={Boolean(filters.unread)}
                        onChange={(e) => filter({ unread: e.target.checked ? 1 : undefined })}
                    />
                    {t('notif.unreadOnly')}
                </label>
            </div>

            <section className="overflow-hidden rounded-[1.6rem] border border-surface-muted bg-white shadow-soft">
                {rows.length === 0 ? (
                    <div className="flex flex-col items-center px-6 py-16 text-center sm:px-12 sm:py-24">
                        <EmptyIllustration />
                        <p className="mt-6 text-lg font-bold text-navy sm:text-xl">{t('notif.empty')}</p>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">{t('notif.emptyHelp')}</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {rows.map((n) => (
                            <li
                                key={n.id}
                                className={`flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:px-6 ${n.read_at ? '' : 'bg-teal/5'}`}
                            >
                                <div className="flex min-w-0 gap-3">
                                    <span
                                        className={`mt-2 h-2.5 w-2.5 shrink-0 rounded-full ${n.read_at ? 'bg-transparent' : 'bg-teal'}`}
                                        aria-hidden="true"
                                    />
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className={`text-sm text-navy ${n.read_at ? 'font-medium' : 'font-bold'}`}>{n.title}</p>
                                            {n.category ? (
                                                <StatusBadge status={n.level === 'warning' ? 'warning' : 'info'} label={enumLabel(t, 'notif.category', n.category)} />
                                            ) : null}
                                        </div>
                                        {n.body ? <p className="mt-1 line-clamp-3 break-words text-sm text-slate-600">{n.body}</p> : null}
                                        <p className="mt-2 text-xs text-slate-400">{fmtAgo(n.created_at, locale)}</p>
                                    </div>
                                </div>
                                <div className="flex shrink-0 flex-wrap items-center gap-2 ps-5 sm:flex-col sm:items-end">
                                    {n.action_url ? (
                                        <Link
                                            href={n.action_url}
                                            className="btn-outline !px-3 !py-1.5 text-xs"
                                            onClick={() => !n.read_at && router.post(route('notifications.read', n.id), {}, { preserveScroll: true, preserveState: true })}
                                        >
                                            {t('notif.open')}
                                        </Link>
                                    ) : null}
                                    {!n.read_at ? (
                                        <button
                                            type="button"
                                            className="text-xs font-medium text-slate-500 underline"
                                            onClick={() => router.post(route('notifications.read', n.id), {}, { preserveScroll: true })}
                                        >
                                            {t('notif.markRead')}
                                        </button>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
            <Pager links={notifications?.links} />
        </AppShell>
    );
}
