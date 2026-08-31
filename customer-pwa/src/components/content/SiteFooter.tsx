import { Link } from 'react-router-dom';

const FOOTER_LINKS = [
  { to: '/about', label: 'About' },
  { to: '/contact', label: 'Visit' },
  { to: '/faq', label: 'FAQ' },
  { to: '/terms', label: 'Terms' },
  { to: '/privacy', label: 'Privacy' },
] as const;

/**
 * Compact customer site links. Visit maps to the Contact page (canonical location/hours).
 */
export function SiteFooter() {
  return (
    <nav className="site-footer-links" aria-label="Cafe information">
      {FOOTER_LINKS.map((link, index) => (
        <span key={link.to} className="site-footer-item">
          {index > 0 ? <span className="site-footer-sep" aria-hidden="true">·</span> : null}
          <Link to={link.to} className="site-footer-link">
            {link.label}
          </Link>
        </span>
      ))}
    </nav>
  );
}
