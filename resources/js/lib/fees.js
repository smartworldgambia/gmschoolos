/** Helpers shared by the Phase 9 finance screens. Amounts are whole Dalasi. */

export function money(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    const n = Number(value);

    return Number.isNaN(n) ? String(value) : `D ${n.toLocaleString('en-US')}`;
}

/** Methods staff can record at the desk. Values are stable slugs; labels come from the catalog (`fin.method.*`). */
export const STAFF_METHODS = ['cash', 'wave', 'bank_transfer', 'card', 'other'];

/** Methods a parent or learner can report. Cash is only ever taken at the school desk. */
export const FAMILY_METHODS = ['wave', 'bank_transfer', 'other'];

/** Methods the school can configure as a collection channel (card is a desk-terminal method only). */
export const SCHOOL_METHODS = ['cash', 'wave', 'bank_transfer', 'other'];

export const ADJUSTMENT_KINDS = ['discount', 'scholarship', 'waiver'];

export const isOpenInvoice = (status) => ['issued', 'partial'].includes(status);
