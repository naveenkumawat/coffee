import { NavLink } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';
import { useCartStore } from '../../stores/cartStore';
import { buildLoginRedirect } from '../../utils/navigation';

export function BottomNavigation() {
  const count = useCartStore((state) => state.count);
  const status = useAuthStore((state) => state.status);
  const isAuthenticated = status === 'authenticated';
  const items = [
    { to: '/', label: 'Home', icon: 'bi-house-door' },
    { to: '/menu', label: 'Menu', icon: 'bi-grid' },
    { to: isAuthenticated ? '/cart' : buildLoginRedirect('/cart'), label: 'Cart', icon: 'bi-bag' },
    { to: isAuthenticated ? '/orders' : buildLoginRedirect('/orders'), label: 'Orders', icon: 'bi-receipt' },
    { to: isAuthenticated ? '/account' : buildLoginRedirect('/account'), label: isAuthenticated ? 'Account' : 'Sign in', icon: 'bi-person' }
  ];

  return (
    <nav className="bottom-navigation" aria-label="Primary">
      {items.map((item) => (
        <NavLink key={item.to} to={item.to} className={({ isActive }) => `bottom-nav-link ${isActive ? 'active' : ''}`}>
          <span className="bottom-nav-icon">
            <i className={`bi ${item.icon}`}></i>
            {item.to === '/cart' && count > 0 ? <small className="bottom-nav-badge">{count}</small> : null}
          </span>
          <span>{item.label}</span>
        </NavLink>
      ))}
    </nav>
  );
}
