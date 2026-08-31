import { ReactNode } from 'react';
import { BrandLogo } from '../common/BrandLogo';

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
          <BrandLogo linked size="md" />
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
