import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { NotificationList } from '../components/notifications/NotificationList';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useNotificationStore } from '../stores/notificationStore';
import { AppIcons } from '../utils/icons';

export function AccountNotificationsPage() {
  const status = useNotificationStore((state) => state.status);
  const unreadCount = useNotificationStore((state) => state.unreadCount);
  const items = useNotificationStore((state) => state.items);
  const sync = useNotificationStore((state) => state.sync);
  const markRead = useNotificationStore((state) => state.markRead);
  const [isMarkingAll, setIsMarkingAll] = useState(false);

  useEffect(() => {
    void sync();
  }, [sync]);

  async function handleMarkAllRead(): Promise<void> {
    const unread = items.filter((item) => !item.read_at);
    if (unread.length === 0 || isMarkingAll) {
      return;
    }

    setIsMarkingAll(true);

    try {
      await Promise.all(unread.map((item) => markRead(item.recipient_id)));
    } finally {
      setIsMarkingAll(false);
    }
  }

  return (
    <div className="page-container account-notifications-page">
      <header className="account-notifications-header">
        <div>
          <Link to="/account" className="link-button">
            ← Account
          </Link>
          <h1>Notifications</h1>
          <p className="text-muted mb-0">
            {unreadCount > 0
              ? `${unreadCount > 99 ? '99+' : unreadCount} unread`
              : 'You’re all caught up'}
          </p>
        </div>
        {unreadCount > 0 ? (
          <button
            type="button"
            className="btn btn-sm btn-outline-dark rounded-pill"
            disabled={isMarkingAll}
            onClick={() => void handleMarkAllRead()}
          >
            {isMarkingAll ? 'Updating…' : 'Mark all read'}
          </button>
        ) : (
          <span className="account-notifications-header-icon" aria-hidden="true">
            <i className={`bi ${AppIcons.notification}`}></i>
          </span>
        )}
      </header>

      {status === 'loading' && items.length === 0 ? <LoadingSkeleton cardCount={2} lines={3} /> : <NotificationList />}
    </div>
  );
}
