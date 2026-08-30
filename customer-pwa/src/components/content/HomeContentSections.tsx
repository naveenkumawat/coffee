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

  const hasContactDetails = Boolean(
    business.phone ||
      business.email ||
      business.address ||
      business.opening_hours ||
      whatsappUrl,
  );

  return (
    <>
      {business.about_short ? (
        <section className="section-shell content-preview-card">
          <div className="section-header">
            <div>
              <p className="eyebrow">About us</p>
              <h2>{business.name ?? 'Our cafe'}</h2>
            </div>
            <Link to="/about" className="text-link">
              Read more
            </Link>
          </div>
          <p className="content-preview-text">{business.about_short}</p>
        </section>
      ) : null}

      {hasContactDetails ? (
        <section className="section-shell content-preview-card">
          <div className="section-header">
            <div>
              <p className="eyebrow">Visit & contact</p>
              <h2>Find us</h2>
            </div>
            <Link to="/contact" className="text-link">
              Contact
            </Link>
          </div>
          <div className="contact-facts">
            {business.address ? <p>{business.address}</p> : null}
            {business.opening_hours ? <p className="content-preline">{business.opening_hours}</p> : null}
            {business.phone ? (
              <a href={`tel:${business.phone}`} className="text-link">
                {business.phone}
              </a>
            ) : null}
            {business.email ? (
              <a href={`mailto:${business.email}`} className="text-link">
                {business.email}
              </a>
            ) : null}
          </div>
          {whatsappUrl ? (
            <a
              href={whatsappUrl}
              className="btn btn-success btn-lg rounded-pill w-100 mt-3"
              target="_blank"
              rel="noreferrer"
            >
              Chat on WhatsApp
            </a>
          ) : null}
        </section>
      ) : null}

      <section className="section-shell">
        <div className="site-link-grid">
          <Link to="/about">About</Link>
          <Link to="/contact">Contact</Link>
          <Link to="/faq">FAQ</Link>
          <Link to="/terms">Terms</Link>
          <Link to="/privacy">Privacy</Link>
        </div>
      </section>
    </>
  );
}
