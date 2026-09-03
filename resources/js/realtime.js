/**
 * Shared Echo bootstrap for internal Blade panels (R1.1).
 *
 * Reads window.__COFFEE_REALTIME__ from the layout. No-ops when disabled
 * or credentials are missing so REST panels keep working without Reverb.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const config = window.__COFFEE_REALTIME__;

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

    function setState(state, label) {
        window.__COFFEE_REALTIME_STATE__ = state;
        if (!indicator) {
            return;
        }
        indicator.dataset.state = state;
        indicator.textContent = label;
        indicator.hidden = state === 'connected';
    }

    if (connector) {
        connector.connection.bind('connected', () => setState('connected', 'Live'));
        connector.connection.bind('connecting', () => setState('reconnecting', 'Reconnecting…'));
        connector.connection.bind('unavailable', () => setState('failed', 'Realtime offline'));
        connector.connection.bind('failed', () => setState('failed', 'Realtime offline'));
        connector.connection.bind('disconnected', () => setState('disconnected', 'Realtime offline'));
    }

    if (config.userId) {
        echo.private(`user.${config.userId}`).listen('.realtime.probe', () => {
            // Foundation probe listener — business handlers arrive in R1.2+.
        });
    }

    if (config.roleChannel) {
        echo.private(config.roleChannel);
    }
}
