/** All Phase 9C-9F operations module catalogs merged into one (en / fr / ar). Imported once by appCatalog.js. */
import { libCatalog } from './libCatalog.js';
import { invCatalog } from './invCatalog.js';
import { procCatalog } from './procCatalog.js';
import { trCatalog } from './trCatalog.js';
import { bdCatalog } from './bdCatalog.js';
import { sgCatalog } from './sgCatalog.js';
import { evCatalog } from './evCatalog.js';

const modules = [libCatalog, invCatalog, procCatalog, trCatalog, bdCatalog, sgCatalog, evCatalog];

const merge = (locale) => Object.assign({}, ...modules.map((m) => m[locale]));

export const opsCatalog = { en: merge('en'), fr: merge('fr'), ar: merge('ar') };
export const opsModules = { libCatalog, invCatalog, procCatalog, trCatalog, bdCatalog, sgCatalog, evCatalog };
