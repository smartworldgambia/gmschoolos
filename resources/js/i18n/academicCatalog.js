/**
 * Phase 5 (academic and student management) catalog: en / fr / ar built from one row list so the three
 * locales can never drift apart. Merged into appCatalog.js.
 */
import { academicRows1 } from './academicRows1.js';
import { academicRows2 } from './academicRows2.js';
import { academicRows3 } from './academicRows3.js';

const rows = [...academicRows1, ...academicRows2, ...academicRows3];

const en = {};
const fr = {};
const ar = {};

for (const [key, e, f, a] of rows) {
    if (key in en) {
        throw new Error(`Duplicate academic catalog key: ${key}`);
    }
    en[key] = e;
    fr[key] = f;
    ar[key] = a;
}

export const academicCatalog = { en, fr, ar };
