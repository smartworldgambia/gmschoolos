/**
 * Phase 8 (portals and communication) catalog: en / fr / ar built from one row list so the three locales can never drift apart.
 * Merged into appCatalog.js.
 */
import { commRows1 } from './commRows1.js';
import { commRows2 } from './commRows2.js';
import { commRows3 } from './commRows3.js';

const rows = [...commRows1, ...commRows2, ...commRows3];

const en = {};
const fr = {};
const ar = {};

for (const [key, e, f, a] of rows) {
    if (key in en) {
        throw new Error(`Duplicate communication catalog key: ${key}`);
    }
    en[key] = e;
    fr[key] = f;
    ar[key] = a;
}

export const commCatalog = { en, fr, ar };