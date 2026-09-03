/**
 * Shared operational notification REST client for internal Blade panels (R1.2).
 * Uses same /api/v1/notifications endpoints as the PWA. No bell UI yet.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        ?? window.__COFFEE_REALTIME__?.csrfToken
        ?? '';
}

async function request(path, options = {}) {
    const response = await fetch(path, {
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers || {}),
        },
        ...options,
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(payload?.message || 'Notification request failed.');
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

const apiBase = '/api/v1/notifications';

window.__COFFEE_NOTIFICATIONS__ = {
    async list(limit = 30) {
        return request(`${apiBase}?limit=${encodeURIComponent(String(limit))}`);
    },
    async actionRequired(limit = 30) {
        return request(`${apiBase}/action-required?limit=${encodeURIComponent(String(limit))}`);
    },
    async delivered(recipientId) {
        return request(`${apiBase}/${recipientId}/delivered`, { method: 'POST', body: '{}' });
    },
    async seen(recipientId) {
        return request(`${apiBase}/${recipientId}/seen`, { method: 'POST', body: '{}' });
    },
    async read(recipientId) {
        return request(`${apiBase}/${recipientId}/read`, { method: 'POST', body: '{}' });
    },
    async acknowledge(recipientId) {
        return request(`${apiBase}/${recipientId}/acknowledge`, { method: 'POST', body: '{}' });
    },
};
