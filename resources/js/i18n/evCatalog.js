/** Phase 9F events catalog (en / fr / ar). */
import { buildCatalog } from './buildCatalog.js';
import { evRows } from './evRows.js';

export const evCatalog = buildCatalog('events', evRows);
