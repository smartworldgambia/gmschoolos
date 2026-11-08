import { usePage } from '@inertiajs/react';

const DEDICATED = ['nursery', 'primary', 'basic_secondary', 'senior_secondary'];

/** Active learner band from the server (School.category + Level), with a safe UI fallback. */
export function useLmsTone() {
    const { lmsContext, school, auth, user } = usePage().props;
    const schoolType = lmsContext?.school_type || school?.category || 'other';
    const band = lmsContext?.band || (DEDICATED.includes(schoolType) ? schoolType : 'combined');
    const person = user ?? auth?.user ?? {};

    return {
        band,
        schoolType,
        role: lmsContext?.role || 'none',
        className: lmsContext?.class_name || null,
        levelName: lmsContext?.level_name || null,
        nursery: band === 'nursery',
        primary: band === 'primary',
        simple: band === 'nursery' || band === 'primary',
        senior: band === 'senior_secondary',
        secondary: band === 'basic_secondary' || band === 'senior_secondary',
        primaryColor: school?.primary_color || '#082B50',
        accentColor: school?.accent_color || '#08B7A5',
        logoUrl: school?.logo_url || null,
        schoolName: school?.name || '',
        userName: person?.name || '',
        shellClass: `lms-shell lms-tone-${band}`,
    };
}

export function tabCopy(t, key, band, author) {
    if (key === 'gradebook' && !author) {
        if (band === 'nursery') return t('lms.tone.nursery.grades');
        if (band === 'primary') return t('lms.tone.primary.grades');
        return t('lms.tab.myGrades');
    }
    if (key === 'forum' && (band === 'nursery' || band === 'primary')) return t('lms.tone.nursery.talk');
    if (key === 'announcements' && band === 'nursery') return t('lms.tone.nursery.news');
    if (key === 'overview' && band === 'nursery') return t('lms.tone.nursery.overview');
    if (key === 'people' && band === 'nursery') return t('lms.tone.nursery.people');
    if (key === 'progress' && band === 'nursery') return t('lms.tone.nursery.progress');

    const keys = {
        overview: 'lms.tab.overview',
        content: 'lms.tab.content',
        assignments: 'lms.tab.assignments',
        quizzes: 'lms.tab.quizzes',
        people: 'lms.tab.people',
        gradebook: 'lms.tab.gradebook',
        progress: 'lms.tab.progress',
        announcements: 'lms.tab.announcements',
        forum: 'lms.tab.forum',
    };

    return t(keys[key] || key);
}

export function dashTitle(t, { band, role, author }) {
    if (role === 'guardian') return t('lms.dash.titleGuardian');
    if (author) return t('lms.dash.teachingTitle');
    if (band === 'nursery') return t('lms.tone.nursery.dashTitle');
    if (band === 'primary') return t('lms.tone.primary.dashTitle');
    if (band === 'senior_secondary') return t('lms.tone.senior.dashTitle');

    return t('lms.index.titleLearner');
}

export function dashSubtitle(t, { band, author, className, userName }) {
    if (author) return t('lms.dash.welcomeBack', { name: userName || '' });
    if (band === 'nursery') return t('lms.tone.nursery.dashSubtitle', { name: userName || '' });
    if (band === 'primary') return t('lms.tone.primary.dashSubtitle');
    if (className) return t('lms.dash.subtitleClass', { class: className });

    return t('lms.index.subtitleLearner');
}

/** Two-letter cover initials from a course code, then the title. */
export function courseInitials(course) {
    const code = String(course?.code || '').replace(/[^A-Za-z0-9]/g, '');
    if (code.length >= 2) {
        return code.slice(0, 2).toUpperCase();
    }

    const words = String(course?.title || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    if (words.length >= 2) {
        return `${words[0][0]}${words[1][0]}`.toUpperCase();
    }

    return String(course?.title || 'C').slice(0, 2).toUpperCase();
}

/** Distinct card cover using school brand colours first, then a short stable palette. */
export function courseCover(index, primary, accent) {
    const a = primary || '#082B50';
    const b = accent || '#08B7A5';
    const palette = [
        [a, b],
        [b, a],
        ['#082B50', b],
        ['#0f766e', a],
        ['#9a3412', b],
        ['#5b21b6', a],
    ];
    const pair = palette[Math.abs(Number(index) || 0) % palette.length];

    return `linear-gradient(135deg, ${pair[0]} 0%, ${pair[1]} 100%)`;
}

/** Subject family for placeholder covers — keywords only, never invents photos. */
export function subjectKind(course) {
    const hay = `${course?.subject || ''} ${course?.title || ''} ${course?.category || ''} ${course?.code || ''}`.toLowerCase();
    if (/math|numeracy|algebra|arithmetic|geometry|calculus|رياضيات|mathématique/.test(hay)) {
        return 'math';
    }
    if (/biolog|nature|life science|أحياء|biologie/.test(hay)) {
        return 'biology';
    }
    if (/english|literature|language arts|anglais|إنجليز/.test(hay)) {
        return 'english';
    }
    if (/social|geograph|histor|civic|society|جغراف|تاريخ|اجتماع/.test(hay)) {
        return 'social';
    }
    if (/science|chemist|physic|laborat|علم|كيمياء|فيزياء|sciences/.test(hay)) {
        return 'science';
    }

    return 'default';
}

export function subjectCoverStyle(kind, index, primary, accent) {
    const map = {
        math: 'linear-gradient(145deg, #092B55 0%, #2878D0 100%)',
        biology: 'linear-gradient(145deg, #0B5C3A 0%, #009B63 100%)',
        english: 'linear-gradient(145deg, #7C2D12 0%, #D97706 100%)',
        social: 'linear-gradient(145deg, #1E3A5F 0%, #0E7490 100%)',
        science: 'linear-gradient(145deg, #134E4A 0%, #00BFA6 100%)',
    };

    return map[kind] || courseCover(index, primary, accent);
}
