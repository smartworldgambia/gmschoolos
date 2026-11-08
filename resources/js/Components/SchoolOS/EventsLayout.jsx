import NavIcon from '@/Components/SchoolOS/NavIcon';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

const ALL = [
    { key: 'calendar', route: 'events.index' },
    { key: 'venues', route: 'events.venues.index' },
    { key: 'reports', route: 'events.reports.index' },
    { key: 'setup', route: 'events.setup.index' },
];

function HeaderDeco() {
    return (
        <svg viewBox="0 0 180 110" className="h-[5.5rem] w-auto" aria-hidden="true">
            <ellipse cx="100" cy="96" rx="52" ry="8" fill="#E3EAF2" opacity="0.8" />
            <rect x="78" y="28" width="78" height="64" rx="12" fill="#FFFFFF" stroke="#E3EAF2" strokeWidth="2" />
            <rect x="78" y="28" width="78" height="18" rx="12" fill="#08B7A5" />
            <rect x="78" y="38" width="78" height="8" fill="#08B7A5" />
            <circle cx="94" cy="37" r="3.5" fill="#FFFFFF" />
            <circle cx="140" cy="37" r="3.5" fill="#FFFFFF" />
            <rect x="90" y="56" width="12" height="10" rx="2" fill="#E3EAF2" />
            <rect x="108" y="56" width="12" height="10" rx="2" fill="#08B7A5" opacity="0.85" />
            <rect x="126" y="56" width="12" height="10" rx="2" fill="#E3EAF2" />
            <rect x="90" y="70" width="12" height="10" rx="2" fill="#E3EAF2" />
            <rect x="108" y="70" width="12" height="10" rx="2" fill="#C9D6E5" />
            <rect x="126" y="70" width="12" height="10" rx="2" fill="#E3EAF2" />
            <path d="M58 34c-12 0-22 8-22 18 0 14 22 28 22 28s22-14 22-28c0-10-10-18-22-18z" fill="#F59E0B" />
            <circle cx="58" cy="50" r="7" fill="#FDE68A" />
            <path d="M138 14l2.2 5.2 5.2 2.2-5.2 2.2-2.2 5.2-2.2-5.2-5.2-2.2 5.2-2.2 2.2-5.2z" fill="#08B7A5" />
            <circle cx="34" cy="22" r="3" fill="#2878D0" />
        </svg>
    );
}

export default function EventsLayout({ canManage = true, title, subtitle, actions, current, shell = {}, children }) {
    const { t } = useLocale();
    const tabs = canManage ? ALL : [{ key: 'calendar', route: 'events.index' }];

    return (
        <AppShell title={title} nav={shell.nav} user={shell.user} school={shell.school} academicYear={shell.academicYear}>
            <Head title={title} />
            <div className="relative mb-5 overflow-hidden">
                <div className="pointer-events-none absolute -end-8 -top-10 hidden h-40 w-72 rounded-full bg-teal/10 blur-2xl lg:block" aria-hidden="true" />
                <div className="pointer-events-none absolute end-24 top-0 hidden h-32 w-48 rounded-full bg-brand-blue/10 blur-2xl lg:block" aria-hidden="true" />
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="flex min-w-0 items-start gap-3 sm:gap-4">
                        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal text-white shadow-sm">
                            <NavIcon name="events" className="h-6 w-6" />
                        </span>
                        <div className="min-w-0">
                            <h1 className="text-[26px] font-bold tracking-tight text-navy sm:text-[30px]">{title}</h1>
                            {subtitle ? <p className="mt-1 max-w-xl text-sm leading-relaxed text-slate-500 sm:text-[15px]">{subtitle}</p> : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 lg:ps-4">
                        <div className="me-2 hidden lg:block">
                            <HeaderDeco />
                        </div>
                        {actions}
                    </div>
                </div>
            </div>
            {tabs.length > 1 ? (
                <nav className="mb-5 flex gap-1 overflow-x-auto rounded-2xl border border-surface-muted bg-white p-1.5 shadow-soft" aria-label={t('ev.tabs.label')}>
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={route(tab.route)}
                            className={`inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition ${
                                tab.key === current ? 'bg-teal text-white shadow-sm' : 'text-slate-600 hover:bg-surface hover:text-navy'
                            }`}
                            aria-current={tab.key === current ? 'page' : undefined}
                        >
                            {t(`ev.tab.${tab.key}`)}
                        </Link>
                    ))}
                </nav>
            ) : null}
            {children}
        </AppShell>
    );
}
