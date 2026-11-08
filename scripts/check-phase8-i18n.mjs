// Phase 8 i18n gate: every key the portal / communication pages and PHP codes use must exist in en, fr and ar,
// with no empty value, no placeholder drift, no override of another catalog, and no enum label missing.
import fs from 'fs';
import { commCatalog } from '../resources/js/i18n/commCatalog.js';
import { lmsCatalog } from '../resources/js/i18n/lmsCatalog.js';
import { recordsCatalog } from '../resources/js/i18n/recordsCatalog.js';
import { academicCatalog } from '../resources/js/i18n/academicCatalog.js';
import { billingCatalog } from '../resources/js/i18n/billingCatalog.js';
import { paymentsCatalog } from '../resources/js/i18n/paymentsCatalog.js';
import { onboardingCatalog } from '../resources/js/i18n/onboardingCatalog.js';
import * as app from '../resources/js/i18n/appCatalog.js';

const catalogs = app.appCatalog ?? app.default ?? null;
if (!catalogs) {
    console.error('appCatalog export not found:', Object.keys(app));
    process.exit(2);
}

const walk = (dir) => (fs.existsSync(dir) ? fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(`${dir}/${e.name}`) : [`${dir}/${e.name}`])) : []);
const P = 'resources/js/Pages';
const jsx = [
    ...walk(`${P}/Accounts`), ...walk(`${P}/Messages`), ...walk(`${P}/Announcements`), ...walk(`${P}/Notifications`), ...walk(`${P}/Communications`),
    ...walk(`${P}/Teacher`), ...walk(`${P}/Student`), ...walk(`${P}/Parent`), ...walk('resources/js/Components/Comm'),
].filter((f) => f.endsWith('.jsx'));
const php = [...walk('app/Services'), ...walk('app/Http/Controllers'), ...walk('app/Support'), ...walk('app/Http/Middleware'), ...walk('app/Http/Requests'), ...walk('app/Notifications')].filter((f) => f.endsWith('.php'));

