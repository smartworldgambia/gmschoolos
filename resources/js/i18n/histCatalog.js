/** Phase 10 leftover historical UI catalogs: dashboards, profile, reports, exports, platform chrome. */
import { buildCatalog } from './buildCatalog.js';
import { histSchoolRows } from './histSchoolRows.js';
import { histProfileRows } from './histProfileRows.js';
import { histPlatRows } from './histPlatRows.js';
import { histPlatRows2 } from './histPlatRows2.js';

export const histCatalog = buildCatalog('hist', histSchoolRows, histProfileRows, histPlatRows, histPlatRows2);
export const histModules = { histSchoolRows, histProfileRows, histPlatRows, histPlatRows2 };
