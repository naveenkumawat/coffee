import { formatElapsed, formatWhen, isActionable, isReminderEligible, sortActionRequired } from './normalize';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function createNotificationUi({
    store,
    onOpenDrawer,
    onCloseDrawer,
    onMarkRead,
    onAcknowledge,
    onOpenTarget,
    onSeen,
}) {
    const bellButton = document.getElementById('coffee-ops-bell');
    const unreadBadge = document.getElementById('coffee-ops-unread-badge');
    const actionBadge = document.getElementById('coffee-ops-action-badge');
    const drawer = document.getElementById('coffee-ops-drawer');
    const backdrop = document.getElementById('coffee-ops-drawer-backdrop');
    const actionList = document.getElementById('coffee-ops-action-list');
    const recentList = document.getElementById('coffee-ops-recent-list');
    const toastHost = document.getElementById('coffee-ops-toast-host');
    let lastFocused = null;
    let drawerOpen = false;

    function renderBadges(state) {
        if (unreadBadge) {
            const count = state.unreadCount;
            unreadBadge.hidden = count <= 0;
            unreadBadge.textContent = count > 99 ? '99+' : String(count);
        }

        if (actionBadge) {
            const count = state.actionRequiredCount;
            actionBadge.hidden = count <= 0;
            actionBadge.textContent = count > 99 ? '99+' : String(count);
            actionBadge.classList.toggle('is-critical', count > 0);
        }

        if (bellButton) {
            bellButton.setAttribute(
                'aria-label',
                `Notifications, ${state.unreadCount} unread, ${state.actionRequiredCount} action required`,
            );
        }
    }

    function itemCard(item, { actionable }) {
        const waiting = actionable ? formatElapsed(item.created_at) : formatWhen(item.created_at);
        const resolved = item.resolved_at ? '<span class="coffee-ops-chip">Resolved</span>' : '';
        const unread = !item.read_at ? 'is-unread' : '';
        const critical = item.priority === 'critical' ? 'is-critical' : '';

        return `
            <article class="coffee-ops-card ${unread} ${critical}" data-recipient-id="${item.recipient_id}">
                <div class="coffee-ops-card-main">
                    <h4 class="coffee-ops-card-title">${escapeHtml(item.title)}</h4>
                    <p class="coffee-ops-card-message">${escapeHtml(item.message)}</p>
                    <div class="coffee-ops-card-meta">
                        <span>${escapeHtml(waiting)}</span>
                        ${resolved}
                    </div>
                </div>
                <div class="coffee-ops-card-actions">
                    ${item.action_url ? `<button type="button" class="btn btn-sm btn-primary" data-ops-open="${item.recipient_id}">Open</button>` : ''}
                    ${actionable ? `<button type="button" class="btn btn-sm btn-light" data-ops-ack="${item.recipient_id}">Acknowledge</button>` : ''}
                    ${!item.read_at ? `<button type="button" class="btn btn-sm btn-link" data-ops-read="${item.recipient_id}">Mark read</button>` : ''}
                </div>
            </article>
        `;
    }

    function renderLists(state) {
        if (!actionList || !recentList) {
            return;
        }

        const actionable = sortActionRequired(state.items.filter(isActionable));
        const recent = state.items.filter((item) => !isActionable(item) || item.read_at || item.resolved_at);

        actionList.innerHTML = actionable.length
            ? actionable.map((item) => itemCard(item, { actionable: true })).join('')
            : '<div class="text-muted fs-7 py-6 text-center">No action required.</div>';

        recentList.innerHTML = recent.length
            ? recent.slice(0, 40).map((item) => itemCard(item, { actionable: false })).join('')
            : '<div class="text-muted fs-7 py-6 text-center">No recent notifications.</div>';
    }

    function lockScroll() {
        document.documentElement.classList.add('coffee-ops-drawer-open');
        document.body.classList.add('coffee-ops-drawer-open');
    }

    function unlockScroll() {
        document.documentElement.classList.remove('coffee-ops-drawer-open');
        document.body.classList.remove('coffee-ops-drawer-open');
    }

    function openDrawer() {
        if (!drawer) {
            return;
        }

        lastFocused = document.activeElement;
        drawer.hidden = false;
        backdrop && (backdrop.hidden = false);
        drawerOpen = true;
        lockScroll();
        onOpenDrawer?.();
        const focusable = drawer.querySelector('button, [href], [tabindex]:not([tabindex="-1"])');
        focusable?.focus?.({ preventScroll: true });

        store.getState().items.filter(isActionable).forEach((item) => {
            if (!item.first_seen_at) {
                void onSeen?.(item.recipient_id);
            }
        });
    }

    function closeDrawer() {
        if (!drawer) {
            return;
        }

        drawer.hidden = true;
        backdrop && (backdrop.hidden = true);
        drawerOpen = false;
        unlockScroll();
        onCloseDrawer?.();
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus({ preventScroll: true });
        }
    }

    /**
     * @param {import('./types').OpsNotification} item
     * @param {{ reminder?: boolean }} [opts]
     */
    function showToast(item, opts = {}) {
        if (!toastHost) {
            return;
        }

        const isAction = isReminderEligible(item) || isActionable(item);
        const toast = document.createElement('div');
        toast.className = `coffee-ops-toast ${item.priority === 'critical' ? 'is-critical' : ''} ${isAction ? 'is-action' : 'is-info'}`;
        toast.setAttribute('role', isAction ? 'alert' : 'status');
        toast.setAttribute('aria-live', isAction ? 'assertive' : 'polite');
        toast.innerHTML = `
            <div class="coffee-ops-toast-body">
                <strong>${escapeHtml(item.title)}</strong>
                <span>${escapeHtml(item.message)}</span>
                ${opts.reminder ? '<em class="coffee-ops-toast-reminder">Reminder</em>' : ''}
            </div>
            <div class="coffee-ops-toast-actions">
                ${item.action_url ? `<button type="button" class="btn btn-sm btn-primary" data-toast-open>Open</button>` : ''}
                <button type="button" class="btn btn-sm btn-light" data-toast-dismiss>Dismiss</button>
            </div>
        `;

        toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', () => toast.remove());
        toast.querySelector('[data-toast-open]')?.addEventListener('click', () => {
            void onOpenTarget?.(item);
            toast.remove();
        });

        toastHost.appendChild(toast);

        if (!item.first_seen_at) {
            void onSeen?.(item.recipient_id);
        }

        if (!isAction || item.priority !== 'critical') {
            window.setTimeout(() => toast.remove(), isAction ? 8000 : 4500);
        }
    }

    function bindListClicks(root) {
        root?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const openId = target.getAttribute('data-ops-open');
            const ackId = target.getAttribute('data-ops-ack');
            const readId = target.getAttribute('data-ops-read');
            const state = store.getState();

            if (openId) {
                const item = state.items.find((row) => String(row.recipient_id) === openId);
                if (item) {
                    void onOpenTarget?.(item);
                }
            }

            if (ackId) {
                void onAcknowledge?.(Number(ackId));
            }

            if (readId) {
                void onMarkRead?.(Number(readId));
            }
        });
    }

    bellButton?.addEventListener('click', (event) => {
        event.preventDefault();
        if (drawerOpen) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    backdrop?.addEventListener('click', closeDrawer);
    drawer?.querySelector('[data-ops-close]')?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawerOpen) {
            closeDrawer();
        }
    });

    bindListClicks(actionList);
    bindListClicks(recentList);

    store.subscribe((state) => {
        renderBadges(state);
        renderLists(state);
    });

    return {
        openDrawer,
        closeDrawer,
        showToast,
        isDrawerOpen: () => drawerOpen,
    };
}
