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
  whatsapp_number: string | null;
}

export interface WebsitePagesContent {
  about: string | null;
  contact: string | null;
  faq: string | null;
  terms: string | null;
  privacy: string | null;
}

export interface WebsiteContent {
  hero: WebsiteHeroContent;
  business: WebsiteBusinessContent;
  payment: WebsitePaymentContent;
  pages: WebsitePagesContent;
}

export type ContentPageKey = keyof WebsitePagesContent;
