/**
 * Phase 9A (finance) catalog: en / fr / ar built from one row list so the three locales can never drift apart.
 * Merged into appCatalog.js.
 */
import { financeRows1 } from './financeRows1.js';
import { financeRows2 } from './financeRows2.js';
import { financeRows3 } from './financeRows3.js';

const rows = [...financeRows1, ...financeRows2, ...financeRows3];

const en = {};
const fr = {};
const ar = {};

for (const [key, e, f, a] of rows) {
    if (key in en) {
        throw new Error(`Duplicate finance catalog key: ${key}`);
    }
    en[key] = e;
    fr[key] = f;
    ar[key] = a;
}

export const financeCatalog = { en, fr, ar };
