/**
 * Build the en / fr / ar maps of a module catalog from row lists `[key, en, fr, ar]`, so the three locales can never drift apart.
 * A duplicate key inside one module is a build error.
 */
export function buildCatalog(name, ...rowLists) {
    const en = {};
    const fr = {};
    const ar = {};

    for (const [key, e, f, a] of rowLists.flat()) {
        if (key in en) {
            throw new Error(`Duplicate ${name} catalog key: ${key}`);
        }
        en[key] = e;
        fr[key] = f;
        ar[key] = a;
    }

    return { en, fr, ar };
}
