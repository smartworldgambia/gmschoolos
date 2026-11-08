import { handleSectionNavClick } from '@/Components/Marketing/scrollToSection';
import { useMarketingLocale } from '@/i18n/MarketingLocale';
import { Link } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';

const stroke = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: '1.8',
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

function Ico({ children, className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
            {children}
        </svg>
    );
}

const icons = {
    home: (
        <Ico className="h-[1.15rem] w-[1.15rem]">
            <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1V10.5z" />
        </Ico>
    ),
    features: (
        <Ico>
            <path d="M12 3 3.5 7.5 12 12l8.5-4.5L12 3z" />
            <path d="M3.5 12.2 12 16.7l8.5-4.5" />
            <path d="M3.5 16.6 12 21.1l8.5-4.5" />
        </Ico>
    ),
    cap: (
        <Ico>
            <path d="M3 10 12 5l9 5-9 5-9-5z" />
            <path d="M7 12.5v4.2c2.2 1.5 7.8 1.5 10 0V12.5" />
        </Ico>
    ),
    book: (
        <Ico>
            <path d="M12 6.5c-1.6-1.4-3.8-2-6.2-2H4v14.2h2.2c2.3 0 4.3.6 5.8 2" />
            <path d="M12 6.5c1.6-1.4 3.8-2 6.2-2H20v14.2h-2.2c-2.3 0-4.3.6-5.8 2" />
            <path d="M12 6.5v14.2" />
        </Ico>
    ),
    notebook: (
        <Ico>
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
            <path d="M6.5 3H20v18H6.5A2.5 2.5 0 0 1 4 18.5v-13A2.5 2.5 0 0 1 6.5 3z" />
            <path d="M8 8h8M8 12h5" />
        </Ico>
    ),
    doc: (
        <Ico>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
            <path d="M14 3v5h5M9 13h6M9 17h4" />
        </Ico>
    ),
    coins: (
        <Ico>
            <ellipse cx="12" cy="6.5" rx="7" ry="3" />
            <path d="M5 6.5v4c0 1.7 3.1 3 7 3s7-1.3 7-3v-4" />
            <path d="M5 10.5v4c0 1.7 3.1 3 7 3s7-1.3 7-3v-4" />
        </Ico>
    ),
    grid: (
        <Ico>
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.4" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.4" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.4" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.4" />
        </Ico>
    ),
    users: (
        <Ico>
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 19c.5-3 2.7-4.7 5.5-4.7s5 1.7 5.5 4.7" />
            <circle cx="17" cy="9" r="2.3" />
            <path d="M20.7 18.5c-.3-2.2-1.8-3.5-3.7-3.5" />
        </Ico>
    ),
    monitor: (
        <Ico>
            <rect x="3" y="4" width="18" height="12" rx="2" />
            <path d="M8 20h8M12 16v4" />
        </Ico>
    ),
    teach: (
        <Ico>
            <path d="M3.5 5.5h11v9h-11z" />
            <path d="M9 14.5V19M6.5 19h5" />
            <circle cx="18.2" cy="9.2" r="2.1" />
            <path d="M15.2 19c.3-2.4 1.6-3.7 3-3.7s2.7 1.3 3 3.7" />
        </Ico>
    ),
    tag: (
        <Ico>
            <path d="M20.6 13.1 12.7 21a2 2 0 0 1-2.8 0L3 14.1V3h11.1l6.5 6.5a2 2 0 0 1 0 2.8z" />
            <circle cx="8.2" cy="8.2" r="1.3" />
        </Ico>
    ),
    headset: (
        <Ico>
            <path d="M4 13v-1a8 8 0 0 1 16 0v1" />
            <rect x="2.8" y="13" width="4.2" height="6.2" rx="1.6" />
            <rect x="17" y="13" width="4.2" height="6.2" rx="1.6" />
            <path d="M21.2 16.5H22a2 2 0 0 1 0 4h-3" />
        </Ico>
    ),
    shield: (
        <Ico>
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="m9 12 2 2 4-4" />
        </Ico>
    ),
    userPlus: (
        <Ico className="h-6 w-6">
            <circle cx="9" cy="8" r="3.2" />
            <path d="M3.5 19c.4-3 2.6-4.8 5.5-4.8S14.1 16 14.5 19" />
            <path d="M17 9v6M14 12h6" />
        </Ico>
    ),
    chevronRight: (
        <Ico className="h-4 w-4">
            <path d="m9 6 6 6-6 6" />
        </Ico>
    ),
    chevronUp: (
        <Ico className="h-4 w-4">
            <path d="m6 15 6-6 6 6" />
        </Ico>
    ),
    chevronDown: (
        <Ico className="h-4 w-4">
            <path d="m6 9 6 6 6-6" />
        </Ico>
    ),
    close: (
        <Ico className="h-5 w-5">
            <path d="M6 6l12 12M18 6 6 18" />
        </Ico>
    ),
};

