export default function FilterBar({ children, actions = null, className = '' }) {
    return (
        <div className={`filter-bar ${className}`}>
            <div className="flex min-w-0 flex-1 flex-wrap items-end gap-3">{children}</div>
            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
