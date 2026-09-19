import { useEffect, useMemo } from 'react';
import { ErrorState } from '../common/ErrorState';
import { LoadingSkeleton } from '../common/LoadingSkeleton';
import { PageHeader } from '../common/PageHeader';
import { ContentPageKey, DEFAULT_BRAND_NAME, WebsiteFaqItem } from '../../types/content';
import { parseFaqItems } from '../../utils/contentPages';
import { ContactActions } from './ContactActions';
import { FaqAccordion } from './FaqAccordion';
import { useContentStore } from '../../stores/contentStore';

const EMPTY_FAQ_ITEMS: WebsiteFaqItem[] = [];

const pageMeta: Record<ContentPageKey, { title: string; description: string }> = {
  about: {
    title: 'About',
    description: 'Our story and what we brew for.',
  },
  contact: {
    title: 'Visit',
    description: 'Address, hours, and how to reach the cafe.',
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

function looksLikeHtml(value: string): boolean {
  return /<[a-z][\s\S]*>/i.test(value);
}

export function ContentPage({ page }: ContentPageProps) {
  const fallbackMeta = pageMeta[page];
  const content = useContentStore((state) => state.content);
  const hasBootstrapped = useContentStore((state) => state.hasBootstrapped);
  const bootstrap = useContentStore((state) => state.bootstrap);

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  const cmsMeta = content?.page_meta?.[page];
  const title = cmsMeta?.title?.trim() || fallbackMeta.title;
  const description = cmsMeta?.meta_description?.trim() || fallbackMeta.description;
  const body = content?.pages[page]?.trim() ?? '';
  const business = content?.business;
  const structuredFaq = content?.faq_items ?? EMPTY_FAQ_ITEMS;
  const faqItems = useMemo(() => {
    if (page !== 'faq') {
      return [];
    }

    if (structuredFaq.length > 0) {
      return structuredFaq.map((item) => ({
        id: String(item.id),
        question: item.question,
        answer: item.answer,
      }));
    }

    return parseFaqItems(body);
  }, [body, page, structuredFaq]);
  const isLongForm = page === 'terms' || page === 'privacy';
  const unpublished = cmsMeta ? cmsMeta.is_published === false : false;
  const showEmpty = !body && page !== 'contact' && !(page === 'faq' && faqItems.length > 0);
  const isLoading = !hasBootstrapped && !content;
  const errorMessage = hasBootstrapped && !content ? 'Unable to load this page.' : null;

  return (
    <div className={`page-container content-page content-page-${page}`}>
      <PageHeader title={title} description={description} showBack />

      {isLoading ? <LoadingSkeleton cardCount={1} lines={6} /> : null}
      {errorMessage ? <ErrorState description={errorMessage} onRetry={() => window.location.reload()} /> : null}

      {!isLoading && !errorMessage ? (
        <div className="content-shell motion-enter">
          {page === 'about' ? (
            <section className="content-about-hero">
              <p className="eyebrow">{business?.name ?? DEFAULT_BRAND_NAME}</p>
              {content?.hero?.title ? <h2>{content.hero.title}</h2> : null}
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
              {looksLikeHtml(body) ? (
                <div className="content-body cms-html" dangerouslySetInnerHTML={{ __html: body }} />
              ) : (
                <div className="content-body content-preline">{body}</div>
              )}
            </section>
          ) : null}

          {(showEmpty || unpublished) && page !== 'contact' ? (
            <section className="content-section">
              <p className="content-empty">This page has not been published yet.</p>
            </section>
          ) : null}

          {page === 'contact' && body ? (
            <section className="content-section is-secondary">
              <h2 className="content-section-title">More details</h2>
              {looksLikeHtml(body) ? (
                <div className="content-body cms-html" dangerouslySetInnerHTML={{ __html: body }} />
              ) : (
                <div className="content-body content-preline">{body}</div>
              )}
            </section>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
