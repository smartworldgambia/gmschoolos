import LanguageSwitcher from '@/Components/LanguageSwitcher';
import BottomNav from '@/Components/SchoolOS/BottomNav';
import NavIcon from '@/Components/SchoolOS/NavIcon';
import { navLabel } from '@/Components/SchoolOS/navLabel';
import Dropdown from '@/Components/Dropdown';
import { useLocale } from '@/i18n/LocaleProvider';
import { messageText } from '@/lib/academic';
import { assetUrl } from '@/lib/assetUrl';
import { roleLabel } from '@/lib/comm';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function schoolInitials(name) {
    const letters = String(name || '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();

    return letters || 'GS';
}

function SchoolMark({ school, name, className = 'h-9 w-9' }) {
    const logoSrc = school?.logo_url || school?.logo_path || null;

    if (logoSrc) {
        return (
            <img
                src={assetUrl(logoSrc)}
                alt=""
                className={`${className} shrink-0 rounded-xl bg-white object-contain p-0.5 ring-1 ring-black/10`}
            />
        );
    }

    return (
        <div
            className={`flex ${className} shrink-0 items-center justify-center rounded-xl bg-teal text-[11px] font-bold tracking-tight text-white shadow-sm`}
            aria-hidden="true"
        >
            {schoolInitials(name)}
        </div>
    );
}

function personInitials(name) {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    const first = parts[0]?.[0] ?? '';
    const second = parts[1]?.[0] ?? '';

    return `${first}${second}`.toUpperCase() || '?';
}

function UserAvatar({ name, photoUrl, className = 'h-9 w-9', textClass = 'text-[11px]' }) {
    if (photoUrl) {
        return <img src={assetUrl(photoUrl)} alt="" className={`${className} rounded-full object-cover`} />;
    }

    return (
        <span
            className={`flex ${className} items-center justify-center rounded-full bg-teal font-bold tracking-tight text-white ${textClass}`}
            aria-hidden="true"
        >
            {personInitials(name)}
        </span>
    );
}

function AccountNavItem({ item, label, badge = 0, onNavigate }) {
    const isCurrent = Boolean(item.current);

    return (
        <Link
            href={item.href ?? '#'}
            className={`flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] font-medium transition ${
                isCurrent ? 'bg-teal/10 text-navy' : 'text-slate-600 hover:bg-surface hover:text-navy'
            }`}
            aria-current={isCurrent ? 'page' : undefined}
            onClick={onNavigate}
        >
            <span className={`shrink-0 ${isCurrent ? 'text-teal' : 'text-slate-400'}`}>
                {item.icon ?? <NavIcon name={item.key} className="h-5 w-5" />}
            </span>
            <span className="min-w-0 flex-1 truncate">{label}</span>
            {badge > 0 ? (
                <span className="ms-auto shrink-0 rounded-full bg-teal px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">
                    {badge > 99 ? '99+' : badge}
                </span>
            ) : null}
        </Link>
    );
}

function NavItem({ item, collapsed = false, dense = false, label, badge = 0 }) {
    const isCurrent = Boolean(item.current);

    return (
        <Link
            href={item.href ?? '#'}
            className={`group flex items-center rounded-xl font-medium transition ${
                dense ? 'gap-2 px-2.5 py-1.5 text-xs' : 'gap-3 px-3 py-2.5 text-[13px]'
            } ${
                isCurrent
                    ? 'bg-teal text-white shadow-sm'
                    : 'text-slate-300 hover:bg-white/5 hover:text-white'
            } ${collapsed ? 'justify-center px-2' : ''}`}
            aria-current={isCurrent ? 'page' : undefined}
            title={collapsed ? label : undefined}
        >
            <span className={`shrink-0 ${isCurrent ? 'text-white' : 'text-slate-400 group-hover:text-teal'}`}>
                {item.icon ?? <NavIcon name={item.key} className={dense ? 'h-4 w-4' : 'h-5 w-5'} />}
            </span>
            {!collapsed ? <span className="truncate">{label}</span> : null}
            {!collapsed && badge > 0 ? (
                <span className={`ms-auto shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold leading-none ${isCurrent ? 'bg-white/20 text-white' : 'bg-teal text-white'}`}>
                    {badge > 99 ? '99+' : badge}
                </span>
            ) : null}
        </Link>
    );
}

export default function AppShell({
    title,
    nav = [],
    user,
    school,
    academicYear,
    children,
}) {
    const { t } = useLocale();
    const page = usePage();
    const authUser = user ?? page.props.auth?.user ?? null;
    const unreadCount = page.props.notifications?.unread_count ?? 0;
    const badges = page.props.badges ?? {};
    const [mobileOpen, setMobileOpen] = useState(false);
    const photoUrl = authUser?.photo_url || page.props.student?.photo_url || null;

    const displayName = authUser?.name ?? t('shell.user');
    const roleSlug = authUser?.role ?? authUser?.roles?.[0]?.name ?? authUser?.roles?.[0] ?? null;
    const displayRole = roleSlug ? roleLabel(t, roleSlug) : t('shell.member');
    const isPlatform = school?.slug === 'platform' || (page.url ?? '').startsWith('/platform');
    const schoolName = isPlatform ? t('shell.platformConsole') : (school?.name ?? t('shell.brand'));
    const opsSplit = nav.findIndex((item) => item.key === 'monitoring');
    const yearLabel =
        typeof academicYear === 'string'
            ? academicYear
            : academicYear?.name ?? academicYear?.label ?? null;

    const homeHref = nav.find((item) => item.key === 'dashboard')?.href ?? nav[0]?.href ?? '/';
    const bottomItems = nav.slice(0, 5);
    const flashVars = { n: page.props.flash?.count ?? 0, failed: page.props.flash?.failedCount ?? 0 };
    const flashSuccess = messageText(t, page.props.flash?.success, flashVars) || undefined;
    const flashError = messageText(t, page.props.flash?.error) || undefined;
    const flashSchoolUrl = page.props.flash?.school_url;

    useEffect(() => {
        if (!mobileOpen) {
            return undefined;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        function onKey(event) {
            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        }

        document.addEventListener('keydown', onKey);

        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKey);
        };
    }, [mobileOpen]);

    return (
        <div className="min-h-screen bg-surface">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 start-0 z-40 hidden w-64 flex-col bg-navy-600 lg:flex">
                <div className="flex h-[4.25rem] items-center gap-3 border-b border-white/10 px-5">
                    <SchoolMark school={school} name={schoolName} />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-bold text-white">{t('shell.brand')}</p>
                        <p className="truncate text-[11px] text-slate-400">{schoolName}</p>
                    </div>
                </div>

                <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label={t('shell.sidebar')}>
                    {nav.map((item, index) => (
                        <div key={item.href ?? item.key ?? item.name}>
                            {index === opsSplit && opsSplit > 0 ? (
                                <div className="my-3 border-t border-white/10" role="presentation" />
                            ) : null}
                            <NavItem
                                item={item}
                                label={navLabel(item, t)}
                                badge={Number(badges?.[item.key] ?? 0)}
                            />
                        </div>
                    ))}
                </nav>

                <div className="border-t border-white/10 p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-teal/20 text-sm font-semibold text-teal">
                            {displayName.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-white">{displayName}</p>
                            <p className="truncate text-xs capitalize text-slate-400">{displayRole}</p>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Mobile account sheet — profile card, then page menu. Desktop sidebar is unchanged. */}
            {mobileOpen ? (
                <div
                    className="fixed inset-0 z-50 lg:hidden"
                    role="dialog"
                    aria-modal="true"
                    aria-label={t('shell.accountMenu')}
                >
                    <button
                        type="button"
                        className="absolute inset-0 bg-navy/40 backdrop-blur-[2px]"
                        aria-label={t('shell.closeMenu')}
                        onClick={() => setMobileOpen(false)}
                    />
                    <aside className="shell-account">
                        <div className="flex shrink-0 items-start gap-1 px-2 pt-2">
                            <Link
                                href={route('profile.edit')}
                                className="shell-account-card min-w-0 flex-1"
                                onClick={() => setMobileOpen(false)}
                            >
                                <UserAvatar
                                    name={displayName}
                                    photoUrl={photoUrl}
                                    className="h-11 w-11"
                                    textClass="text-sm"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-bold text-navy">{displayName}</span>
                                    <span className="mt-0.5 block truncate text-xs capitalize text-slate-500">{displayRole}</span>
                                    <span className="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-teal whitespace-nowrap">
                                        {t('shell.viewProfile')}
                                        <svg className="h-3.5 w-3.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </span>
                                </span>
                            </Link>
                            <button
                                type="button"
                                className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-400 hover:bg-surface hover:text-navy"
                                aria-label={t('shell.closeMenu')}
                                onClick={() => setMobileOpen(false)}
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <p className="mb-0.5 mt-2 px-4 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                            {t('shell.pages')}
                        </p>
                        <nav className="shell-account-nav" aria-label={t('shell.navigation')}>
                            {nav.map((item) => (
                                <AccountNavItem
                                    key={item.href ?? item.key ?? item.name}
                                    item={item}
                                    label={navLabel(item, t)}
                                    badge={Number(badges?.[item.key] ?? 0)}
                                    onNavigate={() => setMobileOpen(false)}
                                />
                            ))}
                        </nav>

                        <div className="shrink-0 border-t border-slate-100 px-2 py-2">
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-start text-[13px] font-semibold text-red-600 hover:bg-red-50"
                                onClick={() => setMobileOpen(false)}
                            >
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H3" />
                                </svg>
                                {t('shell.logout')}
                            </Link>
                        </div>
                    </aside>
                </div>
            ) : null}

            {/* Main column */}
            <div className="lg:ps-64">
                <header className="shell-topbar">
                    <div className="shell-topbar-row">
                        <Link
                            href={homeHref}
                            className="shell-topbar-brand"
                            aria-label={`${schoolName} — ${t('shell.home')}`}
                            title={schoolName}
                        >
                            <SchoolMark school={school} name={schoolName} className="h-8 w-8 sm:h-9 sm:w-9" />
                            <span className="min-w-0">
                                <span className="block truncate text-[13px] font-bold leading-tight text-navy sm:text-sm">
                                    {schoolName}
                                </span>
                            </span>
                        </Link>
                        {title ? <h1 className="sr-only">{title}</h1> : null}

                        <div className="hidden min-w-0 flex-1 lg:block">
                            <label className="relative w-full max-w-md">
                                <span className="sr-only">{t('shell.search')}</span>
                                <svg
                                    className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                                </svg>
                                <input
                                    type="search"
                                    placeholder={t('shell.searchPlaceholder')}
                                    className="w-full rounded-2xl border-surface-muted bg-surface py-2.5 ps-9 pe-3 text-sm text-navy placeholder:text-slate-400 focus:border-teal focus:ring-teal"
                                    disabled
                                    aria-disabled="true"
                                />
                            </label>
                        </div>

                        <div className="shell-topbar-actions">
                            <LanguageSwitcher compact className="shrink-0" />

                            {yearLabel ? (
                                <span className="hidden rounded-lg bg-surface px-3 py-1.5 text-xs font-semibold text-navy sm:inline-flex">
                                    {yearLabel}
                                </span>
                            ) : null}

                            <Link
                                href={route('notifications.index')}
                                className="shell-topbar-icon"
                                aria-label={
                                    unreadCount
                                        ? t('shell.notificationsUnread', { count: unreadCount })
                                        : t('shell.notifications')
                                }
                            >
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.75}
                                        d="M15 17h5l-1.4-1.4A2 2 0 0118 14.172V11a6 6 0 10-12 0v3.172a2 2 0 01-.586 1.414L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    />
                                </svg>
                                {unreadCount > 0 ? (
                                    <span className="absolute end-1.5 top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white">
                                        {unreadCount > 9 ? '9+' : unreadCount}
                                    </span>
                                ) : null}
                            </Link>

                            <div className="hidden lg:block">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button
                                            type="button"
                                            className="inline-flex h-9 items-center gap-2 rounded-full px-0.5 text-sm font-medium text-navy hover:bg-surface sm:h-10 sm:px-2"
                                            aria-label={t('shell.accountMenu')}
                                        >
                                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-teal/15 text-xs font-bold text-teal">
                                                {displayName.charAt(0).toUpperCase()}
                                            </span>
                                            <span className="hidden text-start md:block">
                                                <span className="block leading-tight">{displayName}</span>
                                                <span className="block text-xs capitalize text-slate-500">
                                                    {displayRole}
                                                </span>
                                            </span>
                                            <svg className="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path
                                                    fillRule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>
                                            {t('shell.profile')}
                                        </Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            {t('shell.logout')}
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>

                            <button
                                type="button"
                                className={`shell-topbar-avatar lg:hidden ${mobileOpen ? 'ring-teal/50' : ''}`}
                                aria-label={t('shell.accountMenu')}
                                aria-expanded={mobileOpen}
                                aria-haspopup="dialog"
                                onClick={() => setMobileOpen(true)}
                            >
                                <UserAvatar name={displayName} photoUrl={photoUrl} className="h-9 w-9" textClass="text-[11px]" />
                            </button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-[88rem] px-4 py-6 pb-[4rem] sm:px-6 lg:px-8 lg:pb-10">
                    {flashSuccess || flashError || flashSchoolUrl ? (
                        <div className="mb-6 space-y-3">
                            {flashSuccess ? (
                                <div
                                    className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                                    role="status"
                                >
                                    {flashSuccess}
                                    {flashSchoolUrl ? (
                                        <p className="mt-2">
                                            <span className="font-medium">{t('domainAdmin.flashUrlLabel')} </span>
                                            <a
                                                href={flashSchoolUrl}
                                                className="font-mono text-emerald-800 underline underline-offset-2"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                {flashSchoolUrl}
                                            </a>
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}
                            {flashError ? (
                                <div
                                    className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                                    role="alert"
                                >
                                    {flashError}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                    {children}
                </main>
            </div>

            <BottomNav items={bottomItems} />
        </div>
    );
}
