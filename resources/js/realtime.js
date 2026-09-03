/**
 * Shared Echo bootstrap for internal Blade panels (R1.1 + R1.5).
 *
 * Reads window.__COFFEE_REALTIME__ from the layout. No-ops when disabled
 * or credentials are missing so REST panels keep working without Reverb.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {
    countByRole,
    normalizePresenceMembers,
    renderPresenceSummary,
    shouldSoftReloadDiningPage,
    shouldSoftReloadInventoryPage,
} from './realtime/presence';
import { createEventDedupe } from './notifications/eventDedupe';

window.Pusher = Pusher;

const config = window.__COFFEE_REALTIME__;
const inventoryDedupe = createEventDedupe('inv');
const diningDedupe = createEventDedupe('dining');

if (!config?.enabled || !config.key) {
    window.__COFFEE_ECHO__ = null;
    window.__COFFEE_REALTIME_STATE__ = 'offline';
} else {
    const echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: config.authEndpoint,
        auth: {
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                Accept: 'application/json',
            },
        },
        withCredentials: true,
    });

    window.__COFFEE_ECHO__ = echo;
    window.__COFFEE_REALTIME_STATE__ = 'connecting';

    const connector = echo.connector?.pusher;
    const indicator = document.getElementById('coffee-realtime-indicator');
    let heartbeatTimer = null;
    let presenceMembers = [];

    function setState(state, label) {
        window.__COFFEE_REALTIME_STATE__ = state;
        if (!indicator) {
            return;
        }
        indicator.dataset.state = state;
        indicator.textContent = label;
        indicator.hidden = state === 'connected';
    }

    function presenceApiBase() {
        return (config.presenceApiBase || '').replace(/\/$/, '');
    }

    async function postPresence(path) {
        const base = presenceApiBase();
        if (!base) {
            return;
        }

        try {
            await fetch(`${base}${path}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '{}',
            });
        } catch {
            // Presence is advisory — never block the panel.
        }
    }

    function startHeartbeat() {
        stopHeartbeat();
        void postPresence('/heartbeat');
        heartbeatTimer = window.setInterval(() => {
            void postPresence('/heartbeat');
        }, Number(config.presenceIntervalMs) || 20000);
    }

    function stopHeartbeat() {
        if (heartbeatTimer) {
            window.clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }

    function updatePresenceUi(members) {
        presenceMembers = normalizePresenceMembers(members);
        const counts = countByRole(presenceMembers);
        window.__COFFEE_PRESENCE__ = { members: presenceMembers, counts };
        const el = document.getElementById('coffee-ops-presence');
        if (!el) {
            return;
        }
        el.hidden = false;
        el.textContent = renderPresenceSummary(counts);
        el.setAttribute('aria-label', `Staff online — ${renderPresenceSummary(counts)}`);
    }

    function bindPresence() {
        if (!config.joinPresence) {
            return;
        }

        const channel = echo.join('ops');
        channel
            .here((members) => updatePresenceUi(members))
            .joining((member) => {
                updatePresenceUi([...presenceMembers, member]);
            })
            .leaving((member) => {
                const leavingId = Number(member?.id);
                updatePresenceUi(presenceMembers.filter((row) => row.id !== leavingId));
            });

        // Echo does not always re-emit here on leave for all members; poll summary for admin/operator.
        if (config.showPresenceSummary) {
            window.setInterval(async () => {
                try {
                    const response = await fetch(`${presenceApiBase()}/summary`, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                    });
                    if (!response.ok) {
                        return;
                    }
                    const json = await response.json();
                    const roles = json?.data?.roles;
                    if (roles && typeof roles === 'object') {
                        const el = document.getElementById('coffee-ops-presence');
                        if (el) {
                            el.hidden = false;
                            el.textContent = renderPresenceSummary(roles);
                        }
                        window.__COFFEE_PRESENCE__ = { members: presenceMembers, counts: roles };
                    }
                } catch {
                    // advisory
                }
            }, 15000);
        }
    }

    function bindInventorySignal() {
        if (!config.roleChannel) {
            return;
        }

        echo.private(config.roleChannel).listen('.inventory.ops', (payload) => {
            const eventId = typeof payload?.event_id === 'string' ? payload.event_id : null;
            if (eventId && !inventoryDedupe.claim(eventId)) {
                return;
            }

            window.dispatchEvent(new CustomEvent('coffee:inventory-ops', { detail: payload }));

            if (shouldSoftReloadInventoryPage()) {
                window.setTimeout(() => {
                    window.location.reload();
                }, 350);
            }
        });
    }

    function bindDiningSignal() {
        if (!config.roleChannel) {
            return;
        }

        echo.private(config.roleChannel).listen('.dining.ops', (payload) => {
            const eventId = typeof payload?.event_id === 'string' ? payload.event_id : null;
            if (eventId && !diningDedupe.claim(eventId)) {
                return;
            }

            window.dispatchEvent(new CustomEvent('coffee:dining-ops', { detail: payload }));

            if (shouldSoftReloadDiningPage()) {
                window.setTimeout(() => {
                    window.location.reload();
                }, 400);
            }
        });
    }

    if (connector) {
        connector.connection.bind('connected', () => {
            setState('connected', 'Live');
            startHeartbeat();
        });
        connector.connection.bind('connecting', () => setState('reconnecting', 'Reconnecting…'));
        connector.connection.bind('unavailable', () => {
            stopHeartbeat();
            setState('failed', 'Realtime offline');
        });
        connector.connection.bind('failed', () => {
            stopHeartbeat();
            setState('failed', 'Realtime offline');
        });
        connector.connection.bind('disconnected', () => {
            stopHeartbeat();
            setState('disconnected', 'Realtime offline');
        });
    }

    if (config.userId) {
        echo.private(`user.${config.userId}`)
            .listen('.realtime.probe', () => {
                // Foundation probe listener — keep connection smoke-test quiet.
            })
            .listen('.operational.notification', (payload) => {
                window.dispatchEvent(new CustomEvent('coffee:operational-notification', {
                    detail: payload,
                }));
            });
    }

    if (config.roleChannel) {
        echo.private(config.roleChannel);
        bindInventorySignal();
        bindDiningSignal();
    }

    bindPresence();

    window.addEventListener('pagehide', () => {
        stopHeartbeat();
        void postPresence('/leave');
    });
}
