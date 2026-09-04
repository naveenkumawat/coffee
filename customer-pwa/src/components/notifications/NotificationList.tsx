import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { OperationalNotificationItem } from '../../api/notifications';
import { isCustomerNotificationType } from '../../notifications/liveSignals';
import { formatElapsed, isActionable, sortActionRequired } from '../../notifications/normalize';
import { useNotificationStore } from '../../stores/notificationStore';
import { AppIcons } from '../../utils/icons';
import {
  notificationActionLabel,
  notificationSubjectPath,
  resolveNotificationOpenPath,
} from '../../utils/notificationActions';

interface NotificationListProps {
  compact?: boolean;
}

export function NotificationList({ compact = false }: NotificationListProps) {
  const items = useNotificationStore((state) => state.items);
  const markRead = useNotificationStore((state) => state.markRead);
  const markSeen = useNotificationStore((state) => state.markSeen);
  const acknowledge = useNotificationStore((state) => state.acknowledge);
  const navigate = useNavigate();

  useEffect(() => {
    items.forEach((item) => {
      if (!item.first_seen_at) {
        void markSeen(item.recipient_id);
      }
    });
  }, [items, markSeen]);

  const actionable = sortActionRequired(items.filter(isActionable));
  const recent = items
    .filter(
      (item) =>
        !isActionable(item) ||
        Boolean(item.read_at) ||
        Boolean(item.resolved_at) ||
        isCustomerNotificationType(item.type),
    )
    .slice()
    .sort((a, b) => Date.parse(b.created_at ?? '') - Date.parse(a.created_at ?? ''));

  function openItem(item: OperationalNotificationItem): void {
    void markRead(item.recipient_id);

    const path = resolveNotificationOpenPath(item.action_url, notificationSubjectPath(item));
    if (path) {
      navigate(path);
    }
  }

  const visibleRecent = compact ? recent.slice(0, 20) : recent.slice(0, 40);

  return (
    <div className="account-notification-list">
      {actionable.length > 0 ? (
        <section className="account-section" aria-labelledby="notifications-action-heading">
          <h2 id="notifications-action-heading" className="account-subsection-title">
            Action required
          </h2>
          {actionable.map((item) => {
            const path = resolveNotificationOpenPath(item.action_url, notificationSubjectPath(item));
            const label = notificationActionLabel(path);

            return (
              <article
                key={item.recipient_id}
                className={`account-notification-card ${item.read_at ? '' : 'is-unread'}`.trim()}
              >
                <div className="account-notification-card-icon" aria-hidden="true">
                  <i className={`bi ${AppIcons.notification}`}></i>
                </div>
                <div className="account-notification-card-body">
                  <h3>{item.title}</h3>
                  <p>{item.message}</p>
                  <small>{formatElapsed(item.created_at)}</small>
                  <div className="account-notification-card-actions">
                    {label ? (
                      <button type="button" className="btn btn-sm btn-dark rounded-pill" onClick={() => openItem(item)}>
                        {label}
                      </button>
                    ) : null}
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-dark rounded-pill"
                      onClick={() => void acknowledge(item.recipient_id)}
                    >
                      Dismiss
                    </button>
                  </div>
                </div>
              </article>
            );
          })}
        </section>
      ) : null}

      <section className="account-section" aria-labelledby="notifications-recent-heading">
        <h2 id="notifications-recent-heading" className="account-subsection-title">
          Recent
        </h2>
        {visibleRecent.length === 0 ? <p className="text-muted mb-0">No recent notifications.</p> : null}
        {visibleRecent.map((item) => {
          const path = resolveNotificationOpenPath(item.action_url, notificationSubjectPath(item));
          const label = notificationActionLabel(path);

          return (
            <article
              key={`recent-${item.recipient_id}`}
              className={`account-notification-card ${item.read_at ? '' : 'is-unread'}`.trim()}
            >
              <div className="account-notification-card-icon" aria-hidden="true">
                <i className={`bi ${AppIcons.notification}`}></i>
              </div>
              <div className="account-notification-card-body">
                <h3>{item.title}</h3>
                <p>{item.message}</p>
                <small>{formatElapsed(item.created_at)}</small>
                <div className="account-notification-card-actions">
                  {label ? (
                    <button type="button" className="btn btn-sm btn-dark rounded-pill" onClick={() => openItem(item)}>
                      {label}
                    </button>
                  ) : null}
                  {!item.read_at ? (
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-dark rounded-pill"
                      onClick={() => void markRead(item.recipient_id)}
                    >
                      Mark read
                    </button>
                  ) : null}
                </div>
              </div>
            </article>
          );
        })}
      </section>
    </div>
  );
}
