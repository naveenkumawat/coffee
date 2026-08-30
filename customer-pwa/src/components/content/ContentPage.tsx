import { useEffect, useState } from 'react';
import { fetchWebsiteContent } from '../../api/content';
import { ApiError } from '../../api/client';
import { ErrorState } from '../common/ErrorState';
import { LoadingSkeleton } from '../common/LoadingSkeleton';
import { PageHeader } from '../common/PageHeader';
import { ContentPageKey, WebsiteContent } from '../../types/content';
import { buildWhatsAppUrl } from '../../utils/content';

const pageMeta: Record<ContentPageKey, { title: string; description: string }> = {
  about: {
    title: 'About',
    description: 'Our story and what we brew for.',
  },
  contact: {
    title: 'Contact',
    description: 'Reach the cafe for orders and pickup questions.',
  },
  faq: {
    title: 'FAQ',
    description: 'Common answers for ordering and pickup.',
  },
  terms: {
    title: 'Terms',
    description: 'Terms that apply to orders placed in this app.',
  },
  privacy: {
    title: 'Privacy',
    description: 'How customer information is handled.',
  },
};

interface ContentPageProps {
  page: ContentPageKey;
}

export function ContentPage({ page }: ContentPageProps) {
  const meta = pageMeta[page];
  const [content, setContent] = useState<WebsiteContent | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    async function load(): Promise<void> {
      setIsLoading(true);
      setErrorMessage(null);

      try {
        const response = await fetchWebsiteContent();
        setContent(response.data);
      } catch (error) {
        setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load this page.');
      } finally {
        setIsLoading(false);
      }
    }

    void load();
  }, [page]);

  const body = content?.pages[page]?.trim() ?? '';
  const business = content?.business;
  const whatsappUrl = buildWhatsAppUrl(business?.whatsapp_number);

  return (
    <div className="page-container">
      <PageHeader title={meta.title} description={meta.description} showBack />

      {isLoading ? <LoadingSkeleton cardCount={1} lines={6} /> : null}
      {errorMessage ? <ErrorState description={errorMessage} onRetry={() => window.location.reload()} /> : null}

      {!isLoading && !errorMessage ? (
        <section className="section-shell content-page-card">
          {body ? <div className="content-body content-preline">{body}</div> : (
            <p className="content-empty">This page has not been published yet.</p>
          )}

          {page === 'contact' && business ? (
            <div className="contact-facts mt-4">
              {business.name ? <p className="fw-semibold">{business.name}</p> : null}
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
            </div>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}