const used = new Set();
for (const f of jsx) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(/\bt\('([A-Za-z0-9_.]+)'/g)) used.add(m[1]);
}
for (const f of php) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(/['"]((?:err|ok)\.(?:acct|msg|ann|sms|notif)\.[A-Za-z0-9_.]+)['"]/g)) used.add(m[1]);
}
['messages', 'announcements', 'accounts', 'communications'].forEach((k) => used.add(`appNav.${k}`));
['student', 'parent', 'school_admin', 'school_owner'].forEach((k) => used.add(`setup.role.${k}`));
// keys passed through variables (window.confirm / window.prompt in Accounts/Index.jsx)
['acct.confirm.reset', 'acct.confirm.restore', 'acct.prompt.suspend', 'acct.prompt.revoke'].forEach((k) => used.add(k));

// dynamic and enum families: prefix -> every value the server / page can send
const families = {
    'acct.tab': ['people', 'invite', 'invitations'],
    'acct.invite.student': ['title', 'search', 'none', 'hint', 'emailHint'],
    'acct.invite.guardian': ['title', 'search', 'none', 'hint', 'emailHint'],
    'acct.inviteState': ['pending', 'accepted', 'revoked', 'expired'],
    'acct.kind': ['staff', 'student', 'guardian'],
    'acct.status': ['active', 'suspended', 'revoked'],
    'msg.mode': ['direct', 'group'],
    'msg.who': ['colleague', 'family'],
    'msg.audience': ['guardians', 'students', 'both'],
    'msg.mod.status': ['open', 'dismissed', 'actioned'],
    'ann.tab': ['feed', 'mine'],
    'ann.audience': ['school', 'staff', 'teachers', 'students', 'guardians', 'classes'],
    'ann.status': ['draft', 'scheduled', 'published', 'cancelled'],
    'ann.channel': ['in_app', 'email', 'sms'],
    'notif.channel': ['in_app', 'email', 'sms'],
    'notif.category': ['announcement', 'attendance', 'exam', 'fees', 'message', 'lms', 'account'],
    'notif.categoryHelp': ['announcement', 'attendance', 'exam', 'fees', 'message', 'lms', 'account'],
    'notif.deliveryStatus': ['sent', 'logged', 'failed'],
    'comm.tab': ['settings', 'templates', 'sms', 'deliveries'],
    'comm.provider.state': ['configured', 'unconfigured', 'log', 'off'],
    'comm.provider.note': ['configured', 'unconfigured', 'log', 'off'],
    'comm.provider.name': ['log', 'null', 'twilio', 'africastalking'],
    'comm.locale': ['en', 'fr', 'ar'],
    'comm.tpl': ['attendance_alert', 'exam_published', 'announcement', 'message_received', 'test'],
    'comm.tplHelp': ['attendance_alert', 'exam_published', 'announcement', 'message_received', 'test'],
    'comm.sms.status': ['queued', 'sent', 'delivered', 'failed', 'logged'],
    'dash.invoiceStatus': ['draft', 'issued', 'partial', 'paid', 'overdue', 'cancelled'],
};
for (const [p, vals] of Object.entries(families)) vals.forEach((v) => used.add(`${p}.${v}`));

// every enumLabel prefix used in the pages must be a known family
const known = new Set(Object.keys(families));
let bad = 0;
for (const f of jsx) {
    const s = fs.readFileSync(f, 'utf8');
    for (const m of s.matchAll(/enumLabel\(t,\s*'([A-Za-z0-9_.]+)'/g)) {
        if (!known.has(m[1])) {
            console.log(`UNKNOWN enumLabel prefix in ${f}: ${m[1]}`);
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

const SAME_OK = /^(?:appNav\.communications|appNav\.messages|comm\.provider\.name\.(?:twilio|africastalking)|comm\.tab\.|msg\.title|msg\.message|notif\.title|comm\.title|ann\.channel\.sms|notif\.channel\.sms|ann\.channel\.email|notif\.channel\.email|dash\.quick\.messages|msg\.mod\.|msg\.staff|acct\.invitations|acct\.col\.actions|dash\.col\.date|comm\.sms\.segments|comm\.stats\.segments|msg\.new$|appNav\.)/;
const ph = (s) => (s.match(/\{[a-z]+\}/g) ?? []).sort().join(',');
const others = [lmsCatalog, recordsCatalog, academicCatalog, billingCatalog, paymentsCatalog, onboardingCatalog];
for (const k of Object.keys(commCatalog.en)) {
    for (const l of ['fr', 'ar']) {
        if (ph(commCatalog.en[k]) !== ph(commCatalog[l][k])) {
            console.log(`PLACEHOLDER MISMATCH ${l}: ${k}`);
            bad++;
        }
        if (l === 'ar' && /^[\x00-\x7F]+$/.test(commCatalog.ar[k]) && !SAME_OK.test(k) && commCatalog.ar[k] === commCatalog.en[k]) {
            console.log(`AR looks untranslated: ${k}`);
            bad++;
        }
        if (l === 'fr' && commCatalog.fr[k] === commCatalog.en[k] && commCatalog.en[k].length > 24 && !SAME_OK.test(k)) {
            console.log(`FR looks untranslated: ${k}`);
            bad++;
        }
    }
    if (others.some((c) => k in c.en)) {
        console.log(`OVERRIDES another catalog: ${k}`);
        bad++;
    }
}

// hard-coded English must not appear between tags in the Phase 8 pages (brand names are allowed)
const BRAND = new Set(['Twilio', 'Africa']);
for (const f of jsx) {
    const s = fs.readFileSync(f, 'utf8');
    const stray = [...s.matchAll(/>\s*([A-Z][a-z]+(?: [a-z]+)*)\s*</g)].map((m) => m[1]).filter((x) => x.length > 3 && !BRAND.has(x));
    if (stray.length) {
        console.log(`POSSIBLE HARD-CODED TEXT in ${f}: ${[...new Set(stray)].join(' | ')}`);
        bad++;
    }
}

const defined = Object.keys(commCatalog.en);
const unused = defined.filter((k) => !used.has(k));
console.log(`Phase 8 keys defined: ${defined.length}; required by pages/services: ${used.size}; defined-but-unreferenced: ${unused.length}`);
if (unused.length) console.log(unused.join('\n'));
console.log(bad ? `FAILED (${bad})` : 'OK: all Phase 8 keys present in en/fr/ar');
process.exit(bad ? 1 : 0);