function BrandMark() {
    return (
        <span className="lp-m-drawer-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32" className="h-7 w-7">
                <path d="M6 10.5 16 5l10 5.5V22L16 27.5 6 22V10.5Z" fill="none" stroke="white" strokeWidth="1.6" />
                <path d="M16 5v22.5" stroke="#10B9A5" strokeWidth="1.4" />
                <path d="M6 10.5 16 16l10-5.5" stroke="#BAE6FD" strokeWidth="1.4" fill="none" />
                <path d="M11 14.2 16 17l5-2.8" stroke="#08A85A" strokeWidth="1.2" fill="none" />
            </svg>
        </span>
    );
}

function Accordion({ id, icon, label, open, onToggle, children }) {
    const panelId = `${id}-panel`;

    return (
        <div className="lp-m-acc">
            <button
                type="button"
                className="lp-m-acc-head"
                aria-expanded={open}
                aria-controls={panelId}
                id={`${id}-btn`}
                onClick={onToggle}
            >
                <span className="lp-m-acc-ico">{icon}</span>
                <span className="lp-m-acc-label">{label}</span>
                <span className="lp-m-acc-chev" aria-hidden="true">
                    {open ? icons.chevronUp : icons.chevronDown}
                </span>
            </button>
            <div
                id={panelId}
                role="region"
                aria-labelledby={`${id}-btn`}
                className={`lp-m-acc-body ${open ? 'is-open' : ''}`}
                aria-hidden={!open}
                inert={!open ? true : undefined}
            >
                <div className="lp-m-acc-inner">{children}</div>
            </div>
        </div>
    );
}

function SubLink({ href, icon, label, external, onNavigate }) {
    return (
        <a
            href={href}
            className="lp-m-sub"
            onClick={(e) => {
                if (!external) handleSectionNavClick(e);
                onNavigate();
            }}
        >
            <span className="lp-m-sub-ico">{icon}</span>
            <span className="lp-m-sub-label">{label}</span>
        </a>
    );
}

