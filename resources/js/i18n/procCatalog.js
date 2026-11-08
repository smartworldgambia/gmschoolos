/** Phase 9D procurement catalog (en / fr / ar). */
import { buildCatalog } from './buildCatalog.js';
import { procRows } from './procRows.js';

export const procCatalog = buildCatalog('procurement', procRows);
