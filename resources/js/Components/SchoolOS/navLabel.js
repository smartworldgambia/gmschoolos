/** Resolve localized nav label from PHP `key` with English `name` fallback. */
export function navLabel(item, t) {
    if (item?.key) {
        const key = `appNav.${item.key}`;
        const translated = t(key);
        if (translated !== key) return translated;
    }
    return item?.name ?? '';
}
