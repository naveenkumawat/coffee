import { Link } from 'react-router-dom';
import { selectBrandName, selectSocialLinks, useContentStore } from '../../stores/contentStore';
import { WebsiteSocialLink } from '../../types/content';
import { SocialIcon } from './socialIcons';

const FOOTER_LINKS = [
  { to: '/about', label: 'About' },
  { to: '/contact', label: 'Visit' },
  { to: '/faq', label: 'FAQ' },
  { to: '/terms', label: 'Terms' },
  { to: '/privacy', label: 'Privacy' },
] as const;

/**
 * Compact customer site links + dynamic social icons from Website content API.
 */
export function SiteFooter() {
  const socialLinks = useContentStore((state) => selectSocialLinks(state.content));
  const brandName = useContentStore((state) => selectBrandName(state.content));

  return (
    <footer className="site-footer">
      {socialLinks.length > 0 ? (
        <nav className="site-footer-social" aria-label={`${brandName} on social media`}>
          {socialLinks.map((link) => (
            <SocialLinkButton key={`${link.icon_key}-${link.url}`} link={link} />
          ))}
        </nav>
      ) : null}

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
    </footer>
  );
}

function SocialLinkButton({ link }: { link: WebsiteSocialLink }) {
  return (
    <a
      href={link.url}
      className="site-footer-social-link"
      target="_blank"
      rel="noopener noreferrer"
      aria-label={link.label}
      title={link.label}
    >
      <SocialIcon iconKey={link.icon_key} />
    </a>
  );
}
