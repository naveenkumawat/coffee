import { ReactNode } from 'react';
import { Link } from 'react-router-dom';

interface ProductRailProps {
  eyebrow?: string;
  title: string;
  seeAllHref?: string;
  seeAllLabel?: string;
  children: ReactNode;
}

export function ProductRail({
  eyebrow,
  title,
  seeAllHref,
  seeAllLabel = 'See all',
  children,
}: ProductRailProps) {
  return (
    <section className="section-shell product-rail-section">
      <div className="section-header">
        <div>
          {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
          <h2>{title}</h2>
        </div>
        {seeAllHref ? (
          <Link to={seeAllHref} className="text-link">
            {seeAllLabel}
          </Link>
        ) : null}
      </div>
      <div className="product-rail" role="list">
        {children}
      </div>
    </section>
  );
}
