/** SPA cache so the Show page can keep the inbox list beside the thread without a backend change. */

const KEY = 'schoolos.messagesInbox';

let inbox = [];

function persist(rows) {
    inbox = rows;
    try {
        sessionStorage.setItem(KEY, JSON.stringify(rows));
    } catch {
        /* private mode / quota */
    }
}

export function cacheInbox(rows) {
    if (Array.isArray(rows)) {
        persist(rows);
    }
}

export function readInbox() {
    if (inbox.length > 0) {
        return inbox;
    }
    try {
        const parsed = JSON.parse(sessionStorage.getItem(KEY) || '[]');
        if (Array.isArray(parsed) && parsed.length > 0) {
            inbox = parsed;
        }
    } catch {
        /* ignore */
    }

    return inbox;
}
