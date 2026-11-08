/** Small helpers shared by the Phase 8 portal / communication pages. */

/** Locale-aware date + time ("12 Nov 2026, 14:05"). Empty for missing values. */
export function fmtWhen(iso, locale = 'en') {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return String(iso);
    }
    try {
        return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d);
    } catch {
        return d.toLocaleString();
    }
}

/** Locale-aware date only. */
export function fmtDay(iso, locale = 'en') {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return String(iso);
    }
    try {
        return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric' }).format(d);
    } catch {
        return d.toLocaleDateString();
    }
}

/** Short relative time ("5 min ago") through Intl; falls back to the date. */
export function fmtAgo(iso, locale = 'en') {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return String(iso);
    }
    const seconds = Math.round((d.getTime() - Date.now()) / 1000);
    const steps = [
        ['year', 31536000],
        ['month', 2592000],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    try {
        const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
        for (const [unit, size] of steps) {
            if (Math.abs(seconds) >= size) {
                return rtf.format(Math.round(seconds / size), unit);
            }
        }

        return rtf.format(Math.round(seconds / 60), 'minute');
    } catch {
        return fmtDay(iso, locale);
    }
}

export function fmtBytes(n) {
    const v = Number(n) || 0;
    if (v < 1024) {
        return `${v} B`;
    }
    if (v < 1024 * 1024) {
        return `${(v / 1024).toFixed(0)} KB`;
    }

    return `${(v / 1024 / 1024).toFixed(1)} MB`;
}

/** `setup.role.<slug>` label with the raw slug as fallback. */
export function roleLabel(t, role) {
    if (!role) {
        return '';
    }
    const key = `setup.role.${role}`;
    const text = t(key);

    return text === key ? String(role).replace(/_/g, ' ') : text;
}

/** Notification categories the server can send (config schoolos.notifications.categories). */
export const CATEGORIES = ['announcement', 'attendance', 'exam', 'fees', 'message', 'lms', 'account'];
