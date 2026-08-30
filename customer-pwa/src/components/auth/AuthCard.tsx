import { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import logo from '../../assets/images/app-logo/logo.png';

interface AuthCardProps {
  badge: string;
  title: string;
  description: string;
  children: ReactNode;
  footer?: ReactNode;
  showBrand?: boolean;
}

export function AuthCard({
  badge,
  title,
  description,
  children,
  footer,
  showBrand = true,
}: AuthCardProps) {
  return (
    <section className="auth-card motion-enter">
      {showBrand ? (
        <div className="auth-brand">
          <Link to="/" className="auth-brand-link">
            <img src={logo} alt="Coffee Cafe" className="auth-brand-logo" />
          </Link>
        </div>
      ) : null}

      <div className="auth-card-copy">
        <span className="auth-badge">{badge}</span>
        <h2>{title}</h2>
        <p>{description}</p>
      </div>

      {children}

      {footer ? <div className="auth-links">{footer}</div> : null}
    </section>
  );
}
