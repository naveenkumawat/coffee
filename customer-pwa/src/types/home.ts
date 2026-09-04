import { Product } from './catalog';
import { EligibleCampaign } from '../api/campaigns';

export interface MerchandisingProductAttribution {
  source_type: 'recommendation' | 'campaign' | string;
  request_id: string;
  strategy?: string | null;
  reason?: string | null;
  placement?: string | null;
  source_id?: number | null;
}

export interface HomeSectionProduct extends Product {
  attribution?: MerchandisingProductAttribution | null;
}

export interface HomeSection {
  id: number;
  title: string;
  subtitle: string | null;
  slug: string;
  source_type?: string;
  placement?: string;
  products: HomeSectionProduct[];
  recommendation?: {
    request_id: string;
    context: string;
    cold_start: boolean;
  } | null;
}

export interface HomeCampaigns {
  banner: EligibleCampaign | null;
  inline: EligibleCampaign | null;
}

export interface HomePayload {
  placement: string;
  sections: HomeSection[];
  campaigns?: HomeCampaigns;
}
