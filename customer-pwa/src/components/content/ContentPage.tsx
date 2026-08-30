import { useEffect, useMemo, useState } from 'react';
import { fetchWebsiteContent } from '../../api/content';
import { ApiError } from '../../api/client';
import { ErrorState } from '../common/ErrorState';
import { LoadingSkeleton } from '../common/LoadingSkeleton';
import { PageHeader } from '../common/PageHeader';
import { ContentPageKey, WebsiteContent } from '../../types/content';
import { parseFaqItems } from '../../utils/contentPages';
import { ContactActions } from './ContactActions';
import { FaqAccordion } from './FaqAccordion';

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
  const faqItems = useMemo(() => (page === 'faq' ? parseFaqItems(body) : []), [body, page]);
  const isLongForm = page === 'terms' || page === 'privacy';

  return (
    <div className={`page-container content-page content-page-${page}`}>
      <PageHeader title={meta.title} description={meta.description} showBack />

      {isLoading ? <LoadingSkeleton cardCount={1} lines={6} /> : null}
      {errorMessage ? <ErrorState description={errorMessage} onRetry={() => window.location.reload()} /> : null}

      {!isLoading && !errorMessage ? (
        <div className="content-shell motion-enter">
          {page === 'about' ? (
            <section className="content-about-hero">
              <p className="eyebrow">{business?.name ?? 'Coffee Cafe'}</p>
              <h2>Crafted for pickup</h2>
              {business?.about_short ? <p>{business.about_short}</p> : null}
            </section>
          ) : null}

          {page === 'contact' && business ? <ContactActions business={business} /> : null}

          {page === 'faq' && faqItems.length > 0 ? (
            <section className="content-section" aria-label="Frequently asked questions">
              <FaqAccordion items={faqItems} />
            </section>
          ) : null}

          {body && !(page === 'faq' && faqItems.length > 0) ? (
            <section
              className={`content-section ${isLongForm ? 'is-longform' : ''} ${page === 'about' ? 'is-story' : ''}`.trim()}
            >
              <div className="content-body content-preline">{body}</div>
            </section>
          ) : null}

          {!body && page !== 'contact' && !(page === 'faq' && faqItems.length > 0) ? (
            <section className="content-section">
              <p className="content-empty">This page has not been published yet.</p>
            </section>
          ) : null}

          {page === 'contact' && body ? (
            <section className="content-section is-secondary">
              <h2 className="content-section-title">More details</h2>
              <div className="content-body content-preline">{body}</div>
            </section>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
