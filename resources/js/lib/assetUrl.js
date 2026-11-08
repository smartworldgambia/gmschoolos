/**
 * Resolve a site-relative asset path ("/images/…", "/storage/…") against the app's base path.
 *
 * The app can live at a domain root (artisan serve, vhosts, production) or in a sub-folder
 * (XAMPP: http://localhost/gbschools/public). Stored paths stay portable ("/images/x.jpg");
 * we only prefix the base at render time. Absolute URLs, data:/blob: URIs and
 * protocol-relative URLs are returned untouched.
 */
function appBase() {
    if (typeof document === 'undefined') return '';
    const content = document.querySelector('meta[name="app-base"]')?.getAttribute('content') ?? '';
    return content.replace(/\/+$/, '');
}

export function assetUrl(path) {
    if (typeof path !== 'string' || path === '') return path;
    if (!path.startsWith('/') || path.startsWith('//')) return path;

    const base = appBase();
    if (!base || path === base || path.startsWith(`${base}/`)) return path;

    return `${base}${path}`;
}
