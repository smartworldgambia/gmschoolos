// Phase 9 i18n gate: every key the Phase 9 pages and PHP use exists in en, fr and ar, with no empty value,
// no placeholder drift, no override of another catalog and no hard-coded English between tags.
// Each module is one entry in `modules`; add the module's catalog, pages and PHP folders as it is built.
import fs from 'fs';
import path from 'path';
import * as app from '../resources/js/i18n/appCatalog.js';
import { financeCatalog } from '../resources/js/i18n/financeCatalog.js';
import { hrCatalog } from '../resources/js/i18n/hrCatalog.js';
import { opsModules } from '../resources/js/i18n/opsCatalog.js';
import { commCatalog } from '../resources/js/i18n/commCatalog.js';
import { lmsCatalog } from '../resources/js/i18n/lmsCatalog.js';
import { recordsCatalog } from '../resources/js/i18n/recordsCatalog.js';
import { academicCatalog } from '../resources/js/i18n/academicCatalog.js';
import { billingCatalog } from '../resources/js/i18n/billingCatalog.js';
import { paymentsCatalog } from '../resources/js/i18n/paymentsCatalog.js';
import { onboardingCatalog } from '../resources/js/i18n/onboardingCatalog.js';
import { integrationsCatalog } from '../resources/js/i18n/integrationsCatalog.js';

