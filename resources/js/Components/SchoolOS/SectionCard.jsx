export default function SectionCard({ title, subtitle, actions, children, className = '' }) {
    return (
        <section className={`section-card ${className}`}>
            {title || actions || subtitle ? (
                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        {title ? <h2 className="text-base font-semibold text-navy sm:text-lg">{title}</h2> : null}
                        {subtitle ? <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p> : null}
                    </div>
                    {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
                </div>
            ) : null}
            {children}
        </section>
    );
}
