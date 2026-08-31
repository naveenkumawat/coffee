import { Link } from 'react-router-dom';
import { BrandLogo } from './BrandLogo';
import { useAuthStore } from '../../stores/authStore';

export function Header() {
  const customer = useAuthStore((state) => state.customer);
  const status = useAuthStore((state) => state.status);
  const firstName = customer?.name.split(' ')[0] ?? null;

  return (
    <header className="coffee-topbar coffee-topbar-slim">
      <div className="topbar-brand">
        <BrandLogo linked size="sm" />
        <p className="eyebrow">
          {status === 'authenticated' && firstName
            ? `Welcome back, ${firstName}`
            : 'Order ahead · Takeaway or delivery'}
        </p>
      </div>
      <Link to="/menu" className="btn btn-primary btn-sm rounded-pill topbar-order-cta">
        Order
      </Link>
    </header>
  );
}
