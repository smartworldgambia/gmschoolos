const statusStyles = {
    paid: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    present: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    published: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    in_review: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    superseded: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',

    confirmed: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    verified: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    current: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',

    trial: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    scheduled: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    not_required_during_trial: 'bg-blue-50 text-blue-700 ring-blue-600/20',

    expired: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    unpaid: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    issued: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    pending_verification: 'bg-amber-50 text-amber-700 ring-amber-600/20',

    pending: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    draft: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    due: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    warning: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    amber: 'bg-amber-50 text-amber-700 ring-amber-600/20',

    overdue: 'bg-red-50 text-red-700 ring-red-600/20',
    failed: 'bg-red-50 text-red-700 ring-red-600/20',
    cancelled: 'bg-red-50 text-red-700 ring-red-600/20',
    rejected: 'bg-red-50 text-red-700 ring-red-600/20',
    refunded: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    absent: 'bg-red-50 text-red-700 ring-red-600/20',
    inactive: 'bg-red-50 text-red-700 ring-red-600/20',
    suspended: 'bg-red-50 text-red-700 ring-red-600/20',
    critical: 'bg-red-50 text-red-700 ring-red-600/20',
    danger: 'bg-red-50 text-red-700 ring-red-600/20',
    red: 'bg-red-50 text-red-700 ring-red-600/20',

    partial: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    info: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    blue: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    in_progress: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    passed: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    submitted: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    graded: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    returned: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    missing: 'bg-slate-100 text-slate-700 ring-slate-500/20',
    upcoming: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    ok: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    healthy: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    degraded: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    not_run: 'bg-slate-100 text-slate-700 ring-slate-500/20',
};

const defaultStyle = 'bg-slate-100 text-slate-700 ring-slate-500/20';

function normalizeKey(status) {
    return String(status ?? '')
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, '_');
}

export default function StatusBadge({ status, label, className = '' }) {
    const key = normalizeKey(status);
    const style = statusStyles[key] ?? defaultStyle;
    const text = label ?? status ?? 'Unknown';

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ring-1 ring-inset ${style} ${className}`}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
            {text}
        </span>
    );
}
