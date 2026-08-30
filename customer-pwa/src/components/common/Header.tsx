import { Link } from 'react-router-dom';
import brandMark from '../../assets/images/svg/coffee-cup.svg';
import logo from '../../assets/images/app-logo/logo.png';
import { useAuthStore } from '../../stores/authStore';
import { buildLoginRedirect } from '../../utils/navigation';

interface HeaderProps {
  cartCount: number;
}

export function Header({ cartCount }: HeaderProps) {
  const customer = useAuthStore((state) => state.customer);
  const status = useAuthStore((state) => state.status);
  const firstName = customer?.name.split(' ')[0] ?? 'there';
  const favouritesHref = status === 'authenticated' ? '/favourites' : buildLoginRedirect('/favourites');

  return (
    <header className="coffee-topbar">
      <div>
        <p className="eyebrow">{status === 'authenticated' ? `Welcome back, ${firstName}` : 'Good coffee, quick pickup'}</p>
        <img src={logo} alt="Coffee Cafe" className="brand-logo" />
      </div>
      <div className="topbar-actions">
        <Link to={favouritesHref} className="auth-chip" aria-label="Favourites">
          <i className="bi bi-heart"></i>
          Saved
        </Link>
        <Link to={status === 'authenticated' ? '/account' : buildLoginRedirect('/account')} className="auth-chip">
          <i className={`bi ${status === 'authenticated' ? 'bi-person-check' : 'bi-box-arrow-in-right'}`}></i>
          {status === 'authenticated' ? 'Account' : 'Sign in'}
        </Link>
        <Link
          to={status === 'authenticated' ? '/cart' : buildLoginRedirect('/cart')}
          className="cart-chip"
          aria-label={cartCount > 0 ? `Cart, ${cartCount} items` : 'Cart'}
        >
          <i className="bi bi-bag-heart" aria-hidden="true"></i>
          <span aria-hidden="true">{cartCount}</span>
        </Link>
        <span className="brand-mark" aria-hidden="true">
          <img src={brandMark} alt="" />
        </span>
      </div>
    </header>
  );
}
