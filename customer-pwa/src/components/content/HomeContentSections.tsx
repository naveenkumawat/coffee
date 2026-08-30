import { Link } from 'react-router-dom';
import { buildWhatsAppUrl } from '../../utils/content';
import { WebsiteBusinessContent } from '../../types/content';

interface HomeContentSectionsProps {
  business: WebsiteBusinessContent;
}

export function HomeContentSections({ business }: HomeContentSectionsProps) {
  const whatsappUrl = buildWhatsAppUrl(
    business.whatsapp_number,
    business.name ? `Hi ${business.name}, I have a question about ordering.` : 'Hi, I have a question about ordering.',
  );

  const hasContact =
    Boolean(business.phone || business.email || business.address || business.opening_hours || whatsappUrl);

  return (
    <div className="home-secondary">
      {business.about_short ? (
        <section className="section-shell content-preview-card is-secondary">
          <div className="section-header">
            <div>
              <p className="eyebrow">About</p>
              <h2>{business.name ?? 'Our cafe'}</h2>
            </div>
            <Link to="/about" className="text-link">
              More
            </Link>
          </div>
          <p className="content-preview-text">{business.about_short}</p>
        </section>
      ) : null}

      {hasContact ? (
        <section className="section-shell content-preview-card is-secondary">
          <div className="section-header">
            <div>
              <p className="eyebrow">Visit</p>
              <h2>Find us</h2>
            </div>
            <Link to="/contact" className="text-link">
              Contact
            </Link>
          </div>
          <div className="contact-facts">
            {business.address ? <p>{business.address}</p> : null}
            {business.opening_hours ? <p className="content-preline">{business.opening_hours}</p> : null}
          </div>
          {whatsappUrl ? (
            <a href={whatsappUrl} className="btn btn-outline-dark rounded-pill w-100 mt-3" target="_blank" rel="noreferrer">
              WhatsApp the cafe
            </a>
          ) : null}
        </section>
      ) : null}

      <nav className="home-legal-links" aria-label="Site links">
        <Link to="/faq">FAQ</Link>
        <Link to="/terms">Terms</Link>
        <Link to="/privacy">Privacy</Link>
      </nav>
    </div>
  );
}
