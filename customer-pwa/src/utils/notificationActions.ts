import { OperationalNotificationItem } from '../api/notifications';

/**
 * Resolve a safe in-app path from notification action metadata.
 * Returns null when there is no customer-navigable destination.
 */
export function resolveNotificationOpenPath(
  actionUrl: string | null | undefined,
  fallbackSubjectPath: string | null,
): string | null {
  if (actionUrl) {
    if (
      actionUrl.startsWith('/orders') ||
      actionUrl.startsWith('/dining') ||
      actionUrl.startsWith('/account') ||
      actionUrl.startsWith('/waiter')
    ) {
      return actionUrl;
    }

    try {
      const parsed = new URL(actionUrl, window.location.origin);
      if (parsed.origin === window.location.origin) {
        if (
          parsed.pathname.startsWith('/orders') ||
          parsed.pathname.startsWith('/dining') ||
          parsed.pathname.startsWith('/account') ||
          parsed.pathname.startsWith('/waiter')
        ) {
          return `${parsed.pathname}${parsed.search}`;
        }
      }
    } catch {
      // fall through
    }
  }

  return fallbackSubjectPath;
}

export function notificationSubjectPath(item: OperationalNotificationItem): string | null {
  if (item.subject?.type === 'Order') {
    return `/orders/${item.subject.id}`;
  }

  if (item.subject?.type === 'DiningSession') {
    return `/dining/sessions/${item.subject.id}`;
  }

  return null;
}

export function notificationActionLabel(path: string | null): string | null {
  if (!path) {
    return null;
  }

  if (path.startsWith('/orders')) {
    return 'View Order';
  }

  if (path.includes('payment') || path.includes('confirmation')) {
    return 'Review Payment';
  }

  if (path.startsWith('/account/loyalty') || path.startsWith('/account/rewards')) {
    return 'View Rewards';
  }

  if (path.startsWith('/account/referral')) {
    return 'View Referral';
  }

  if (path.startsWith('/dining')) {
    return 'View Dining';
  }

  return 'Open';
}
