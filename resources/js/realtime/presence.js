/**
 * Shared staff presence + inventory live-signal helpers (R1.5).
 */

const PRESENCE_LABELS = {
    administrator: 'Admin',
    operator: 'Operator',
    barista: 'Barista',
    chef: 'Chef',
    waiter: 'Waiter',
};

export function normalizePresenceMembers(members) {
    const byUser = new Map();
    (Array.isArray(members) ? members : []).forEach((member) => {
        const id = Number(member?.id);
        const role = typeof member?.role === 'string' ? member.role : null;
        if (!Number.isFinite(id) || id <= 0 || !role) {
            return;
        }
        byUser.set(id, { id, role, label: member.label || PRESENCE_LABELS[role] || role });
    });

    return [...byUser.values()];
}

export function countByRole(members) {
    const counts = {
        administrator: 0,
        operator: 0,
        barista: 0,
        chef: 0,
        waiter: 0,
    };

    normalizePresenceMembers(members).forEach((member) => {
        if (Object.prototype.hasOwnProperty.call(counts, member.role)) {
            counts[member.role] += 1;
        }
    });

    return counts;
}

export function renderPresenceSummary(counts) {
    return ['operator', 'barista', 'chef', 'waiter', 'administrator']
        .map((role) => `${PRESENCE_LABELS[role]}: ${counts[role] ?? 0}`)
        .join(' · ');
}

export function shouldSoftReloadInventoryPage() {
    const path = window.location.pathname || '';

    return /\/(administrator|operator|barista)\/.*inventory/i.test(path)
        || /\/(administrator|operator|barista)\/.*refill/i.test(path);
}

export function shouldSoftReloadDiningPage() {
    const path = window.location.pathname || '';

    return /\/waiter\/(tables|sessions)/i.test(path)
        || /\/(administrator|operator)\/dining-sessions/i.test(path);
}
