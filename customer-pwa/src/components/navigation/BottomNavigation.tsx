import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { NavLink, useLocation } from 'react-router-dom';
import { RealtimeConnectionState } from '../../realtime/types';
import { useAuthStore } from '../../stores/authStore';
import { useCartStore } from '../../stores/cartStore';
import { useContentStore } from '../../stores/contentStore';
import { cartBadgeAriaLabel, formatCartBadgeCount } from '../../utils/cartQuantity';
import { buildLoginRedirect } from '../../utils/navigation';
import { realtimeStatusLabel, realtimeStatusTone } from '../../utils/realtimeStatus';
import { isWaiter } from '../../utils/roles';
import { getRememberedWaiterSession } from '../../utils/waiterSession';

interface BottomNavItem {
  to: string;
  label: string;
  icon: string;
  end?: boolean;
  ariaLabel?: string;
}

interface BottomNavigationProps {
  realtimeState?: RealtimeConnectionState;
}

export function BottomNavigation({ realtimeState = 'idle' }: BottomNavigationProps) {
  const location = useLocation();
  const count = useCartStore((state) => state.count);
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const isAuthenticated = status === 'authenticated';
  const waiterMode = isAuthenticated && isWaiter(customer);
  const showRealtimeDot = isAuthenticated && realtimeState !== 'idle';
  const realtimeTone = realtimeStatusTone(realtimeState);
  const realtimeLabel = realtimeStatusLabel(realtimeState);
  const content = useContentStore((state) => state.content);
  const diningEnabled = Boolean(
    content?.fulfilment?.dining_enabled ?? content?.fulfilment?.dine_in_enabled,
  );
  const previousCount = useRef(count);
  const [badgeBump, setBadgeBump] = useState(false);
  const [activeSessionId, setActiveSessionId] = useState<string | null>(null);
  const badgeLabel = formatCartBadgeCount(count);

  useEffect(() => {
    if (count > previousCount.current) {
      setBadgeBump(true);
      const timer = window.setTimeout(() => setBadgeBump(false), 280);
      previousCount.current = count;

      return () => window.clearTimeout(timer);
    }

    previousCount.current = count;
  }, [count]);

  useEffect(() => {
    if (!waiterMode) {
      setActiveSessionId(null);

      return;
    }

    setActiveSessionId(getRememberedWaiterSession());
  }, [waiterMode, location.pathname]);

  const items: BottomNavItem[] = waiterMode
    ? [
        { to: '/waiter', label: 'Tables', icon: 'bi-grid-3x3-gap', end: true },
        ...(activeSessionId
          ? [
              {
                to: `/waiter/sessions/${activeSessionId}`,
                label: 'Session',
                icon: 'bi-cup-hot',
              } satisfies BottomNavItem,
            ]
          : []),
      ]
    : [
        { to: '/', label: 'Home', icon: 'bi-house-door', end: true },
        { to: '/menu', label: 'Menu', icon: 'bi-grid' },
        ...(diningEnabled
          ? [
              {
                to: isAuthenticated ? '/dining' : buildLoginRedirect('/dining'),
                label: 'Dining',
                icon: 'bi-cup-hot',
              } satisfies BottomNavItem,
            ]
          : []),
        {
          to: '/cart',
          label: 'Cart',
          icon: 'bi-bag',
          ariaLabel: cartBadgeAriaLabel(count),
        },
        {
          to: isAuthenticated ? '/account' : buildLoginRedirect('/account'),
          label: isAuthenticated ? 'Account' : 'Sign in',
          icon: 'bi-person',
        },
      ];

  if (typeof document === 'undefined') {
    return null;
  }

  /* Portal to body so fixed positioning is never trapped by app-shell filters/transforms. */
  return createPortal(
    <nav className="bottom-navigation" aria-label="Primary">
      {items.map((item) => (
        <NavLink
          key={item.label}
          to={item.to}
          end={item.end}
          aria-label={item.ariaLabel}
          className={({ isActive }) => `bottom-nav-link ${isActive ? 'active' : ''}`}
        >
          <span className="bottom-nav-icon">
            <i className={`bi ${item.icon}`} aria-hidden="true"></i>
            {showRealtimeDot && (item.label === 'Home' || item.label === 'Tables') ? (
              <span
                className={`bottom-nav-realtime-dot is-${realtimeTone}`}
                title={realtimeLabel}
                aria-label={realtimeLabel}
                role="status"
              />
            ) : null}
            {item.label === 'Cart' && badgeLabel ? (
              <small className={`bottom-nav-badge ${badgeBump ? 'is-bump' : ''}`} aria-hidden="true">
                {badgeLabel}
              </small>
            ) : null}
          </span>
          <span>{item.label}</span>
        </NavLink>
      ))}
    </nav>,
    document.body,
  );
}
