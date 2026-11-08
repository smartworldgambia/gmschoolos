import { handleSectionNavClick } from '@/Components/Marketing/scrollToSection';
import { useMarketingLocale } from '@/i18n/MarketingLocale';
import { useEffect, useState } from 'react';

function IconHome({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z" />
        </svg>
    );
}

function IconCap({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M3 10 12 5l9 5-9 5-9-5z" />
            <path d="M7 12.5v4.2c2.2 1.5 7.8 1.5 10 0V12.5" />
        </svg>
    );
}

function IconPeople({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 19c.5-3 2.7-4.7 5.5-4.7s5 1.7 5.5 4.7" />
            <circle cx="17" cy="9" r="2.3" />
            <path d="M20.7 18.5c-.3-2.2-1.8-3.5-3.7-3.5" />
        </svg>
    );
}

function IconModules({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.4" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.4" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.4" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.4" />
        </svg>
    );
}

function IconBars({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M4 19V6M4 19h16M8 16v-5M12 16V8M16 16v-3" />
        </svg>
    );
}

const ITEMS = [
    { key: 'home', href: '#top', icon: IconHome },
    { key: 'academics', href: '#features', icon: IconCap },
    { key: 'people', href: '#solutions', icon: IconPeople },
    { key: 'modules', href: '#pricing', icon: IconModules },
    { key: 'reports', href: '#resources', icon: IconBars },
];

export default function MobileBottomNav() {
    const { t } = useMarketingLocale();
    const [active, setActive] = useState('#top');

    useEffect(() => {
        const sync = () => setActive(window.location.hash || '#top');
        sync();
        window.addEventListener('hashchange', sync);
        return () => window.removeEventListener('hashchange', sync);
    }, []);

    return (
        <nav className="lp-m-dock md:hidden" aria-label={t('mobile.nav.bar')}>
            <ul className="lp-m-dock-list">
                {ITEMS.map((item) => {
                    const Icon = item.icon;
                    const on = active === item.href || (item.href === '#top' && (active === '' || active === '#'));
                    return (
                        <li key={item.key}>
                            <a
                                href={item.href}
                                className={`lp-m-dock-link ${on ? 'is-active' : ''}`}
                                aria-current={on ? 'page' : undefined}
                                onClick={(e) => {
                                    handleSectionNavClick(e);
                                    setActive(item.href);
                                }}
                            >
                                <span className="lp-m-dock-icon">
                                    <Icon />
                                </span>
                                <span className="lp-m-dock-label">{t(`mobile.nav.${item.key}`)}</span>
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
