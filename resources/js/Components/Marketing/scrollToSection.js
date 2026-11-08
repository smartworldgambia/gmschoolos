/** Scroll to an in-page section, clearing sticky navbar height. */
export function scrollToSection(hash, { behavior = 'smooth' } = {}) {
    const id = String(hash || '').replace(/^#/, '');
    if (!id) return false;

    const el = document.getElementById(id);
    if (!el) return false;

    const nav = document.querySelector('header');
    // Land content just under the sticky header (small breathing room only).
    const offset = (nav?.getBoundingClientRect().height ?? 72) + 4;
    const top = el.getBoundingClientRect().top + window.scrollY - offset;

    // Prefer instant jump for hash loads; smooth for nav clicks.
    const scrollBehavior = behavior === 'auto' || behavior === 'instant' ? 'instant' : behavior;

    const html = document.documentElement;
    const prev = html.style.scrollBehavior;
    if (scrollBehavior === 'instant') {
        html.style.scrollBehavior = 'auto';
    }

    try {
        window.scrollTo({ top: Math.max(0, top), behavior: scrollBehavior });
    } catch {
        window.scrollTo(0, Math.max(0, top));
    } finally {
        if (scrollBehavior === 'instant') {
            html.style.scrollBehavior = prev;
        }
    }

    if (window.location.hash !== `#${id}`) {
        window.history.replaceState(null, '', `#${id}`);
    }

    return true;
}

export function handleSectionNavClick(event) {
    const href = event.currentTarget.getAttribute('href') || '';
    if (!href.startsWith('#')) return;

    event.preventDefault();
    scrollToSection(href, { behavior: 'smooth' });
}
