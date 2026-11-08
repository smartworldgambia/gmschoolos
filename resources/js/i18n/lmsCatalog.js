/**
 * Phase 7 (integrated LMS) catalog: en / fr / ar built from one row list so the three locales can never drift apart.
 * Merged into appCatalog.js.
 */
import { lmsRows1 } from './lmsRows1.js';
import { lmsRows2 } from './lmsRows2.js';
import { lmsRows3 } from './lmsRows3.js';
import { lmsRows4 } from './lmsRows4.js';

const rows = [...lmsRows1, ...lmsRows2, ...lmsRows3, ...lmsRows4];

const en = {};
const fr = {};
const ar = {};

for (const [key, e, f, a] of rows) {
    if (key in en) {
        throw new Error(`Duplicate LMS catalog key: ${key}`);
    }
    en[key] = e;
    fr[key] = f;
    ar[key] = a;
}

export const lmsCatalog = { en, fr, ar };
