import { Link } from 'react-router-dom';
import brandMark from '../../assets/images/svg/coffee-cup.svg';
import logo from '../../assets/images/app-logo/logo.png';
import { useAuthStore } from '../../stores/authStore';

interface HeaderProps {
  cartCount: number;
}

export function Header({ cartCount }: HeaderProps) {
  const customer = useAuthStore((state) => state.customer);
  const status = useAuthStore((state) => state.status);
  const firstName = customer?.name.split(' ')[0] ?? 'there';

  return (
    <header className="coffee-topbar">
      <div>
        <p className="eyebrow">{status === 'authenticated' ? `Welcome back, ${firstName}` : 'Good coffee, quick pickup'}</p>
        <img src={logo} alt="Coffee Cafe" className="brand-logo" />
      </div>
      <div className="topbar-actions">
        <Link to="/account" className="auth-chip">
          <i className={`bi ${status === 'authenticated' ? 'bi-person-check' : 'bi-box-arrow-in-right'}`}></i>
          {status === 'authenticated' ? 'Account' : 'Sign in'}
        </Link>
        <span className="cart-chip">
          <i className="bi bi-bag-heart"></i>
          {cartCount}
        </span>
        <span className="brand-mark">
          <img src={brandMark} alt="" />
        </span>
      </div>
    </header>
  );
}
