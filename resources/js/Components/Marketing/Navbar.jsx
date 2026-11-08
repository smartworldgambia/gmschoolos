import LanguageSwitcher from '@/Components/LanguageSwitcher';
import BrandLogo from '@/Components/Marketing/BrandLogo';
import { IconChevron, IconClose, IconMenu } from '@/Components/Marketing/Icons';
import MobileNavDrawer from '@/Components/Marketing/MobileNavDrawer';
import { handleSectionNavClick } from '@/Components/Marketing/scrollToSection';
import { useMarketingLocale } from '@/i18n/MarketingLocale';
import { Link } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';

function buildNav(t) {
    return [
        { label: t('nav.home'), href: '#top' },
        {
            label: t('nav.features'),
            href: '#features',
            children: [
                { label: t('nav.schoolManagement'), href: '#features' },
                { label: t('nav.learningLms'), href: '#features' },
                { label: t('nav.feesExams'), href: '#features' },
                { label: t('nav.moreModules'), href: '#features' },
            ],
        },
        {
            label: t('nav.solutions'),
            href: '#solutions',
            children: [
                { label: t('nav.forAdmins'), href: '#solutions' },
                { label: t('nav.forTeachers'), href: '#solutions' },
                { label: t('nav.forParents'), href: '#solutions' },
            ],
        },
        { label: t('nav.pricing'), href: '#pricing' },
        {
            label: t('nav.resources'),
            href: '#resources',
            children: [
                { label: t('nav.productOverview'), href: '#solutions' },
                { label: t('nav.support'), href: '#contact' },
                { label: t('nav.privacy'), href: '/privacy', external: true },
                { label: t('nav.terms'), href: '/terms', external: true },
                { label: t('nav.health'), href: '/health', external: true },
            ],
        },
        { label: t('nav.contact'), href: '#contact' },
    ];
}

