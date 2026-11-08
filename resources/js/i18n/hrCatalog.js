/**
 * Phase 9B (HR, leave and payroll) catalog: en / fr / ar built from one row list so the three locales can never drift apart.
 * Merged into appCatalog.js.
 */
import { hrRows1 } from './hrRows1.js';
import { hrRows2 } from './hrRows2.js';
import { hrRows3 } from './hrRows3.js';

const rows = [...hrRows1, ...hrRows2, ...hrRows3];

const en = {};
const fr = {};
const ar = {};

for (const [key, e, f, a] of rows) {
    if (key in en) {
        throw new Error(`Duplicate HR catalog key: ${key}`);
    }
    en[key] = e;
    fr[key] = f;
    ar[key] = a;
}

export const hrCatalog = { en, fr, ar };
