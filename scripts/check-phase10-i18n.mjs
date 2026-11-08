// Phase 10 i18n gate: leftover historical chrome (dashboards, reports, exports, profile, platform console)
// must have every used key in en, fr and ar, with no empty value, no placeholder drift, no override of
// another catalog and no hard-coded English between tags.
import fs from 'fs';
import path from 'path';
import * as app from '../resources/js/i18n/appCatalog.js';
import { histCatalog } from '../resources/js/i18n/histCatalog.js';
import { financeCatalog } from '../resources/js/i18n/financeCatalog.js';
import { hrCatalog } from '../resources/js/i18n/hrCatalog.js';
import { opsCatalog } from '../resources/js/i18n/opsCatalog.js';
import { commCatalog } from '../resources/js/i18n/commCatalog.js';
import { lmsCatalog } from '../resources/js/i18n/lmsCatalog.js';
import { recordsCatalog } from '../resources/js/i18n/recordsCatalog.js';
import { academicCatalog } from '../resources/js/i18n/academicCatalog.js';
import { billingCatalog } from '../resources/js/i18n/billingCatalog.js';
import { paymentsCatalog } from '../resources/js/i18n/paymentsCatalog.js';
import { onboardingCatalog } from '../resources/js/i18n/onboardingCatalog.js';
import { integrationsCatalog } from '../resources/js/i18n/integrationsCatalog.js';

const catalogs = app.appCatalog;
const walk = (p) => {
    if (!fs.existsSync(p)) return [];
    if (fs.statSync(p).isFile()) return [p];
    return fs.readdirSync(p).flatMap((f) => walk(path.join(p, f)));
};

const pages = [
    'resources/js/Pages/Admin/Dashboard.jsx',
    'resources/js/Pages/Dashboard.jsx',
    'resources/js/Pages/Reports',
    'resources/js/Pages/Exports',
    'resources/js/Pages/Profile',
    'resources/js/Pages/Platform/Dashboard.jsx',
    'resources/js/Pages/Platform/Schools',
    'resources/js/Pages/Platform/Backups',
    'resources/js/Pages/Platform/Ops',
    'resources/js/Pages/Platform/Scale',
    'resources/js/Pages/Platform/Monitoring',
    'resources/js/Pages/Platform/Capacity',
    'resources/js/Pages/Platform/Marketing',
];

const families = {
    'dash.admin.quick': ['students', 'attendance', 'fees', 'lms'],
    'dash.home.link': ['admin', 'teacher', 'student', 'parent', 'fees', 'academics', 'lms'],
    'plat.schools.field': ['name', 'email', 'billing_email', 'region', 'slug', 'host'],
    'plat.schools.quota': ['max_students', 'max_staff', 'max_storage_mb', 'max_exports_per_day', 'max_api_requests_per_hour'],
    'plat.schools.status': ['active', 'pending', 'inactive'],
    'plat.cap.tier': ['shared', 'premium_isolated', 'dedicated_db'],
    'plat.mon.severity': ['info', 'warning', 'critical'],
};

const files = pages.flatMap(walk).filter((f) => f.endsWith('.jsx'));
const sources = files.map((f) => [f, fs.readFileSync(f, 'utf8')]);
const used = new Set();
let bad = 0;

for (const [, src] of sources) {
    for (const m of src.matchAll(/\bt\('([A-Za-z0-9_.]+)'/g)) used.add(m[1]);
}
for (const [prefix, vals] of Object.entries(families)) vals.forEach((v) => used.add(`${prefix}.${v}`));

for (const [f, src] of sources) {
    for (const m of src.matchAll(/\bt\(`([A-Za-z0-9_.]+)\.\$\{/g)) {
        if (!families[m[1]]) {
            console.log(`UNDECLARED dynamic family ${m[1]} in ${f}`);
            bad++;
        }
    }
}

for (const k of [...used].sort()) {
    for (const l of ['en', 'fr', 'ar']) {
        const v = catalogs[l]?.[k];
        if (typeof v !== 'string' || v.trim() === '') {
            console.log(`MISSING ${l}: ${k}`);
            bad++;
        }
    }
}

const SAME_OK = /^(?:appNav\.|plat\.(?:ok|scale\.col\.sql|cap\.col\.(?:rps|p95)|cap\.rps|ops\.col\.url|schools\.domainPlaceholder)$)/;
const ph = (s) => (s.match(/\{[a-z]+\}/g) ?? []).sort().join(',');
const others = [financeCatalog, hrCatalog, opsCatalog, commCatalog, lmsCatalog, recordsCatalog, academicCatalog, billingCatalog, paymentsCatalog, onboardingCatalog, integrationsCatalog];

for (const k of Object.keys(histCatalog.en)) {
    if (others.some((c) => k in c.en)) {
        console.log(`OVERRIDES another catalog: ${k}`);
        bad++;
    }
    for (const l of ['fr', 'ar']) {
        if (typeof histCatalog[l][k] !== 'string' || histCatalog[l][k].trim() === '') {
            console.log(`EMPTY ${l}: ${k}`);
            bad++;
            continue;
        }
        if (ph(histCatalog.en[k]) !== ph(histCatalog[l][k])) {
            console.log(`PLACEHOLDER MISMATCH ${l}: ${k}`);
            bad++;
        }
        if (l === 'ar' && /^[\x00-\x7F]+$/.test(histCatalog.ar[k]) && !SAME_OK.test(k) && histCatalog.ar[k] === histCatalog.en[k]) {
            console.log(`AR looks untranslated: ${k}`);
            bad++;
        }
        if (l === 'fr' && histCatalog.fr[k] === histCatalog.en[k] && histCatalog.en[k].length > 24 && !SAME_OK.test(k)) {
            console.log(`FR looks untranslated: ${k}`);
            bad++;
        }
    }
}

const stray = [];
for (const [f, src] of sources) {
    for (const m of src.matchAll(/>\s*([A-Z][a-z]+(?: [a-z]+)*)\s*</g)) if (m[1].length > 3) stray.push(`${path.basename(f)}: ${m[1]}`);
    for (const m of src.matchAll(/\b(?:placeholder|title|aria-label|label)="([A-Za-z][^"{]{3,})"/g)) stray.push(`${path.basename(f)}: ${m[1]}`);
}
if (stray.length) {
    console.log(`POSSIBLE HARD-CODED TEXT: ${[...new Set(stray)].join(' | ')}`);
    bad++;
}

const defined = Object.keys(histCatalog.en);
const unused = defined.filter((k) => !used.has(k));
console.log(`[hist] keys defined: ${defined.length}; required: ${used.size}; defined-but-unreferenced: ${unused.length}`);
if (unused.length) console.log(unused.join('\n'));
console.log(bad ? `FAILED (${bad})` : 'OK: all Phase 10 keys present in en/fr/ar');
process.exit(bad ? 1 : 0);
