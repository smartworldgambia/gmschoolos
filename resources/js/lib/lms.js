/** Small helpers shared by the Phase 7 LMS screens. */

/** Server date-times arrive as "YYYY-MM-DD HH:MM:SS" in the school's own time zone; show them as they are, minus seconds. */
export function fmt(dt) {
    if (!dt) {
        return '—';
    }

    return String(dt).replace('T', ' ').slice(0, 16);
}

export function fmtBytes(n) {
    const v = Number(n || 0);
    if (v < 1024) {
        return `${v} B`;
    }
    if (v < 1024 * 1024) {
        return `${(v / 1024).toFixed(1)} KB`;
    }

    return `${(v / 1024 / 1024).toFixed(1)} MB`;
}

/** Render a number without trailing zeros (7, 7.5). */
export function num(v) {
    if (v === null || v === undefined || v === '') {
        return '—';
    }
    const n = Number(v);

    return Number.isInteger(n) ? String(n) : String(Math.round(n * 100) / 100);
}

export const COURSE_STATUSES = ['draft', 'published', 'archived'];
export const QUESTION_TYPES = ['multiple_choice', 'true_false', 'short_answer', 'essay'];
export const CONTENT_TYPES = ['text', 'video', 'document', 'link'];
export const MATERIAL_TYPES = ['file', 'document', 'video', 'link'];

/** Append the first class name when the title does not already include it. */
export function courseDisplayTitle(course, classes) {
    const title = String(course?.title || '').trim();
    const list = classes ?? course?.classes ?? [];
    const klass = (Array.isArray(list) ? list : []).filter(Boolean)[0];
    if (!klass) {
        return title;
    }
    if (title.toLowerCase().includes(String(klass).toLowerCase())) {
        return title;
    }
    if (/\bgrade\b|\bclass\b|classe|صف/i.test(title)) {
        return title;
    }

    return `${title} – ${klass}`;
}

export function parseSchoolDate(dt) {
    if (!dt) {
        return null;
    }
    const d = new Date(String(dt).replace(' ', 'T'));

    return Number.isNaN(d.getTime()) ? null : d;
}

export function dateKey(dt) {
    const d = parseSchoolDate(dt);
    if (!d) {
        return null;
    }
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/** Fri, 14 Nov 2026, 11:59 PM — school wall-clock, no invented timezone shift. */
export function fmtFriendly(dt, locale = 'en') {
    const d = parseSchoolDate(dt);
    if (!d) {
        return fmt(dt);
    }
    const loc = locale === 'ar' ? 'ar' : locale === 'fr' ? 'fr' : 'en-GB';

    return new Intl.DateTimeFormat(loc, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(d);
}
