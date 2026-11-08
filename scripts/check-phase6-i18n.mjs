// Phase 6 i18n gate: every key the new pages / PHP services use must exist in en, fr and ar,
// no record row may silently override another catalog's key, and no FR/AR value may be empty.
import fs from 'fs';
import { recordsCatalog } from '../resources/js/i18n/recordsCatalog.js';
import { academicCatalog } from '../resources/js/i18n/academicCatalog.js';
import * as app from '../resources/js/i18n/appCatalog.js';

const catalogs = app.appCatalog ?? app.default ?? null;
if (!catalogs) {
    console.error('appCatalog export not found:', Object.keys(app));
    process.exit(2);
}

const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(`${dir}/${e.name}`) : [`${dir}/${e.name}`]));
const jsx = [
    'resources/js/Components/SchoolOS/ReasonAction.jsx',
    'resources/js/Components/SchoolOS/SeniorRoundForm.jsx',
    'resources/js/Pages/School/SeniorApply.jsx',
    ...['ExternalExams', 'SeniorSecondary', 'Records', 'Verify'].flatMap((d) => walk(`resources/js/Pages/${d}`)),
];
const php = [...walk('app/Services'), ...walk('app/Http/Controllers')].filter((f) => f.endsWith('.php'));

const used = new Set();
const prefix = '(?:acad|import|apply|ext|col|ss|ssa|rec|ver|err|ok|appNav|studentStatus)';
for (const f of jsx) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(new RegExp(`['"\`](${prefix}\\.[A-Za-z0-9_.]+)['"\`]`, 'g'))) used.add(m[1]);
}
for (const f of php) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(/['"]((?:err|ok)\.(?:ext|col|cert|ss|rec)\.[A-Za-z0-9_.]+)['"]/g)) used.add(m[1]);
}

// dynamic families (prefix -> values)
const families = {
    'ext.candStatus': ['registered', 'confirmed', 'withdrawn'],
    'ext.certStatus': ['awaiting', 'received', 'available', 'collected', 'outstanding'],
    'ext.colStatus': ['outstanding', 'collected'],
    'ext.docType': ['registration_form', 'birth_certificate', 'photo', 'other'],
    'ext.event': ['entered', 'imported', 'verified', 'published', 'corrected'],
    'ext.resultStatus': ['entered', 'imported', 'verified', 'published'],
    'ext.sessionStatus': ['open', 'closed'],
    'ext.source': ['manual', 'import'],
    'ext.type': ['gabece', 'wassce'],
    'ext.setStatus': ['registered', 'confirmed', 'withdrawn'],
    'rec.event': ['created', 'exception', 'submitted', 'returned', 'approved', 'issued', 'reprinted', 'revoked'],
    'rec.status': ['draft', 'submitted', 'approved', 'issued', 'reprinted', 'revoked'],
    'rec.termSource': ['platform', 'manual'],
    'rec.type': ['transcript_g9', 'transcript_g12', 'testimonial', 'cover_letter', 'admission_letter'],
    'ss.appStatus': ['submitted', 'under_review', 'shortlisted', 'waitlisted', 'approved', 'rejected', 'withdrawn'],
    'ss.docType': ['gabece_slip', 'birth_certificate', 'previous_report', 'other'],
    'ss.event': ['submitted', 'recomputed', 'result_removed', 'result_corrected', 'result_added', 'results_verified', 'recommendation_applied', 'override', 'decision', 'withdrawn'],
    'ss.gender': ['male', 'female', 'other'],
    'ss.recommendation': ['review', 'reject', 'shortlist', 'waitlist'],
    'ss.roundStatus': ['draft', 'open', 'closed', 'finalised'],
    'ss.source': ['online', 'desk', 'manual'],
    'ss.moveTo': ['open', 'closed', 'draft', 'finalised'],
    'ss.reason': ['unknownGrade', 'minSubjects', 'missingRequired', 'failedRequired', 'notEnoughElectives', 'combination', 'noAggregate', 'threshold'],
    'ver.state': ['valid', 'revoked', 'not_found'],
    'ver.stateHelp': ['valid', 'revoked', 'not_found'],
    'err.ext.wrongGrade': ['gabece', 'wassce'],
    'err.rec.status': ['draft', 'submitted', 'approved', 'issued', 'reprinted', 'revoked'],
};
for (const [p, vals] of Object.entries(families)) vals.forEach((v) => used.add(`${p}.${v}`));
// prefix-only literals are enum prefixes, not keys
for (const p of Object.keys(families)) used.delete(p);
['ext.type', 'ext.source', 'rec.status', 'rec.type', 'rec.event'].forEach((p) => used.delete(p));
['ext.candStatus', 'ext.certStatus', 'ext.colStatus', 'ext.docType', 'ext.event', 'ext.resultStatus', 'ext.sessionStatus', 'rec.termSource', 'ss.appStatus', 'ss.docType', 'ss.event', 'ss.gender', 'ss.recommendation', 'ss.roundStatus', 'ss.source'].forEach((p) => used.delete(p));
['studentStatus'].forEach((p) => used.delete(p));

// PHP concatenations such as 'err.rec.status.'.$status are covered by the families above
for (const k of [...used]) if (k.endsWith('.')) used.delete(k);

let bad = 0;
for (const k of [...used].sort()) {
    for (const l of ['en', 'fr', 'ar']) {
        const v = catalogs[l]?.[k];
        if (typeof v !== 'string' || v.trim() === '') {
            console.log(`MISSING ${l}: ${k}`);
            bad++;
        }
    }
}

// placeholders must match across locales
for (const k of Object.keys(recordsCatalog.en)) {
    const ph = (s) => (s.match(/\{[a-z]+\}/g) ?? []).sort().join(',');
    for (const l of ['fr', 'ar']) {
        if (ph(recordsCatalog.en[k]) !== ph(recordsCatalog[l][k])) {
            console.log(`PLACEHOLDER MISMATCH ${l}: ${k}`);
            bad++;
        }
    }
    if (k in academicCatalog.en) {
        console.log(`OVERRIDES academic key: ${k}`);
        bad++;
    }
}

// keys defined but never referenced (informational)
const defined = Object.keys(recordsCatalog.en);
const unusedKeys = defined.filter((k) => !used.has(k) && !k.startsWith('appNav.'));
console.log(`records keys: ${defined.length}; used by pages/services: ${used.size}; defined-but-unreferenced: ${unusedKeys.length}`);
if (unusedKeys.length) console.log(unusedKeys.join('\n'));
console.log(bad ? `FAILED (${bad})` : 'OK: all Phase 6 keys present in en/fr/ar');
process.exit(bad ? 1 : 0);
