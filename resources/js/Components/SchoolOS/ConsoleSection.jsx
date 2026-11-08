import NavIcon from '@/Components/SchoolOS/NavIcon';

export default function ConsoleSection({
    title,
    icon,
    help,
    actions,
    children,
    className = '',
}) {
    return (
        <section className={className}>
            {title || actions ? (
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    {title ? (
                        <h2 className="console-section-title mb-0 inline-flex items-center gap-2">
                            {icon ? <NavIcon name={icon} className="h-4 w-4 text-teal" /> : null}
                            {title}
                        </h2>
                    ) : (
                        <span />
                    )}
                    {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
                </div>
            ) : null}
            {help ? <p className="mb-3 text-sm leading-relaxed text-slate-500">{help}</p> : null}
            {children}
        </section>
    );
}
