import { useLocale } from '@/i18n/LocaleProvider';
import { errorList } from '@/lib/academic';
import { Link } from '@inertiajs/react';

export const controlClass =
    'ui-input';

export function Card({ title, actions, children, className = '' }) {
    return (
        <section className={`console-card ${className}`}>
            {title || actions ? (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    {title ? <h2 className="text-base font-semibold text-navy">{title}</h2> : <span />}
                    {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
                </div>
            ) : null}
            {children}
        </section>
    );
}

export function Field({ label, error, hint, children, className = '' }) {
    return (
        <div className={className}>
            {label ? <label className="mb-1 block text-sm font-medium text-slate-700">{label}</label> : null}
            {children}
            {hint ? <p className="mt-1 text-xs text-slate-500">{hint}</p> : null}
            {error ? <p className="mt-1 text-xs text-red-600">{error}</p> : null}
        </div>
    );
}

export function Input({ className = '', ...props }) {
    return <input {...props} className={`${controlClass} ${className}`} />;
}

export function Select({ className = '', children, ...props }) {
    return (
        <select {...props} className={`${controlClass} ${className}`}>
            {children}
        </select>
    );
}

export function Textarea({ className = '', rows = 3, ...props }) {
    return <textarea rows={rows} {...props} className={`${controlClass} ${className}`} />;
}

/** All validation errors of an Inertia form as one readable block (translated where a key exists). */
export function ErrorSummary({ errors }) {
    const { t } = useLocale();
    const list = errorList(t, errors);
    if (list.length === 0) {
        return null;
    }

    return (
        <ul className="mb-4 list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 px-8 py-3 text-sm text-red-800" role="alert">
            {list.map((m, i) => (
                <li key={i}>{m}</li>
            ))}
        </ul>
    );
}

/** Laravel paginator links (labels are English in the payload, so previous/next are replaced by icons-in-words). */
export function Pager({ links = [] }) {
    const { t } = useLocale();
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <nav className="mt-4 flex flex-wrap items-center gap-1" aria-label={t('acad.common.pagination')}>
            {links.map((link, i) => {
                const first = i === 0;
                const last = i === links.length - 1;
                const label = first ? t('acad.common.previous') : last ? t('acad.common.next') : link.label;
                const cls = `rounded-md px-3 py-1.5 text-sm whitespace-nowrap ${
                    link.active ? 'bg-navy text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50'
                } ${link.url ? '' : 'pointer-events-none opacity-40'}`;

                return link.url ? (
                    <Link key={i} href={link.url} className={cls} preserveScroll>
                        {label}
                    </Link>
                ) : (
                    <span key={i} className={cls}>
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}

export function Stat({ label, value, tone = 'navy' }) {
    return (
        <div className="console-card">
            <p className="text-xs text-slate-500">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${tone === 'red' ? 'text-red-600' : tone === 'green' ? 'text-emerald-600' : 'text-navy'}`}>{value}</p>
        </div>
    );
}
