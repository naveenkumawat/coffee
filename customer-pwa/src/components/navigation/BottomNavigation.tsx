import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { NavLink, useLocation } from 'react-router-dom';
import { useOrderingContext } from '../../hooks/useOrderingContext';
import { RealtimeConnectionState } from '../../realtime/types';
import { useAuthStore } from '../../stores/authStore';
import { useCartStore } from '../../stores/cartStore';
import { useNotificationStore } from '../../stores/notificationStore';
import { cartBadgeAriaLabel, formatCartBadgeCount } from '../../utils/cartQuantity';
import { AppIcons, formatCountBadge } from '../../utils/icons';
import { buildLoginRedirect } from '../../utils/navigation';
import {
  diningMenuPath,
  diningSessionPath,
  isDiningOrderingContext,
} from '../../utils/orderingContext';
import { realtimeStatusLabel, realtimeStatusTone } from '../../utils/realtimeStatus';
import { isWaiter } from '../../utils/roles';
import { getRememberedWaiterSession } from '../../utils/waiterSession';
import { NotificationBadge } from '../common/NotificationBadge';

interface BottomNavItem {
  to: string;
  label: string;
  icon: string;
  end?: boolean;
  ariaLabel?: string;
  badgeCount?: number;
  /** Custom active matching so Dining/Menu/Cart never fight over the same route. */
  isNavActive?: (pathname: string) => boolean;
}

interface BottomNavigationProps {
  realtimeState?: RealtimeConnectionState;
}

function isDiningAddItemsPath(pathname: string, sessionId: string): boolean {
  return pathname === diningMenuPath(sessionId) || pathname.startsWith(`${diningMenuPath(sessionId)}/`);
}

function isDiningSessionSurfacePath(pathname: string, sessionId: string): boolean {
  if (isDiningAddItemsPath(pathname, sessionId)) {
    return false;
  }

  const sessionBase = diningSessionPath(sessionId);

  return pathname === sessionBase || pathname.startsWith(`${sessionBase}/`);
}

export function BottomNavigation({ realtimeState = 'idle' }: BottomNavigationProps) {
  const location = useLocation();
  const orderingContext = useOrderingContext();
  const retailCartCount = useCartStore((state) => state.count);
  const status = useAuthStore((state) => state.status);
  const customer = useAuthStore((state) => state.customer);
  const unreadCount = useNotificationStore((state) => state.unreadCount);
  const isAuthenticated = status === 'authenticated';
  const waiterMode = isAuthenticated && isWaiter(customer);
  const showRealtimeDot = isAuthenticated && realtimeState !== 'idle';
  const realtimeTone = realtimeStatusTone(realtimeState);
  const realtimeLabel = realtimeStatusLabel(realtimeState);
  const diningContext =
    !waiterMode && isDiningOrderingContext(orderingContext) ? orderingContext : null;
  const cartCount = diningContext ? (diningContext.draftItemCount ?? 0) : retailCartCount;
  const previousCount = useRef(cartCount);
  const [badgeBump, setBadgeBump] = useState(false);
  const [activeSessionId, setActiveSessionId] = useState<string | null>(null);
  const badgeLabel = formatCartBadgeCount(cartCount);
  const accountUnreadLabel = formatCountBadge(unreadCount);

  const menuTo = diningContext ? diningMenuPath(diningContext.diningSessionId) : '/menu';
  const diningTo = diningContext
    ? diningSessionPath(diningContext.diningSessionId)
    : isAuthenticated
      ? '/dining'
      : buildLoginRedirect('/dining');
  const cartTo = diningContext ? diningSessionPath(diningContext.diningSessionId) : '/cart';
  const cartAriaLabel = diningContext
    ? cartCount > 0
      ? `Cart, ${cartCount} item${cartCount === 1 ? '' : 's'} in next order`
      : 'Cart, dining next order'
    : cartBadgeAriaLabel(retailCartCount);

  useEffect(() => {
    if (cartCount > previousCount.current) {
      setBadgeBump(true);
      const timer = window.setTimeout(() => setBadgeBump(false), 280);
      previousCount.current = cartCount;

      return () => window.clearTimeout(timer);
    }

    previousCount.current = cartCount;
  }, [cartCount]);

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
                icon: AppIcons.dining,
              } satisfies BottomNavItem,
            ]
          : []),
      ]
    : [
        { to: '/', label: 'Home', icon: AppIcons.home, end: true },
        {
          to: menuTo,
          label: 'Menu',
          icon: AppIcons.menu,
          isNavActive: (pathname) => {
            if (diningContext) {
              return isDiningAddItemsPath(pathname, diningContext.diningSessionId);
            }

            return pathname === '/menu' || pathname.startsWith('/menu/');
          },
        },
        {
          to: diningTo,
          label: 'Dining',
          icon: AppIcons.dining,
          isNavActive: (pathname) => {
            if (diningContext) {
              return isDiningSessionSurfacePath(pathname, diningContext.diningSessionId);
            }

            if (pathname === '/dining') {
              return true;
            }

            // Booking/list flow only — dining add-items should highlight Menu instead.
            return (
              pathname.startsWith('/dining/') &&
              !pathname.includes('/menu')
            );
          },
        },
        {
          to: cartTo,
          label: 'Cart',
          icon: AppIcons.cart,
          ariaLabel: cartAriaLabel,
          isNavActive: (pathname) => {
            // When Cart and Dining share the dining session URL, Dining owns the active state.
            if (diningContext) {
              return false;
            }

            return pathname === '/cart' || pathname.startsWith('/cart/');
          },
        },
        {
          to: isAuthenticated ? '/account' : buildLoginRedirect('/account'),
          label: isAuthenticated ? 'Account' : 'Sign in',
          icon: AppIcons.account,
          badgeCount: isAuthenticated ? unreadCount : 0,
          ariaLabel:
            isAuthenticated && accountUnreadLabel
              ? `Account, ${accountUnreadLabel} unread notifications`
              : undefined,
          isNavActive: (pathname) => pathname === '/account' || pathname.startsWith('/account/'),
        },
      ];

  if (typeof document === 'undefined') {
    return null;
  }

  /* Portal to body so fixed positioning is never trapped by app-shell filters/transforms. */
  return createPortal(
    <nav className="bottom-navigation bottom-navigation--customer" aria-label="Primary">
      {items.map((item) => (
        <NavLink
          key={`${item.label}-${item.to}`}
          to={item.to}
          end={item.end}
          aria-label={item.ariaLabel}
          className={({ isActive }) => {
            const active = item.isNavActive ? item.isNavActive(location.pathname) : isActive;

            return `bottom-nav-link ${active ? 'active' : ''}`;
          }}
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
            {item.label === 'Account' && item.badgeCount ? (
              <NotificationBadge count={item.badgeCount} className="bottom-nav-badge" />
            ) : null}
          </span>
          <span>{item.label}</span>
        </NavLink>
      ))}
    </nav>,
    document.body,
  );
}
