/** Phase 9D inventory and assets catalog (en / fr / ar). */
import { buildCatalog } from './buildCatalog.js';
import { invRows } from './invRows.js';
import { assetRows } from './assetRows.js';

export const invCatalog = buildCatalog('inventory', invRows, assetRows);
