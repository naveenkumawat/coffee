import { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { EligibleCampaign, recordCampaignInteraction } from '../../api/campaigns';
import { trackBehaviour } from '../../tracking/behaviourTracker';
import { getOrCreateCampaignSessionKey } from '../../utils/campaignSession';
import { stashCartAttribution } from '../../utils/cartAttributionStash';
import { getOrCreateVisitorId } from '../../utils/visitorId';

interface LandingCampaignSurfaceProps {
  campaign: EligibleCampaign | null | undefined;
  surface: 'banner' | 'inline';
  placement: string;
}

export function LandingCampaignSurface({ campaign, surface, placement }: LandingCampaignSurfaceProps) {
  const navigate = useNavigate();
  const impressedRequestId = useRef<string | null>(null);
  const sessionKey = getOrCreateCampaignSessionKey();

  useEffect(() => {
    if (!campaign?.request_id) {
      return;
    }

    if (impressedRequestId.current === campaign.request_id) {
      return;
    }

    impressedRequestId.current = campaign.request_id;

    void recordCampaignInteraction({
      campaign_id: campaign.id,
      event_type: 'impression',
      visitor_key: getOrCreateVisitorId(),
      session_key: sessionKey,
      placement,
      request_id: campaign.request_id,
    }).catch(() => undefined);

    trackBehaviour({
      event_type: 'campaign_impression',
      metadata: {
        campaign_id: campaign.id,
        request_id: campaign.request_id,
        attribution_key: campaign.attribution_key,
        surface,
        placement,
      },
      dedupe_key: `campaign_impression:${campaign.request_id}:${campaign.id}`,
    });
  }, [campaign, placement, sessionKey, surface]);

  if (!campaign) {
    return null;
  }

  const onCta = (): void => {
    void recordCampaignInteraction({
      campaign_id: campaign.id,
      event_type: 'click',
      visitor_key: getOrCreateVisitorId(),
      session_key: sessionKey,
      placement,
      request_id: campaign.request_id,
      cta_type: campaign.cta.type,
    }).catch(() => undefined);

    trackBehaviour({
      event_type: 'campaign_clicked',
      metadata: {
        campaign_id: campaign.id,
        request_id: campaign.request_id,
        attribution_key: campaign.attribution_key,
        surface,
        placement,
        cta_type: campaign.cta.type,
      },
      dedupe_key: `campaign_click:${campaign.request_id}:${campaign.id}`,
    });

    if (campaign.cta.type === 'product' && campaign.cta.product_id) {
      stashCartAttribution(campaign.cta.product_id, {
        source_type: 'campaign',
        source_id: campaign.id,
        request_id: campaign.request_id,
        placement,
        context: surface,
      });
      navigate(`/menu/${campaign.cta.product_id}`);
      return;
    }

    if (campaign.cta.type === 'category' && campaign.cta.category_id) {
      navigate(`/menu?category=${campaign.cta.category_id}`);
      return;
    }

    if (campaign.cta.type === 'internal_page' && campaign.cta.internal_path) {
      navigate(campaign.cta.internal_path.startsWith('/') ? campaign.cta.internal_path : `/${campaign.cta.internal_path}`);
      return;
    }

    if (campaign.cta.type === 'promotion') {
      navigate('/menu');
    }
  };

  return (
    <section className={`section-shell landing-campaign landing-campaign-${surface}`} data-surface={surface}>
      <div className="landing-campaign-card">
        {campaign.image_url ? (
          <img
            src={campaign.image_url}
            alt=""
            className="landing-campaign-image"
            onError={(event) => {
              event.currentTarget.style.display = 'none';
            }}
          />
        ) : null}
        <div className="landing-campaign-copy">
          <h2>{campaign.title}</h2>
          {campaign.message ? <p>{campaign.message}</p> : null}
          {campaign.cta.type !== 'close' && campaign.cta_label ? (
            <button type="button" className="btn btn-primary rounded-pill" onClick={onCta}>
              {campaign.cta_label}
            </button>
          ) : null}
        </div>
      </div>
    </section>
  );
}
