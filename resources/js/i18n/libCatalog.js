/** Phase 9C library catalog (en / fr / ar). Merged into appCatalog.js. */
import { buildCatalog } from './buildCatalog.js';
import { libRows } from './libRows.js';

export const libCatalog = buildCatalog('library', libRows);
