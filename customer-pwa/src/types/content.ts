export interface WebsiteHeroContent {
  title: string | null;
  subtitle: string | null;
  image_path: string | null;
}

export interface WebsiteBusinessContent {
  name: string | null;
  about_short: string | null;
  phone: string | null;
  whatsapp_number: string | null;
  email: string | null;
  address: string | null;
  opening_hours: string | null;
}

export interface WebsitePaymentContent {
  display_name: string | null;
  instructions: string | null;
  upi_id: string | null;
  phone: string | null;
  qr_image_path: string | null;
  whatsapp_number: string | null;
}

export interface WebsiteFulfilmentContent {
  delivery_disclaimer: string | null;
  dine_in_enabled?: boolean;
  dining_enabled?: boolean;
}

export interface WebsiteBehaviourContent {
  tracking_enabled: boolean;
}

export interface WebsitePagesContent {
  about: string | null;
  contact: string | null;
  faq: string | null;
  terms: string | null;
  privacy: string | null;
}

export interface WebsiteSocialLink {
  label: string;
  icon_key: string;
  url: string;
  sort_order?: number;
}

export interface WebsiteAvailabilityContent {
  available: boolean;
  code: string;
  message: string;
  next_open_at: string | null;
  reopens_at: string | null;
  timezone: string;
  today_hours?: string | null;
  weekly_hours?: Array<{
    weekday: number;
    label: string;
    is_open: boolean;
    intervals: Array<{ opens_at: string; closes_at: string }>;
  }>;
}

export interface WebsiteContent {
  hero: WebsiteHeroContent;
  business: WebsiteBusinessContent;
  payment: WebsitePaymentContent;
  fulfilment?: WebsiteFulfilmentContent;
  behaviour?: WebsiteBehaviourContent;
  pages: WebsitePagesContent;
  social_links?: WebsiteSocialLink[];
  availability?: WebsiteAvailabilityContent;
}

export type ContentPageKey = keyof WebsitePagesContent;

/** Fallbacks used only until `/content` loads or when settings are empty. */
export const DEFAULT_BRAND_NAME = 'The88Coffees';
export const DEFAULT_HOME_SLOGAN = 'Sip. Relax. Enjoy.';