const catalogs = app.appCatalog;
const modules = [
    {
        name: 'finance',
        catalog: financeCatalog,
        pages: ['resources/js/Pages/Fees', 'resources/js/Components/SchoolOS/FinanceLayout.jsx'],
        php: ['app/Http/Controllers/Fees', 'app/Services/Finance', 'app/Services/Ops'],
        phpPrefix: /^(?:ok|err)\.(?:fin|ops)\./,
        families: {
            'fin.tab': ['overview', 'invoices', 'payments', 'adjustments', 'reconciliation', 'reports', 'setup'],
            'fin.status': ['issued', 'partial', 'paid', 'overdue', 'cancelled', 'pending', 'confirmed', 'rejected', 'voided', 'approved', 'revoked', 'matched', 'variance'],
            'fin.method': ['cash', 'wave', 'bank_transfer', 'card', 'other'],
            'fin.kind': ['fee', 'discount', 'scholarship', 'waiver'],
            'fin.bucket': ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'],
        },
    },
    {
        name: 'hr',
        catalog: hrCatalog,
        pages: ['resources/js/Pages/Hr', 'resources/js/Components/SchoolOS/HrLayout.jsx'],
        php: ['app/Http/Controllers/Hr', 'app/Services/Hr'],
        phpPrefix: /^(?:ok|err)\.hr\./,
        families: {
            'hr.tab': ['overview', 'staff', 'leave', 'payroll', 'setup', 'myLeave', 'myPayslips'],
            'hr.status': ['active', 'inactive', 'exited', 'pending', 'approved', 'rejected', 'cancelled', 'ended', 'terminated', 'draft', 'posted', 'paid'],
            'hr.leaveType': ['annual', 'sick', 'unpaid', 'other'],
            'hr.employment': ['permanent', 'contract', 'part_time', 'volunteer'],
            'hr.contractType': ['permanent', 'fixed_term', 'part_time', 'probation'],
            'hr.docType': ['contract', 'id', 'certificate', 'cv', 'other'],
            'hr.compKind': ['allowance', 'deduction'],
            'hr.calc': ['fixed', 'percent_of_basic'],
        },
    },
    {
        name: 'library',
        catalog: opsModules.libCatalog,
        pages: ['resources/js/Pages/Library', 'resources/js/Components/SchoolOS/LibraryLayout.jsx', 'resources/js/Components/SchoolOS/ModuleLayout.jsx'],
        php: ['app/Http/Controllers/Library', 'app/Services/Library'],
        phpPrefix: /^(?:ok|err)\.lib\./,
        families: {
            'lib.tab': ['catalogue', 'loans', 'fines', 'setup'],
            'lib.copy': ['available', 'on_loan', 'damaged', 'withdrawn', 'lost'],
            'lib.loan': ['out', 'overdue', 'returned'],
            'lib.fine': ['unpaid', 'paid', 'waived'],
            'lib.fineReason': ['overdue', 'lost', 'damaged'],
            'lib.view': ['out', 'overdue', 'returned'],
        },
    },
    {
        name: 'inventory',
        catalog: opsModules.invCatalog,
        pages: ['resources/js/Pages/Inventory', 'resources/js/Components/SchoolOS/InventoryLayout.jsx'],
        php: ['app/Http/Controllers/Inventory', 'app/Services/Inventory'],
        phpPrefix: /^(?:ok|err)\.(?:inv|asset)\./,
        families: {
            'inv.tabs': ['label'],
            'inv.tab': ['items', 'stock', 'assets', 'setup'],
            'inv.move': ['receipt', 'issue', 'adjustment', 'transfer', 'transfer_in', 'transfer_out'],
            'inv.status': ['active', 'inactive', 'low'],
            'asset.status': ['in_store', 'in_use', 'under_maintenance', 'disposed', 'lost'],
            'asset.condition': ['new', 'good', 'fair', 'poor'],
            'asset.maintKind': ['preventive', 'repair', 'inspection'],
            'asset.maintStatus': ['scheduled', 'done', 'cancelled'],
        },
    },
    {
        name: 'procurement',
        catalog: opsModules.procCatalog,
        pages: ['resources/js/Pages/Procurement', 'resources/js/Components/SchoolOS/ProcurementLayout.jsx'],
        php: ['app/Http/Controllers/Procurement', 'app/Services/Procurement'],
        phpPrefix: /^(?:ok|err)\.proc\./,
        families: {
            'proc.tab': ['requests', 'orders', 'invoices', 'suppliers', 'reports', 'setup'],
            'proc.status': ['draft', 'submitted', 'approved', 'rejected', 'cancelled', 'ordered', 'issued', 'partially_received', 'received', 'recorded', 'paid'],
        },
    },
    {
        name: 'transport',
        catalog: opsModules.trCatalog,
        pages: ['resources/js/Pages/Transport', 'resources/js/Components/SchoolOS/TransportLayout.jsx'],
        php: ['app/Http/Controllers/Transport', 'app/Services/Transport'],
        phpPrefix: /^(?:ok|err)\.tr\./,
        families: {
            'tr.tabs': ['label'],
            'tr.tab': ['overview', 'attendance'],
            'tr.ride': ['boarded', 'absent', 'not_travelling'],
        },
    },
    {
        name: 'boarding',
        catalog: opsModules.bdCatalog,
        pages: ['resources/js/Pages/Boarding', 'resources/js/Components/SchoolOS/BoardingLayout.jsx'],
        php: ['app/Http/Controllers/Boarding', 'app/Services/Boarding'],
        phpPrefix: /^(?:ok|err)\.bd\./,
        families: {
            'bd.tabs': ['label'],
            'bd.tab': ['overview', 'attendance', 'welfare'],
            'bd.gender': ['male', 'female', 'mixed'],
            'bd.mark': ['present', 'absent', 'leave', 'sick'],
            'bd.welKind': ['health', 'pastoral', 'discipline', 'visitor', 'other'],
        },
    },
    {
        name: 'safeguarding',
        catalog: opsModules.sgCatalog,
        pages: ['resources/js/Pages/Safeguarding', 'resources/js/Components/SchoolOS/SafeguardingLayout.jsx'],
        php: ['app/Http/Controllers/Safeguarding', 'app/Services/Safeguarding'],
        phpPrefix: /^(?:ok|err)\.sg\./,
        families: {
            'sg.tabs': ['label'],
            'sg.tab': ['cases', 'report'],
            'sg.category': ['welfare', 'bullying', 'abuse_concern', 'other'],
            'sg.severity': ['low', 'medium', 'high', 'critical'],
            'sg.status': ['open', 'investigating', 'closed'],
        },
    },
    {
        name: 'events',
        catalog: opsModules.evCatalog,
        pages: ['resources/js/Pages/Events', 'resources/js/Components/SchoolOS/EventsLayout.jsx'],
        php: ['app/Http/Controllers/Events', 'app/Services/Events'],
        phpPrefix: /^(?:ok|err)\.ev\./,
        families: {
            'ev.tabs': ['label'],
            'ev.tab': ['calendar', 'venues', 'reports', 'setup'],
            'ev.category': ['academic', 'sports', 'cultural', 'meeting', 'trip', 'other'],
            'ev.audience': ['all', 'staff', 'students', 'parents', 'class'],
            'ev.status': ['draft', 'published', 'cancelled', 'completed'],
            'ev.who': ['student', 'staff'],
            'ev.reg': ['registered', 'cancelled'],
            'ev.mark': ['present', 'absent'],
        },
    },
];

const walk = (p) => {
    if (!fs.existsSync(p)) return [];
    if (fs.statSync(p).isFile()) return [p];
    return fs.readdirSync(p).flatMap((f) => walk(path.join(p, f)));
};

