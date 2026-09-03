import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import {
  EligibleCampaign,
  fetchEligibleCampaign,
  CampaignPlacement,
} from '../../api/campaigns';
import { trackBehaviour } from '../../tracking/behaviourTracker';
import { useCartStore } from '../../stores/cartStore';
import { getOrCreateCampaignSessionKey } from '../../utils/campaignSession';
import { getOrCreateVisitorId } from '../../utils/visitorId';
import { CampaignPopupModal } from './CampaignPopupModal';

function placementFromPath(pathname: string, searchParams: URLSearchParams): {
  placement: CampaignPlacement;
  productId?: number;
  categoryId?: number;
} {
  if (pathname === '/' || pathname === '') {
    return { placement: 'home' };
  }

  if (pathname.startsWith('/menu/')) {
    const id = Number(pathname.split('/')[2]);

    return {
      placement: 'product_detail',
      productId: Number.isFinite(id) ? id : undefined,
    };
  }

  if (pathname.startsWith('/menu')) {
    const category = Number(searchParams.get('category') ?? '');

    return {
      placement: Number.isFinite(category) && category > 0 ? 'category' : 'menu',
      categoryId: Number.isFinite(category) && category > 0 ? category : undefined,
    };
  }

  if (pathname.startsWith('/cart')) {
    return { placement: 'cart' };
  }

  if (pathname.startsWith('/checkout')) {
    return { placement: 'checkout' };
  }

  if (pathname.includes('/orders/') && pathname.includes('confirmation')) {
    return { placement: 'order_success' };
  }

  if (pathname.startsWith('/orders/') && searchParams.get('placed') === '1') {
    return { placement: 'order_success' };
  }

  return { placement: 'global' };
}

export function CampaignPopupController() {
  const location = useLocation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const cart = useCartStore((state) => state.cart);
  const [campaign, setCampaign] = useState<EligibleCampaign | null>(null);
  const [open, setOpen] = useState(false);
  const impressedRequestId = useRef<string | null>(null);
  const productViews = useRef(0);
  const sessionKey = useMemo(() => getOrCreateCampaignSessionKey(), []);

  const routeContext = useMemo(
    () => placementFromPath(location.pathname, searchParams),
    [location.pathname, searchParams],
  );

  const cartProductIds = useMemo(
    () =>
      (cart?.items ?? [])
        .map((item) => item.product?.id)
        .filter((id): id is number => typeof id === 'number'),
    [cart?.items],
  );

  const trackCampaign = useCallback(
    (eventType: 'campaign_impression' | 'campaign_clicked' | 'campaign_dismissed', current: EligibleCampaign) => {
      trackBehaviour({
        event_type: eventType,
        metadata: {
          campaign_id: current.id,
          request_id: current.request_id,
          placement: routeContext.placement,
          cta_type: current.cta.type,
          attribution_key: current.attribution_key ?? undefined,
          session_key: sessionKey,
        },
        dedupe_key: `${eventType}:${current.request_id}:${current.id}`,
      });
    },
    [routeContext.placement, sessionKey],
  );

  useEffect(() => {
    if (location.pathname.startsWith('/menu/')) {
      productViews.current += 1;
    }
  }, [location.pathname]);

  useEffect(() => {
    let cancelled = false;
    let delayTimer: number | undefined;
    let scrollHandler: (() => void) | undefined;

    setOpen(false);
    setCampaign(null);

    // Never interrupt essential checkout/payment actions.
    if (routeContext.placement === 'checkout') {
      return;
    }

    async function load(): Promise<void> {
      try {
        const response = await fetchEligibleCampaign({
          placement: routeContext.placement,
          visitor_key: getOrCreateVisitorId(),
          session_key: sessionKey,
          product_id: routeContext.productId,
          category_id: routeContext.categoryId,
          cart_product_ids: cartProductIds,
          surface: 'popup',
        });

        if (cancelled || !response.data.campaign) {
          return;
        }

        const next = response.data.campaign;
        setCampaign(next);

        const trigger = next.trigger ?? { type: 'immediate' };

        const present = (): void => {
          if (cancelled) {
            return;
          }

          setOpen(true);

          if (impressedRequestId.current !== next.request_id) {
            impressedRequestId.current = next.request_id;
            trackCampaign('campaign_impression', next);
          }
        };

        if (trigger.type === 'delay') {
          delayTimer = window.setTimeout(present, Math.max(0, trigger.delay_ms ?? 0));

          return;
        }

        if (trigger.type === 'scroll') {
          const threshold = Math.max(10, Math.min(100, trigger.scroll_percent ?? 50)) / 100;
          scrollHandler = (): void => {
            const doc = document.documentElement;
            const max = doc.scrollHeight - window.innerHeight;

            if (max <= 0 || window.scrollY / max >= threshold) {
              present();
              if (scrollHandler) {
                window.removeEventListener('scroll', scrollHandler);
              }
            }
          };
          window.addEventListener('scroll', scrollHandler, { passive: true });
          scrollHandler();

          return;
        }

        if (trigger.type === 'product_views') {
          if (productViews.current >= Math.max(1, trigger.product_view_count ?? 1)) {
            present();
          }

          return;
        }

        present();
      } catch {
        // Fail silently — campaigns must never block browsing.
      }
    }

    void load();

    return () => {
      cancelled = true;

      if (delayTimer) {
        window.clearTimeout(delayTimer);
      }

      if (scrollHandler) {
        window.removeEventListener('scroll', scrollHandler);
      }
    };
  }, [routeContext, cartProductIds, sessionKey, trackCampaign, location.pathname]);

  const handleClose = useCallback((): void => {
    if (campaign) {
      trackCampaign('campaign_dismissed', campaign);
    }

    setOpen(false);
  }, [campaign, trackCampaign]);

  const handleCta = useCallback((): void => {
    if (!campaign) {
      return;
    }

    trackCampaign('campaign_clicked', campaign);
    setOpen(false);

    const { cta } = campaign;

    if (cta.type === 'product' && cta.product_id) {
      navigate(`/menu/${cta.product_id}`);

      return;
    }

    if (cta.type === 'category' && cta.category_id) {
      navigate(`/menu?category=${cta.category_id}`);

      return;
    }

    if (cta.type === 'internal_page' && cta.internal_path) {
      navigate(cta.internal_path);

      return;
    }

    if (cta.type === 'promotion') {
      navigate('/cart');
    }
  }, [campaign, navigate, trackCampaign]);

  if (!campaign) {
    return null;
  }

  return (
    <CampaignPopupModal
      campaign={campaign}
      open={open}
      onClose={handleClose}
      onCta={handleCta}
    />
  );
}
