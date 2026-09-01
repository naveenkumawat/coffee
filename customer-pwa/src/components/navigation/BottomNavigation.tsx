import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { NavLink } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';
import { useCartStore } from '../../stores/cartStore';
import { useContentStore } from '../../stores/contentStore';
import { cartBadgeAriaLabel, formatCartBadgeCount } from '../../utils/cartQuantity';
import { buildLoginRedirect } from '../../utils/navigation';

interface BottomNavItem {
  to: string;
  label: string;
  icon: string;
  end?: boolean;
  ariaLabel?: string;
}

export function BottomNavigation() {
  const count = useCartStore((state) => state.count);
  const status = useAuthStore((state) => state.status);
  const isAuthenticated = status === 'authenticated';
  const content = useContentStore((state) => state.content);
  const diningEnabled = Boolean(
    content?.fulfilment?.dining_enabled ?? content?.fulfilment?.dine_in_enabled,
  );
  const previousCount = useRef(count);
  const [badgeBump, setBadgeBump] = useState(false);
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

  const items: BottomNavItem[] = [
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