function NavDropdown({ item }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const menuId = useId();

    useEffect(() => {
        function onDoc(e) {
            if (!ref.current?.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold text-navy/80 transition hover:text-navy"
                aria-expanded={open}
                aria-haspopup="true"
                aria-controls={menuId}
                onClick={() => setOpen((v) => !v)}
            >
                {item.label}
                <IconChevron className={`h-3.5 w-3.5 transition ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div
                    id={menuId}
                    role="menu"
                    className="absolute left-0 top-full z-50 mt-2 min-w-[12rem] rounded-xl border border-slate-100 bg-white p-1.5 shadow-card"
                >
                    {item.children.map((child) =>
                        child.external ? (
                            <a
                                key={child.label}
                                href={child.href}
                                role="menuitem"
                                className="block rounded-lg px-3 py-2 text-sm text-brand-ink hover:bg-surface"
                                onClick={() => setOpen(false)}
                            >
                                {child.label}
                            </a>
                        ) : (
                            <a
                                key={child.label}
                                href={child.href}
                                role="menuitem"
                                className="block rounded-lg px-3 py-2 text-sm text-brand-ink hover:bg-surface"
                                onClick={(e) => {
                                    handleSectionNavClick(e);
                                    setOpen(false);
                                }}
                            >
                                {child.label}
                            </a>
                        ),
                    )}
                </div>
            )}
        </div>
    );
}

export default function Navbar({ loginUrl, registerUrl }) {
    const { locale, setLocale, t, locales } = useMarketingLocale();
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [isPhone, setIsPhone] = useState(false);
    const nav = buildNav(t);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        const mq = window.matchMedia('(max-width: 767px)');
        const sync = () => setIsPhone(mq.matches);
        sync();
        mq.addEventListener('change', sync);
        return () => mq.removeEventListener('change', sync);
    }, []);

    return (
        <>
        <header
            className={`sticky top-0 z-40 border-b transition ${
                scrolled
                    ? 'border-slate-200/80 bg-white/95 shadow-nav backdrop-blur'
                    : 'border-transparent bg-white'
            } lp-m-header max-md:z-[60] max-md:border-0 max-md:bg-[#F4F8FF]/95 max-md:shadow-none max-md:backdrop-blur-md`}
        >
            <div className="landing-container flex h-16 items-center justify-between gap-3 lg:h-[4.25rem] max-md:h-[4.35rem] max-md:px-4">
                <div className="hidden md:block">
                    <BrandLogo />
                </div>
                <a href="#top" className="lp-m-brand md:hidden" onClick={handleSectionNavClick} aria-label="Gambia SchoolOS">
                    <span className="lp-m-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" className="h-5 w-5">
                            <path d="M6 10.5 16 5l10 5.5V22L16 27.5 6 22V10.5Z" fill="none" stroke="white" strokeWidth="1.6" />
                            <path d="M16 5v22.5" stroke="#10B9A5" strokeWidth="1.4" />
                            <path d="M6 10.5 16 16l10-5.5" stroke="#BAE6FD" strokeWidth="1.4" fill="none" />
                            <path d="M11 14.2 16 17l5-2.8" stroke="#08A85A" strokeWidth="1.2" fill="none" />
                        </svg>
                    </span>
                    <span className="min-w-0">
                        <span className="lp-m-brand-title">Gambia SchoolOS</span>
                        <span className="lp-m-brand-sub">{t('nav.brandSubtitle')}</span>
                    </span>
                </a>

                <nav className="hidden items-center gap-1 lg:flex" aria-label={t('nav.primary')}>
                    {nav.map((item) =>
                        item.children ? (
                            <NavDropdown key={item.label} item={item} />
                        ) : (
                            <a
                                key={item.label}
                                href={item.href}
                                className="rounded-md px-2.5 py-1 text-sm font-semibold text-navy/80 transition hover:text-navy"
                                onClick={handleSectionNavClick}
                            >
                                {item.label}
                            </a>
                        ),
                    )}
                </nav>

                <div className="flex items-center gap-2 sm:gap-3">
                    <LanguageSwitcher className="hidden md:block" />

                    <Link
                        href={loginUrl}
                        className="btn-outline !hidden !whitespace-nowrap !px-3 !py-2 text-[11px] md:!inline-flex md:text-xs lg:text-sm"
                    >
                        {t('nav.login')}
                    </Link>
                    <Link
                        href={registerUrl}
                        className="btn-teal !hidden !whitespace-nowrap !px-3 !py-2 text-[11px] md:!inline-flex md:text-xs lg:text-sm"
                    >
                        {t('nav.getStarted')}
                    </Link>

                    <button
                        type="button"
                        className="inline-flex rounded-lg border border-slate-200 p-2 text-navy lg:hidden max-md:h-11 max-md:w-11 max-md:items-center max-md:justify-center max-md:rounded-[14px] max-md:border-0 max-md:bg-white max-md:p-0 max-md:shadow-[0_8px_22px_rgba(8,38,91,0.12)]"
                        aria-expanded={mobileOpen}
                        aria-controls="mobile-nav"
                        aria-label={mobileOpen ? t('nav.closeMenu') : t('nav.openMenu')}
                        onClick={() => setMobileOpen((v) => !v)}
                    >
                        {mobileOpen ? (
                            <IconClose className="h-6 w-6 max-md:h-5 max-md:w-5" />
                        ) : (
                            <IconMenu className="h-6 w-6 max-md:h-5 max-md:w-5" />
                        )}
                    </button>
                </div>
            </div>

            {mobileOpen && (
                <div id="tablet-nav" className="hidden border-t border-slate-100 bg-white md:block lg:hidden">
                    <nav className="landing-container space-y-1 py-3" aria-label={t('nav.mobile')}>
                        {nav.map((item) => (
                            <div key={item.label}>
                                <a
                                    href={item.href}
                                    className="block rounded-lg px-3 py-2.5 text-sm font-semibold text-navy"
                                    onClick={(e) => {
                                        handleSectionNavClick(e);
                                        setMobileOpen(false);
                                    }}
                                >
                                    {item.label}
                                </a>
                                {item.children?.map((child) => (
                                    <a
                                        key={child.label}
                                        href={child.href}
                                        className="block rounded-lg px-5 py-2 text-sm text-brand-mute"
                                        onClick={(e) => {
                                            if (!child.external) handleSectionNavClick(e);
                                            setMobileOpen(false);
                                        }}
                                    >
                                        {child.label}
                                    </a>
                                ))}
                            </div>
                        ))}
                        <div className="flex flex-wrap gap-2 border-t border-slate-100 px-3 pt-3">
                            {locales.map((l) => (
                                <button
                                    key={l.code}
                                    type="button"
                                    className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                        locale === l.code ? 'bg-navy text-white' : 'bg-surface text-navy'
                                    }`}
                                    onClick={() => setLocale(l.code)}
                                >
                                    {l.label}
                                </button>
                            ))}
                        </div>
                        <div className="flex gap-2 px-3 pb-3 pt-2">
                            <Link
                                href={loginUrl}
                                className="btn-outline flex-1 !py-2.5 text-sm"
                                onClick={() => setMobileOpen(false)}
                            >
                                {t('nav.login')}
                            </Link>
                            <Link
                                href={registerUrl}
                                className="btn-teal flex-1 !py-2.5 text-sm"
                                onClick={() => setMobileOpen(false)}
                            >
                                {t('nav.getStarted')}
                            </Link>
                        </div>
                    </nav>
                </div>
            )}
        </header>
        {mobileOpen && isPhone ? (
            <MobileNavDrawer
                loginUrl={loginUrl}
                registerUrl={registerUrl}
                onClose={() => setMobileOpen(false)}
            />
        ) : null}
        </>
    );
}
