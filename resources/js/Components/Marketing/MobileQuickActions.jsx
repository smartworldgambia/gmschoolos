import { handleSectionNavClick } from '@/Components/Marketing/scrollToSection';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

function IconPersonAdd({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <circle cx="9" cy="8" r="3.2" />
            <path d="M3.5 19c.4-3 2.6-4.8 5.5-4.8S14.1 16 14.5 19" />
            <path d="M17 9v6M14 12h6" />
        </svg>
    );
}

function IconBook({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
            <path d="M6.5 3H20v18H6.5A2.5 2.5 0 0 1 4 18.5v-13A2.5 2.5 0 0 1 6.5 3z" />
            <path d="M8 8h8M8 12h5" />
        </svg>
    );
}

function IconCalendar({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 10h18" />
        </svg>
    );
}

function IconMegaphone({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="m3 11 13-6v14L3 13z" />
            <path d="M11.2 16.4A4.2 4.2 0 0 1 8 20" />
        </svg>
    );
}

const ACTIONS = [
    { key: 'addStudent', href: '#features', icon: IconPersonAdd, tone: 'text-sky-500' },
    { key: 'grades', href: '#features', icon: IconBook, tone: 'text-violet-500' },
    { key: 'timetable', href: '#features', icon: IconCalendar, tone: 'text-amber-500' },
    { key: 'broadcast', href: '#contact', icon: IconMegaphone, tone: 'text-fuchsia-500' },
];

export default function MobileQuickActions() {
    const { t } = useMarketingLocale();

    return (
        <section className="lp-m-actions md:hidden" aria-label={t('features.label')}>
            {ACTIONS.map((action) => {
                const Icon = action.icon;
                return (
                    <a
                        key={action.key}
                        href={action.href}
                        className="lp-m-action"
                        onClick={handleSectionNavClick}
                    >
                        <span className={`lp-m-action-icon ${action.tone}`}>
                            <Icon />
                        </span>
                        <span className="lp-m-action-label">{t(`mobile.action.${action.key}`)}</span>
                    </a>
                );
            })}
        </section>
    );
}
