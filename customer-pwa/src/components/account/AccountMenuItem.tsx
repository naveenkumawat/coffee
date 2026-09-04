import { Link } from 'react-router-dom';
import { AppIconName, appIconClass, formatCountBadge } from '../../utils/icons';
import { NotificationBadge } from '../common/NotificationBadge';

interface AccountMenuItemProps {
  to: string;
  label: string;
  icon: AppIconName;
  meta?: string | null;
  badgeCount?: number;
  emphasis?: boolean;
}

export function AccountMenuItem({
  to,
  label,
  icon,
  meta = null,
  badgeCount = 0,
  emphasis = true,
}: AccountMenuItemProps) {
  const badgeLabel = formatCountBadge(badgeCount);
  const ariaLabel = badgeLabel ? `${label}, ${badgeLabel} unread notifications` : undefined;

  return (
    <Link
      to={to}
      className={`account-link-row ${emphasis ? 'is-emphasis' : ''}`.trim()}
      aria-label={ariaLabel}
    >
      <span className="account-link-row-main">
        <i className={`bi ${appIconClass(icon)}`} aria-hidden="true"></i>
        <strong className="account-link-row-label">{label}</strong>
      </span>
      <span className="account-link-row-trailing">
        {meta ? <small className="account-link-row-meta">{meta}</small> : null}
        <NotificationBadge count={badgeCount} />
        <i className="bi bi-chevron-right" aria-hidden="true"></i>
      </span>
    </Link>
  );
}
