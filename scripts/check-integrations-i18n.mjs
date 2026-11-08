// Integrations console i18n gate: every key the page and the PHP codes use exists in en, fr and ar, with no empty value,
// no placeholder drift, no override of another catalog and no hard-coded English between tags.
import fs from 'fs';
import { integrationsCatalog } from '../resources/js/i18n/integrationsCatalog.js';
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

const page = 'resources/js/Pages/Platform/Integrations/Index.jsx';
const php = ['app/Http/Controllers/Platform/IntegrationController.php', 'app/Services/Integrations/IntegrationSettings.php'];
const used = new Set();

const src = fs.readFileSync(page, 'utf8');
for (const m of src.matchAll(/\bt\('([A-Za-z0-9_.]+)'/g)) used.add(m[1]);
for (const f of php) {
    for (const m of fs.readFileSync(f, 'utf8').matchAll(/['"]((?:err|ok)\.integ\.[A-Za-z0-9_.]+)['"]/g)) used.add(m[1]);
}
// the validation-code map in the controller also names codes without the exact quoted form above; keep it explicit
['required', 'invalid', 'tooLong', 'email', 'port', 'url', 'phone', 'password', 'testFailed', 'testFirst', 'nothingSaved'].forEach((k) => used.add(`err.integ.${k}`));
['saved', 'savedOff', 'activated', 'deactivated', 'cleared', 'confirmed', 'testSentMail', 'testSentSms'].forEach((k) => used.add(`ok.integ.${k}`));
used.add('appNav.integrations');

const families = {
    'integ.f': ['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'driver', 'twilio_sid', 'twilio_token', 'twilio_from', 'twilio_messaging_service_sid', 'twilio_status_callback', 'at_username', 'at_api_key', 'at_sender_id', 'at_sandbox', 'at_webhook_secret'],
    'integ.enc': ['tls', 'ssl'],
    'integ.state': ['none', 'unreadable', 'untested', 'failed', 'tested'],
    'integ.driver': ['smtp', 'log', 'array', 'null', 'twilio', 'africastalking'],
    'integ.event': ['saved', 'test_ok', 'test_failed', 'activated', 'deactivated', 'cleared', 'confirmed'],
    'integ.name': ['mail', 'sms'],
    'integ.flow': ['1', '2', '3', '4'],
};
for (const [p, vals] of Object.entries(families)) vals.forEach((v) => used.add(`${p}.${v}`));

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

// the server-side field list must be the family above
const defs = fs.readFileSync('app/Services/Integrations/IntegrationSettings.php', 'utf8');
for (const m of defs.matchAll(/^\s+'([a-z_]+)' => \['(?:string|int|bool|email|enum:[a-z|]+)', (?:true|false), (?:true|false)\],/gm)) {
    if (!families['integ.f'].includes(m[1])) {
        console.log(`FIELD without a label: ${m[1]}`);
        bad++;
    }
}

const SAME_OK = /^(?:appNav\.|integ\.name\.|integ\.driver\.|integ\.f\.(?:port|host|username|at_username)$|integ\.title$|integ\.webhook\.title$)/;
const ph = (s) => (s.match(/\{[a-z]+\}/g) ?? []).sort().join(',');
const others = [commCatalog, lmsCatalog, recordsCatalog, academicCatalog, billingCatalog, paymentsCatalog, onboardingCatalog];
for (const k of Object.keys(integrationsCatalog.en)) {
    for (const l of ['fr', 'ar']) {
        if (ph(integrationsCatalog.en[k]) !== ph(integrationsCatalog[l][k])) {
            console.log(`PLACEHOLDER MISMATCH ${l}: ${k}`);
            bad++;
        }
        if (l === 'ar' && /^[\x00-\x7F]+$/.test(integrationsCatalog.ar[k]) && !SAME_OK.test(k) && integrationsCatalog.ar[k] === integrationsCatalog.en[k]) {
            console.log(`AR looks untranslated: ${k}`);
            bad++;
        }
        if (l === 'fr' && integrationsCatalog.fr[k] === integrationsCatalog.en[k] && integrationsCatalog.en[k].length > 24 && !SAME_OK.test(k)) {
            console.log(`FR looks untranslated: ${k}`);
            bad++;
        }
    }
    if (others.some((c) => k in c.en)) {
        console.log(`OVERRIDES another catalog: ${k}`);
        bad++;
    }
}

const stray = [...src.matchAll(/>\s*([A-Z][a-z]+(?: [a-z]+)*)\s*</g)].map((m) => m[1]).filter((x) => x.length > 3);
if (stray.length) {
    console.log(`POSSIBLE HARD-CODED TEXT in ${page}: ${[...new Set(stray)].join(' | ')}`);
    bad++;
}

const defined = Object.keys(integrationsCatalog.en);
const unused = defined.filter((k) => !used.has(k));
console.log(`Integrations keys defined: ${defined.length}; required: ${used.size}; defined-but-unreferenced: ${unused.length}`);
if (unused.length) console.log(unused.join('\n'));
console.log(bad ? `FAILED (${bad})` : 'OK: all integrations keys present in en/fr/ar');
process.exit(bad ? 1 : 0);
