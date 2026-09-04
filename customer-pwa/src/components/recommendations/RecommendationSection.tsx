import { useEffect, useMemo, useRef, useState } from 'react';
import {
  fetchRecommendations,
  RecommendationContext,
  RecommendationItem,
  RecommendationPayload,
} from '../../api/recommendations';
import { ProductCard } from '../catalog/ProductCard';
import { trackBehaviour } from '../../tracking/behaviourTracker';
import { stashCartAttribution } from '../../utils/cartAttributionStash';
import { getOrCreateVisitorId } from '../../utils/visitorId';
import { recommendationReasonLabel } from '../../utils/recommendationLabels';

interface RecommendationSectionProps {
  context: RecommendationContext;
  title?: string;
  productId?: number;
  categoryId?: number;
  cartProductIds?: number[];
  excludeProductIds?: number[];
  limit?: number;
  placement: string;
}

export function RecommendationSection({
  context,
  title,
  productId,
  categoryId,
  cartProductIds = [],
  excludeProductIds = [],
  limit = 8,
  placement,
}: RecommendationSectionProps) {
  const [payload, setPayload] = useState<RecommendationPayload | null>(null);
  const impressedRequestId = useRef<string | null>(null);

  const cartKey = useMemo(() => cartProductIds.slice().sort((a, b) => a - b).join(','), [cartProductIds]);
  const excludeKey = useMemo(
    () => excludeProductIds.slice().sort((a, b) => a - b).join(','),
    [excludeProductIds],
  );

  useEffect(() => {
    let cancelled = false;

    async function load(): Promise<void> {
      try {
        const response = await fetchRecommendations({
          context,
          visitor_key: getOrCreateVisitorId(),
          product_id: productId,
          category_id: categoryId,
          cart_product_ids: cartProductIds,
          exclude_product_ids: excludeProductIds,
          limit,
        });

        if (!cancelled) {
          setPayload(response.data);
        }
      } catch {
        if (!cancelled) {
          setPayload(null);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
    // Intentionally depend on serialized cart/exclude keys to avoid rerender spam.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [context, productId, categoryId, cartKey, excludeKey, limit]);

  useEffect(() => {
    if (!payload?.items?.length) {
      return;
    }

    if (impressedRequestId.current === payload.request_id) {
      return;
    }

    impressedRequestId.current = payload.request_id;

    for (const item of payload.items) {
      trackBehaviour({
        event_type: 'recommendation_impression',
        product_id: item.product.id,
        metadata: {
          request_id: item.request_id,
          reason: item.reason,
          strategy: item.strategy,
          placement,
          context: payload.context,
        },
        dedupe_key: `rec_impression:${payload.request_id}:${item.product.id}`,
      });
    }
  }, [payload, placement]);

  if (!payload?.items?.length) {
    return null;
  }

  const heading = title ?? recommendationReasonLabel(payload.items[0]?.reason ?? 'based_on_your_interests');

  return (
    <section className="section-shell recommendation-section" data-placement={placement}>
      <div className="section-header">
        <div>
          <h2>{heading}</h2>
        </div>
      </div>
      <div className="product-rail">
        {payload.items.map((item) => (
          <div key={`${payload.request_id}-${item.product.id}`} className="product-rail-item">
            <RecommendationProductCard item={item} placement={placement} />
          </div>
        ))}
      </div>
    </section>
  );
}

function RecommendationProductCard({
  item,
  placement,
}: {
  item: RecommendationItem;
  placement: string;
}) {
  return (
    <div
      onClickCapture={() => {
        stashCartAttribution(item.product.id, {
          source_type: 'recommendation',
          request_id: item.request_id,
          reason: item.reason,
          strategy: item.strategy,
          placement,
          context: item.strategy,
        });
        trackBehaviour({
          event_type: 'recommendation_clicked',
          product_id: item.product.id,
          metadata: {
            request_id: item.request_id,
            reason: item.reason,
            strategy: item.strategy,
            placement,
          },
          dedupe_key: `rec_click:${item.request_id}:${item.product.id}`,
        });
      }}
    >
      <ProductCard product={item.product} layout="rail" />
      <p className="recommendation-reason">
        {recommendationReasonLabel(item.reason)}
      </p>
    </div>
  );
}
