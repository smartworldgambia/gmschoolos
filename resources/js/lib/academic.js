/**
 * Helpers for the Phase 5 academic screens.
 *
 * Server-side business refusals and confirmations are sent as stable keys (`err.*` / `ok.*`) and are
 * translated here. Messages raised by the older setup services are plain English, so the known ones
 * are mapped onto keys as well; anything else (framework validation text) is shown as received.
 */

const LEGACY_MESSAGES = {
    'This level already exists.': 'err.wizard.levelExists',
    'Remove the classes in this level first.': 'err.wizard.levelHasClasses',
    'This department already exists.': 'err.wizard.departmentExists',
    'Choose a level from this school.': 'err.wizard.levelInvalid',
    'This class already exists in the level.': 'err.wizard.classExists',
    'This class already has students or timetable entries and cannot be removed.': 'err.wizard.classInUse',
    'This subject already exists.': 'err.wizard.subjectExists',
    'This subject code is already used.': 'err.wizard.subjectCodeUsed',
    'This subject is used in the timetable and cannot be removed.': 'err.wizard.subjectInTimetable',
    'This subject is in use (timetable, class allocation or results) and cannot be removed.': 'err.wizard.subjectInUse',
    'An academic year with this name already exists.': 'err.year.nameTaken',
};

/** Translate a flash value or an error-bag message. ars fills {n} / {failed} style placeholders. */
export function messageText(t, value, vars) {
    if (typeof value !== 'string' || value === '') {
        return '';
    }
    if (/^(ok|err)\./.test(value)) {
        return t(value, vars);
    }
    const mapped = LEGACY_MESSAGES[value];

    return mapped ? t(mapped) : value;
}

/** Translate `${prefix}.${value}` and fall back to the raw value when no label exists. */
export function enumLabel(t, prefix, value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const key = `${prefix}.${value}`;
    const text = t(key);

    return text === key ? String(value) : text;
}

/** Error bag -> list of readable messages. */
export function errorList(t, errors = {}) {
    return Object.values(errors)
        .filter(Boolean)
        .map((m) => messageText(t, Array.isArray(m) ? m[0] : m));
}

export function todayIso() {
    return new Date().toISOString().slice(0, 10);
}

export const GENDERS = ['male', 'female', 'other'];
export const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'excused', 'left_early'];
export const DAYS = [1, 2, 3, 4, 5, 6, 7];
