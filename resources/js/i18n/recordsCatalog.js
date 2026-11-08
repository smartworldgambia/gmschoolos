/**
 * Phase 6 (external examinations, Senior Secondary admissions, Records Office) catalog: en / fr / ar built from
 * one row list so the three locales can never drift apart. Merged into appCatalog.js.
 */
import { recordsRows1 } from './recordsRows1.js';
import { recordsRows2 } from './recordsRows2.js';
import { recordsRows3 } from './recordsRows3.js';

const rows = [...recordsRows1, ...recordsRows2, ...recordsRows3];

const en = {};
const fr = {};
const ar = {};

for (const [key, e, f, a] of rows) {
    if (key in en) {
        throw new Error(`Duplicate records catalog key: ${key}`);
    }
    en[key] = e;
    fr[key] = f;
    ar[key] = a;
}

export const recordsCatalog = { en, fr, ar };
