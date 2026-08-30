import { useEffect, useRef, useState } from 'react';
import { NavLink } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';
import { useCartStore } from '../../stores/cartStore';
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
    {
      to: '/cart',
      label: 'Cart',
      icon: 'bi-bag',
      ariaLabel: cartBadgeAriaLabel(count),
    },
    { to: isAuthenticated ? '/orders' : buildLoginRedirect('/orders'), label: 'Orders', icon: 'bi-receipt' },
    {
      to: isAuthenticated ? '/account' : buildLoginRedirect('/account'),
      label: isAuthenticated ? 'Account' : 'Sign in',
      icon: 'bi-person',
    },
  ];

  return (
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
    </nav>
  );
}