const SAME_OK = /^(?:appNav\.|fin\.method\.wave$|fin\.method\.cash$)/;
const ph = (s) => (s.match(/\{[a-z]+\}/g) ?? []).sort().join(',');
const others = [commCatalog, lmsCatalog, recordsCatalog, academicCatalog, billingCatalog, paymentsCatalog, onboardingCatalog, integrationsCatalog];
let bad = 0;

for (const mod of modules) {
    const used = new Set();
    const files = mod.pages.flatMap(walk).filter((f) => f.endsWith('.jsx'));
    const sources = files.map((f) => [f, fs.readFileSync(f, 'utf8')]);

    for (const [, src] of sources) {
        for (const m of src.matchAll(/\bt\('([A-Za-z0-9_.]+)'/g)) used.add(m[1]);
    }
    for (const f of mod.php.flatMap(walk).filter((x) => x.endsWith('.php'))) {
        for (const m of fs.readFileSync(f, 'utf8').matchAll(/['"]((?:err|ok)\.[A-Za-z0-9_.]+)['"]/g)) {
            if (mod.phpPrefix.test(m[1])) used.add(m[1]);
        }
    }
    for (const [prefix, vals] of Object.entries(mod.families)) vals.forEach((v) => used.add(`${prefix}.${v}`));

    // every dynamic t(`prefix.${x}`) must have a declared family
    for (const [f, src] of sources) {
        for (const m of src.matchAll(/\bt\(`([A-Za-z0-9_.]+)\.\$\{/g)) {
            if (!mod.families[m[1]]) {
                console.log(`UNDECLARED dynamic family ${m[1]} in ${f}`);
                bad++;
            }
        }
        for (const m of src.matchAll(/enumLabel\(t, '([A-Za-z0-9_.]+)'/g)) {
            if (!mod.families[m[1]]) {
                console.log(`UNDECLARED enum family ${m[1]} in ${f}`);
                bad++;
            }
        }
    }

    for (const k of [...used].sort()) {
        for (const l of ['en', 'fr', 'ar']) {
            const v = catalogs[l]?.[k];
            if (typeof v !== 'string' || v.trim() === '') {
                console.log(`MISSING ${l}: ${k}`);
                bad++;
            }
        }
    }

    for (const k of Object.keys(mod.catalog.en)) {
        if (others.some((c) => k in c.en)) {
            console.log(`OVERRIDES another catalog: ${k}`);
            bad++;
        }
        for (const l of ['fr', 'ar']) {
            if (typeof mod.catalog[l][k] !== 'string' || mod.catalog[l][k].trim() === '') {
                console.log(`EMPTY ${l}: ${k}`);
                bad++;
                continue;
            }
            if (ph(mod.catalog.en[k]) !== ph(mod.catalog[l][k])) {
                console.log(`PLACEHOLDER MISMATCH ${l}: ${k}`);
                bad++;
            }
            if (l === 'ar' && /^[\x00-\x7F]+$/.test(mod.catalog.ar[k]) && !SAME_OK.test(k) && mod.catalog.ar[k] === mod.catalog.en[k]) {
                console.log(`AR looks untranslated: ${k}`);
                bad++;
            }
            if (l === 'fr' && mod.catalog.fr[k] === mod.catalog.en[k] && mod.catalog.en[k].length > 24 && !SAME_OK.test(k)) {
                console.log(`FR looks untranslated: ${k}`);
                bad++;
            }
        }
    }

    const stray = [];
    for (const [f, src] of sources) {
        for (const m of src.matchAll(/>\s*([A-Z][a-z]+(?: [a-z]+)*)\s*</g)) if (m[1].length > 3) stray.push(`${path.basename(f)}: ${m[1]}`);
        for (const m of src.matchAll(/\b(?:placeholder|title|aria-label|label)="([A-Za-z][^"{]{3,})"/g)) stray.push(`${path.basename(f)}: ${m[1]}`);
    }
    if (stray.length) {
        console.log(`POSSIBLE HARD-CODED TEXT: ${[...new Set(stray)].join(' | ')}`);
        bad++;
    }

    const defined = Object.keys(mod.catalog.en);
    const unused = defined.filter((k) => !used.has(k));
    console.log(`[${mod.name}] keys defined: ${defined.length}; required: ${used.size}; defined-but-unreferenced: ${unused.length}`);
    if (unused.length) console.log(unused.join('\n'));
}

console.log(bad ? `FAILED (${bad})` : 'OK: all Phase 9 keys present in en/fr/ar');
process.exit(bad ? 1 : 0);
