import { NavLink } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';
import { useCartStore } from '../../stores/cartStore';
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
  const items: BottomNavItem[] = [
    { to: '/', label: 'Home', icon: 'bi-house-door', end: true },
    { to: '/menu', label: 'Menu', icon: 'bi-grid' },
    {
      to: isAuthenticated ? '/cart' : buildLoginRedirect('/cart'),
      label: 'Cart',
      icon: 'bi-bag',
      ariaLabel: count > 0 ? `Cart, ${count} items` : 'Cart'
    },
    { to: isAuthenticated ? '/orders' : buildLoginRedirect('/orders'), label: 'Orders', icon: 'bi-receipt' },
    {
      to: isAuthenticated ? '/account' : buildLoginRedirect('/account'),
      label: isAuthenticated ? 'Account' : 'Sign in',
      icon: 'bi-person'
    }
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
            {item.label === 'Cart' && count > 0 ? (
              <small className="bottom-nav-badge" aria-hidden="true">
                {count}
              </small>
            ) : null}
          </span>
          <span>{item.label}</span>
        </NavLink>
      ))}
    </nav>
  );
}
