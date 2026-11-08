import ConsoleHeroDeco, { decoFromUrl, titleIconFromUrl } from '@/Components/SchoolOS/ConsoleHeroDeco';
import NavIcon from '@/Components/SchoolOS/NavIcon';
import { usePage } from '@inertiajs/react';

export default function PageHeader({ title, subtitle, actions, className = '', deco }) {
    const url = usePage().url ?? '';
    const isConsole = url.startsWith('/platform');
    const decoName = deco ?? (isConsole ? decoFromUrl(url) : null);
    const iconName = isConsole ? titleIconFromUrl(url) : null;

    const heading = (
        <div className="flex min-w-0 items-start gap-3">
            {iconName ? (
                <span className="console-title-icon hidden sm:inline-flex">
                    <NavIcon name={iconName} className="h-5 w-5" />
                </span>
            ) : null}
            <div className="min-w-0">
                <h1 className="text-[26px] font-bold tracking-tight text-navy sm:text-[30px]">{title}</h1>
                {subtitle ? (
                    <p className="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500 sm:text-[15px]">{subtitle}</p>
                ) : null}
            </div>
        </div>
    );

    const actionWrap = actions ? (
        <div className="flex flex-wrap items-center gap-2 sm:justify-end">{actions}</div>
    ) : null;

    if (!isConsole) {
        return (
            <div className={`mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between ${className}`}>
                {heading}
                {actionWrap}
            </div>
        );
    }

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
