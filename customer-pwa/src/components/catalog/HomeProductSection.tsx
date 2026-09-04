import { useEffect, useRef } from 'react';
import { HomeSection, HomeSectionProduct } from '../../types/home';
import { ProductCard } from './ProductCard';
import { ProductRail } from './ProductRail';
import { trackBehaviour } from '../../tracking/behaviourTracker';
import { stashCartAttribution } from '../../utils/cartAttributionStash';

interface HomeProductSectionProps {
  section: HomeSection;
  placement?: string;
}

export function HomeProductSection({ section, placement = 'home_rail' }: HomeProductSectionProps) {
  const impressedRequestId = useRef<string | null>(null);
  const products = section.products ?? [];

  useEffect(() => {
    const requestId = section.recommendation?.request_id;

    if (!requestId || products.length === 0) {
      return;
    }

    if (impressedRequestId.current === requestId) {
      return;
    }

    impressedRequestId.current = requestId;

    for (const product of products) {
      const attribution = product.attribution;

      if (!attribution || attribution.source_type !== 'recommendation') {
        continue;
      }

      trackBehaviour({
        event_type: 'recommendation_impression',
        product_id: product.id,
        metadata: {
          request_id: attribution.request_id,
          reason: attribution.reason,
          strategy: attribution.strategy,
          placement: attribution.placement ?? placement,
          context: section.recommendation?.context,
          section_id: section.id,
        },
        dedupe_key: `rec_impression:${requestId}:${product.id}`,
      });
    }
  }, [section, products, placement]);

  if (products.length === 0) {
    return null;
  }

  return (
    <ProductRail
      eyebrow={section.subtitle ?? undefined}
      title={section.title}
      seeAllHref="/menu"
      seeAllLabel="See all"
    >
      {products.map((product) => (
        <div key={`${section.id}-${product.id}`} className="product-rail-item" role="listitem">
          <MerchandisingProductCard product={product} sectionId={section.id} placement={placement} />
        </div>
      ))}
    </ProductRail>
  );
}

function MerchandisingProductCard({
  product,
  sectionId,
  placement,
}: {
  product: HomeSectionProduct;
  sectionId: number;
  placement: string;
}) {
  return (
    <div
      onClickCapture={() => {
        const attribution = product.attribution;

        if (attribution?.source_type === 'recommendation' && attribution.request_id) {
          stashCartAttribution(product.id, {
            source_type: 'recommendation',
            request_id: attribution.request_id,
            reason: attribution.reason ?? undefined,
            strategy: attribution.strategy ?? undefined,
            placement: attribution.placement ?? placement,
            context: attribution.strategy ?? undefined,
          });
          trackBehaviour({
            event_type: 'recommendation_clicked',
            product_id: product.id,
            metadata: {
              request_id: attribution.request_id,
              reason: attribution.reason,
              strategy: attribution.strategy,
              placement: attribution.placement ?? placement,
              section_id: sectionId,
            },
            dedupe_key: `rec_click:${attribution.request_id}:${product.id}`,
          });
        }
      }}
    >
      <ProductCard product={product} layout="rail" />
    </div>
  );
}
