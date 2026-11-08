import NavIcon from '@/Components/SchoolOS/NavIcon';
import { navLabel } from '@/Components/SchoolOS/navLabel';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';

export default function BottomNav({ items = [], className = '' }) {
    const { t } = useLocale();

    if (!items.length) {
        return null;
    }

    return (
        <nav aria-label={t('shell.mobileNav')} className={`shell-bottom-nav ${className}`}>
            <ul className="shell-bottom-list">
                {items.slice(0, 5).map((item) => {
                    const isCurrent = Boolean(item.current);
                    const label = navLabel(item, t);

                    return (
                        <li key={item.href ?? item.key ?? item.name} className="min-w-0 flex-1">
                            <Link
                                href={item.href ?? '#'}
                                className={`shell-bottom-link ${isCurrent ? 'shell-bottom-link-on' : 'shell-bottom-link-off'}`}
                                aria-current={isCurrent ? 'page' : undefined}
                            >
                                <span
                                    className={`shell-bottom-icon ${isCurrent ? 'shell-bottom-icon-on' : ''}`}
                                    aria-hidden="true"
                                >
                                    {item.icon ?? <NavIcon name={item.key} className="h-4 w-4" />}
                                </span>
                                <span className="max-w-full truncate">{label}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
