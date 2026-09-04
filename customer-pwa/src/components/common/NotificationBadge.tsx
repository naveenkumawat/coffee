import { formatCountBadge } from '../../utils/icons';

interface NotificationBadgeProps {
  count: number;
  className?: string;
}

export function NotificationBadge({ count, className = '' }: NotificationBadgeProps) {
  const label = formatCountBadge(count);

  if (!label) {
    return null;
  }

  return (
    <small className={`notification-count-badge ${className}`.trim()} aria-hidden="true">
      {label}
    </small>
  );
}
