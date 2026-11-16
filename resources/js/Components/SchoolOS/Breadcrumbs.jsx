import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';

/**
 * @param {{ items: Array<{ label: string, href?: string }> }} props
 */
export default function Breadcrumbs({ items = [], className = '' }) {
    const { t } = useLocale();

    if (!items.length) {
        return null;
    }

    return (
        <nav className={`mb-2 text-sm text-slate-500 ${className}`} aria-label={t('shell.breadcrumb')}>
            <ol className="flex flex-wrap items-center gap-1.5">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li key={`${item.label}-${index}`} className="inline-flex items-center gap-1.5">
                            {index > 0 ? <span className="text-slate-300" aria-hidden="true">›</span> : null}
                            {item.href && !isLast ? (
                                <Link href={item.href} className="transition hover:text-navy">
                                    {item.label}
                                </Link>
                            ) : (
                                <span className={isLast ? 'font-medium text-slate-600' : ''} aria-current={isLast ? 'page' : undefined}>
                                    {item.label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
