/** Phase 9E boarding catalog (en / fr / ar). */
import { buildCatalog } from './buildCatalog.js';
import { bdRows } from './bdRows.js';

export const bdCatalog = buildCatalog('boarding', bdRows);
