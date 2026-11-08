/**
 * Map helpers for school websites. No API key needed: we use the public
 * Google Maps embed + deep links, driven either by an admin-set pin (lat/lng)
 * or by the school's address.
 */

export function hasPin(lat, lng) {
    const a = Number.parseFloat(lat);
    const b = Number.parseFloat(lng);
    return Number.isFinite(a) && Number.isFinite(b) && Math.abs(a) <= 90 && Math.abs(b) <= 180;
}

/** The query string Google Maps should search / pin: exact pin wins over address. */
export function mapQuery({ lat, lng, address, region, country } = {}) {
    if (hasPin(lat, lng)) {
        return `${Number.parseFloat(lat)},${Number.parseFloat(lng)}`;
    }
    const text = [address, region, country].filter((v) => v && String(v).trim() !== '').join(', ');
    return text.trim();
}

export function mapEmbedUrl(query, locale = 'en') {
    if (!query) return null;
    return `https://maps.google.com/maps?q=${encodeURIComponent(query)}&z=15&hl=${encodeURIComponent(locale)}&output=embed`;
}

export function mapOpenUrl(query) {
    if (!query) return null;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}

export function mapDirectionsUrl(query) {
    if (!query) return null;
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(query)}`;
}

/**
 * Pull coordinates out of whatever the admin pastes:
 *  - "13.4549, -16.5790"
 *  - https://www.google.com/maps/place/.../@13.4549,-16.579,17z/...
 *  - ...!3d13.4549!4d-16.579...
 *  - ...?q=13.4549,-16.579  /  ?ll=...  /  ?query=...
 * Returns { lat, lng } or null.
 */
export function parseCoordinates(input) {
    const text = decodeURIComponent(String(input ?? '').trim());
    if (!text) return null;

    const num = '(-?\\d{1,3}(?:\\.\\d+)?)';
    const patterns = [
        new RegExp(`!3d${num}!4d${num}`),
        new RegExp(`@${num},\\s*${num}`),
        new RegExp(`[?&](?:q|ll|query|destination|center)=${num}\\s*,\\s*${num}`),
        new RegExp(`^${num}\\s*[,;\\s]\\s*${num}$`),
    ];

    for (const pattern of patterns) {
        const m = text.match(pattern);
        if (m) {
            const lat = Number.parseFloat(m[1]);
            const lng = Number.parseFloat(m[2]);
            if (hasPin(lat, lng)) {
                return { lat: Number(lat.toFixed(6)), lng: Number(lng.toFixed(6)) };
            }
        }
    }

    return null;
}
