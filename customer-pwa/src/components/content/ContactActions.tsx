import { WebsiteBusinessContent } from '../../types/content';
import { buildWhatsAppUrl } from '../../utils/content';

interface ContactActionsProps {
  business: WebsiteBusinessContent;
}

export function ContactActions({ business }: ContactActionsProps) {
  const whatsappUrl = buildWhatsAppUrl(
    business.whatsapp_number,
    business.name ? `Hi ${business.name}, I have a question.` : 'Hi, I have a question about ordering.',
  );

  const hasAny = Boolean(
    business.phone || business.email || business.address || business.opening_hours || whatsappUrl,
  );

  if (!hasAny) {
    return null;
  }

  return (
    <div className="contact-actions">
      {business.name ? <p className="contact-actions-name">{business.name}</p> : null}

      {business.address ? (
        <div className="contact-action-row">
          <span className="contact-action-label">Address</span>
          <p>{business.address}</p>
        </div>
      ) : null}

      {business.opening_hours ? (
        <div className="contact-action-row">
          <span className="contact-action-label">Hours</span>
          <p className="content-preline">{business.opening_hours}</p>
        </div>
      ) : null}

      <div className="contact-action-buttons">
        {business.phone ? (
          <a href={`tel:${business.phone}`} className="btn btn-outline-dark rounded-pill">
            <i className="bi bi-telephone" aria-hidden="true"></i>
            Call {business.phone}
          </a>
        ) : null}
        {business.email ? (
          <a href={`mailto:${business.email}`} className="btn btn-outline-dark rounded-pill">
            <i className="bi bi-envelope" aria-hidden="true"></i>
            Email
          </a>
        ) : null}
        {whatsappUrl ? (
          <a href={whatsappUrl} className="btn btn-primary rounded-pill" target="_blank" rel="noreferrer">
            <i className="bi bi-whatsapp" aria-hidden="true"></i>
            WhatsApp
          </a>
        ) : null}
      </div>
    </div>
  );
}
