import Breadcrumbs from '@/Components/SchoolOS/Breadcrumbs';
import ConsoleHeroDeco, { decoFromUrl, titleIconFromUrl } from '@/Components/SchoolOS/ConsoleHeroDeco';
import NavIcon from '@/Components/SchoolOS/NavIcon';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, usePage } from '@inertiajs/react';

export default function PageHeader({
    title,
    subtitle,
    actions,
    breadcrumbs,
    icon,
    deco,
    className = '',
}) {
    const { t } = useLocale();
    const page = usePage();
    const url = page.url ?? '';
    const isConsole = url.startsWith('/platform');
    const decoName = deco ?? (isConsole ? decoFromUrl(url) : null);
    const iconName = icon ?? (isConsole ? titleIconFromUrl(url) : null);
    const homeHref = page.props.auth?.user ? (isConsole ? '/platform' : '/dashboard') : '/';

    const crumbs =
        breadcrumbs ??
        (title
            ? [
                  { label: t('shell.home'), href: homeHref },
                  { label: title },
              ]
            : []);

    const heading = (
        <div className="flex min-w-0 items-start gap-3">
            {iconName ? (
                <span className="console-title-icon hidden sm:inline-flex">
                    {typeof iconName === 'string' ? <NavIcon name={iconName} className="h-5 w-5" /> : iconName}
                </span>
            ) : null}
            <div className="min-w-0 flex-1">
                <Breadcrumbs items={crumbs} />
                <h1 className="text-[1.375rem] font-bold tracking-tight text-navy sm:text-[32px]">{title}</h1>
                {subtitle ? (
                    <p className="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500 sm:text-[15px]">{subtitle}</p>
                ) : null}
            </div>
        </div>
    );

    const actionWrap = actions ? (
        <div className="page-hero-actions flex flex-wrap items-center gap-2 sm:justify-end">{actions}</div>
    ) : null;

    if (isConsole) {
        return (
            <div className={`console-hero ${className}`}>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    {heading}
                    <div className="flex items-start gap-3">
                        {actionWrap}
                        <div className="hidden lg:block">{decoName ? <ConsoleHeroDeco name={decoName} /> : null}</div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className={`page-hero ${className}`}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                {heading}
                {actionWrap}
            </div>
        </div>
    );
}

export function PageHeaderLink({ href, children, className = 'btn-primary' }) {
    return (
        <Link href={href} className={className}>
            {children}
        </Link>
    );
}
