import { useEffect, useId, useLayoutEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useNotificationStore } from '../../stores/notificationStore';
import { formatElapsed, isActionable, sortActionRequired } from '../../notifications/normalize';
import { lockOverlayBackgroundScroll, unlockOverlayBackgroundScroll } from '../../utils/overlayScrollLock';

export function NotificationBell() {
  const unreadCount = useNotificationStore((state) => state.unreadCount);
  const actionRequiredCount = useNotificationStore((state) => state.actionRequiredCount);
  const setDrawerOpen = useNotificationStore((state) => state.setDrawerOpen);
  const drawerOpen = useNotificationStore((state) => state.drawerOpen);

  return (
    <button
      type="button"
      className="ops-notification-bell"
      aria-label={`Notifications, ${unreadCount} unread, ${actionRequiredCount} action required`}
      aria-expanded={drawerOpen}
      onClick={() => setDrawerOpen(!drawerOpen)}
    >
      <span aria-hidden="true">🔔</span>
      {actionRequiredCount > 0 ? (
        <span className="ops-notification-bell-action">{actionRequiredCount > 99 ? '99+' : actionRequiredCount}</span>
      ) : null}
      {unreadCount > 0 ? (
        <span className="ops-notification-bell-unread">{unreadCount > 99 ? '99+' : unreadCount}</span>
      ) : null}
    </button>
  );
}

export function NotificationDrawer() {
  const titleId = useId();
  const open = useNotificationStore((state) => state.drawerOpen);
  const items = useNotificationStore((state) => state.items);
  const setDrawerOpen = useNotificationStore((state) => state.setDrawerOpen);
  const markRead = useNotificationStore((state) => state.markRead);
  const markSeen = useNotificationStore((state) => state.markSeen);
  const acknowledge = useNotificationStore((state) => state.acknowledge);
  const navigate = useNavigate();
  const closeRef = useRef<HTMLButtonElement>(null);
  const previouslyFocused = useRef<HTMLElement | null>(null);

  useLayoutEffect(() => {
    if (!open) {
      return;
    }

    previouslyFocused.current = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;
    lockOverlayBackgroundScroll();
    closeRef.current?.focus({ preventScroll: true });

    items.filter(isActionable).forEach((item) => {
      if (!item.first_seen_at) {
        void markSeen(item.recipient_id);
      }
    });

    return () => {
      unlockOverlayBackgroundScroll();
      previouslyFocused.current?.focus({ preventScroll: true });
    };
  }, [open, items, markSeen]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        setDrawerOpen(false);
      }
    };

    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [open, setDrawerOpen]);

  if (!open) {
    return null;
  }

  const actionable = sortActionRequired(items.filter(isActionable));
  const recent = items.filter((item) => !isActionable(item) || Boolean(item.read_at) || Boolean(item.resolved_at));

  return (
    <div className="ops-notification-layer">
      <button
        type="button"
        className="ops-notification-backdrop"
        aria-label="Close notifications"
        onClick={() => setDrawerOpen(false)}
      />
      <aside className="ops-notification-drawer" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <header className="ops-notification-drawer-header">
          <h2 id={titleId}>Notifications</h2>
          <button ref={closeRef} type="button" onClick={() => setDrawerOpen(false)}>
            Close
          </button>
        </header>
        <div className="ops-notification-drawer-body">
          <h3>Action required</h3>
          {actionable.length === 0 ? <p className="ops-notification-empty">No action required.</p> : null}
          {actionable.map((item) => (
            <article key={item.recipient_id} className="ops-notification-card">
              <h4>{item.title}</h4>
              <p>{item.message}</p>
              <small>{formatElapsed(item.created_at)}</small>
              <div className="ops-notification-card-actions">
                {item.action_url ? (
                  <button
                    type="button"
                    onClick={() => {
                      void markRead(item.recipient_id);
                      setDrawerOpen(false);
                      const url = item.action_url ?? '';
                      if (url.startsWith('http')) {
                        window.location.assign(url);
                      } else {
                        navigate(url.replace(/^\/waiter/, '/waiter') || '/waiter');
                      }
                    }}
                  >
                    Open
                  </button>
                ) : null}
                <button type="button" onClick={() => void acknowledge(item.recipient_id)}>
                  Acknowledge
                </button>
              </div>
            </article>
          ))}

          <h3>Recent</h3>
          {recent.length === 0 ? <p className="ops-notification-empty">No recent notifications.</p> : null}
          {recent.slice(0, 30).map((item) => (
            <article key={`recent-${item.recipient_id}`} className="ops-notification-card">
              <h4>{item.title}</h4>
              <p>{item.message}</p>
              {!item.read_at ? (
                <button type="button" onClick={() => void markRead(item.recipient_id)}>
                  Mark read
                </button>
              ) : null}
            </article>
          ))}
        </div>
      </aside>
    </div>
  );
}