export default function MobileNavDrawer({ loginUrl, registerUrl, onClose }) {
    const { locale, setLocale, t, locales } = useMarketingLocale();
    const titleId = useId();
    const closeRef = useRef(null);
    const [open, setOpen] = useState({ features: true, solutions: true, resources: true });

    useEffect(() => {
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        closeRef.current?.focus();

        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', onKey);

        return () => {
            document.body.style.overflow = prev;
            document.removeEventListener('keydown', onKey);
        };
    }, [onClose]);

    const go = () => onClose();
    const toggle = (key) => setOpen((s) => ({ ...s, [key]: !s[key] }));

    return (
        <div
            className="lp-m-drawer md:hidden"
            id="mobile-nav"
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
        >
            <div className="lp-m-drawer-head">
                <a href="#top" className="lp-m-drawer-brand" onClick={(e) => { handleSectionNavClick(e); go(); }}>
                    <BrandMark />
                    <span className="min-w-0">
                        <span id={titleId} className="lp-m-drawer-title">
                            Gambia SchoolOS
                        </span>
                        <span className="lp-m-drawer-sub">{t('nav.brandSubtitle')}</span>
                    </span>
                </a>
                <button
                    ref={closeRef}
                    type="button"
                    className="lp-m-drawer-close"
                    aria-label={t('nav.closeMenu')}
                    onClick={onClose}
                >
                    {icons.close}
                </button>
                <svg className="lp-m-drawer-ribbon" viewBox="0 0 180 56" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M8 46 C 55 14, 110 22, 180 8" fill="none" stroke="#CE1126" strokeWidth="8" strokeLinecap="round" />
                    <path d="M8 38 C 55 6, 110 14, 180 0" fill="none" stroke="#0C47A1" strokeWidth="6.5" strokeLinecap="round" />
                    <path d="M8 32 C 55 0, 110 8, 180 -6" fill="none" stroke="#F9F9F9" strokeWidth="3.5" strokeLinecap="round" />
                    <path d="M8 26 C 55 -6, 110 2, 180 -12" fill="none" stroke="#3A7A28" strokeWidth="6.5" strokeLinecap="round" />
                </svg>
            </div>

            <nav className="lp-m-drawer-panel" aria-label={t('nav.mobile')}>
                <a
                    href="#top"
                    className="lp-m-home"
                    onClick={(e) => {
                        handleSectionNavClick(e);
                        go();
                    }}
                >
                    <span className="lp-m-home-ico">{icons.home}</span>
                    <span className="lp-m-home-label">{t('nav.home')}</span>
                    <span className="lp-m-home-chev" aria-hidden="true">
                        {icons.chevronRight}
                    </span>
                </a>

                <Accordion
                    id="lp-features"
                    icon={icons.features}
                    label={t('nav.features')}
                    open={open.features}
                    onToggle={() => toggle('features')}
                >
                    <SubLink href="#features" icon={icons.cap} label={t('nav.drawer.schoolManagement')} onNavigate={go} />
                    <SubLink href="#features" icon={icons.notebook} label={t('nav.drawer.learningLms')} onNavigate={go} />
                    <SubLink href="#features" icon={icons.coins} label={t('nav.drawer.feesExams')} onNavigate={go} />
                    <SubLink href="#features" icon={icons.grid} label={t('nav.drawer.moreModules')} onNavigate={go} />
                </Accordion>

                <Accordion
                    id="lp-solutions"
                    icon={icons.users}
                    label={t('nav.solutions')}
                    open={open.solutions}
                    onToggle={() => toggle('solutions')}
                >
                    <SubLink href="#solutions" icon={icons.monitor} label={t('nav.drawer.forAdmins')} onNavigate={go} />
                    <SubLink href="#solutions" icon={icons.teach} label={t('nav.drawer.forTeachers')} onNavigate={go} />
                    <SubLink href="#solutions" icon={icons.users} label={t('nav.drawer.forParents')} onNavigate={go} />
                </Accordion>

                <a
                    href="#pricing"
                    className="lp-m-price"
                    onClick={(e) => {
                        handleSectionNavClick(e);
                        go();
                    }}
                >
                    <span className="lp-m-sub-ico">{icons.tag}</span>
                    <span className="lp-m-price-label">{t('nav.pricing')}</span>
                    <span className="lp-m-home-chev" aria-hidden="true">
                        {icons.chevronRight}
                    </span>
                </a>

                <Accordion
                    id="lp-resources"
                    icon={icons.book}
                    label={t('nav.resources')}
                    open={open.resources}
                    onToggle={() => toggle('resources')}
                >
                    <SubLink href="#solutions" icon={icons.doc} label={t('nav.drawer.productOverview')} onNavigate={go} />
                    <SubLink href="#contact" icon={icons.headset} label={t('nav.support')} onNavigate={go} />
                    <SubLink href="/privacy" icon={icons.shield} label={t('nav.privacy')} external onNavigate={go} />
                </Accordion>

                <Link href={registerUrl} className="lp-m-cta" onClick={go}>
                    <span className="lp-m-cta-ico">{icons.userPlus}</span>
                    <span className="min-w-0">
                        <span className="lp-m-cta-title">{t('nav.drawer.getStarted')}</span>
                        <span className="lp-m-cta-hint">{t('nav.drawer.getStartedHint')}</span>
                    </span>
                    <span className="lp-m-cta-arrow" aria-hidden="true">
                        {icons.chevronRight}
                    </span>
                </Link>

                <div className="lp-m-drawer-foot">
                    <Link href={loginUrl} className="lp-m-login" onClick={go}>
                        {t('nav.login')}
                    </Link>
                    <div className="lp-m-langs" role="group" aria-label={t('nav.primary')}>
                        {locales.map((l) => (
                            <button
                                key={l.code}
                                type="button"
                                className={`lp-m-lang ${locale === l.code ? 'is-on' : ''}`}
                                onClick={() => setLocale(l.code)}
                            >
                                {l.label}
                            </button>
                        ))}
                    </div>
                </div>
            </nav>
        </div>
    );
}
