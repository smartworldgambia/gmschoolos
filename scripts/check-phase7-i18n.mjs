// Phase 7 i18n gate: every key the LMS pages / PHP use must exist in en, fr and ar, with no empty value,
// no placeholder drift, no override of another catalog, and no enum label missing for a value the server can send.
import fs from 'fs';
import { lmsCatalog } from '../resources/js/i18n/lmsCatalog.js';
import { recordsCatalog } from '../resources/js/i18n/recordsCatalog.js';
import { academicCatalog } from '../resources/js/i18n/academicCatalog.js';
import * as app from '../resources/js/i18n/appCatalog.js';

const catalogs = app.appCatalog ?? app.default ?? null;
if (!catalogs) {
    console.error('appCatalog export not found:', Object.keys(app));
    process.exit(2);
}

const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(`${dir}/${e.name}`) : [`${dir}/${e.name}`]));
const jsx = [...walk('resources/js/Pages/Lms'), ...walk('resources/js/Components/Lms')].filter((f) => f.endsWith('.jsx'));
const php = [...walk('app/Services'), ...walk('app/Http/Controllers'), ...walk('app/Support')].filter((f) => f.endsWith('.php'));

const used = new Set();
for (const f of jsx) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(/\bt\('([A-Za-z0-9_.]+)'/g)) used.add(m[1]);
}
for (const f of php) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(/['"]((?:err|ok)\.lms\.[A-Za-z0-9_.]+)['"]/g)) used.add(m[1]);
}
// the nav key emitted by SchoolNav
used.add('appNav.externalContent');

// enum families used through enumLabel(t, prefix, value): prefix -> every value the server can send
const families = {
    'lms.status': ['draft', 'published', 'archived', 'submitted', 'graded', 'returned', 'missing', 'pending'],
    'lms.qtype': ['multiple_choice', 'true_false', 'short_answer', 'essay'],
    'lms.ctype': ['text', 'video', 'document', 'link', 'lti', 'scorm', 'external'],
    'lms.mtype': ['file', 'document', 'video', 'link'],
    'lms.release': ['immediate', 'after_close', 'scheduled', 'manual'],
    'lms.method': ['highest', 'latest', 'average'],
    'lms.latePolicy': ['allow', 'block'],
    'lms.grading': ['graded', 'needs_grading'],
    'lms.gradeType': ['assignment', 'quiz'],
    'lms.people.sourceType': ['manual', 'class'],
    'lms.people.state': ['active', 'withdrawn'],
    'lms.forum.type': ['discussion', 'question'],
};
for (const [p, vals] of Object.entries(families)) vals.forEach((v) => used.add(`${p}.${v}`));

// every enumLabel prefix used in the pages must be a known family
const known = new Set(Object.keys(families));
for (const f of jsx) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(/enumLabel\(t,\s*'([A-Za-z0-9_.]+)'/g)) {
        if (!known.has(m[1])) {
            console.log(`UNKNOWN enumLabel prefix in ${f}: ${m[1]}`);
            process.exitCode = 1;
        }
    }
}

let bad = process.exitCode ? 1 : 0;
for (const k of [...used].sort()) {
    for (const l of ['en', 'fr', 'ar']) {
        const v = catalogs[l]?.[k];
        if (typeof v !== 'string' || v.trim() === '') {
            console.log(`MISSING ${l}: ${k}`);
            bad++;
        }
    }
}

// FR / AR must really be translated (not an English copy) except for tokens that are legitimately identical
const SAME_OK = /^(?:lms\.(?:common\.minutesShort|q\.pointsShort|ext\.version)|appNav\.)/;
const ph = (s) => (s.match(/\{[a-z]+\}/g) ?? []).sort().join(',');
for (const k of Object.keys(lmsCatalog.en)) {
    for (const l of ['fr', 'ar']) {
        if (ph(lmsCatalog.en[k]) !== ph(lmsCatalog[l][k])) {
            console.log(`PLACEHOLDER MISMATCH ${l}: ${k}`);
            bad++;
        }
        if (l === 'ar' && /^[\x00-\x7F]+$/.test(lmsCatalog.ar[k]) && !SAME_OK.test(k) && lmsCatalog.ar[k] === lmsCatalog.en[k]) {
            console.log(`AR looks untranslated: ${k}`);
            bad++;
        }
        if (l === 'fr' && lmsCatalog.fr[k] === lmsCatalog.en[k] && lmsCatalog.en[k].length > 24) {
            console.log(`FR looks untranslated: ${k}`);
            bad++;
        }
    }
    if (k in academicCatalog.en || k in recordsCatalog.en) {
        console.log(`OVERRIDES another catalog: ${k}`);
        bad++;
    }
}

// legacy hard-coded English must not come back in the LMS pages
for (const f of jsx) {
    const s = fs.readFileSync(f, 'utf8');
    const stray = [...s.matchAll(/>\s*([A-Z][a-z]+(?: [a-z]+)*)\s*</g)].map((m) => m[1]).filter((x) => x.length > 3);
    if (stray.length) {
        console.log(`POSSIBLE HARD-CODED TEXT in ${f}: ${[...new Set(stray)].join(' | ')}`);
        bad++;
    }
}

const defined = Object.keys(lmsCatalog.en);
const unused = defined.filter((k) => !used.has(k) && !k.startsWith('lms.forum.type.'));
console.log(`LMS keys defined: ${defined.length}; required by pages/services: ${used.size}; defined-but-unreferenced: ${unused.length}`);
if (unused.length) console.log(unused.join('\n'));
console.log(bad ? `FAILED (${bad})` : 'OK: all Phase 7 keys present in en/fr/ar');
process.exit(bad ? 1 : 0);
