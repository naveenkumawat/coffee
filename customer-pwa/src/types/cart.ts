export interface CartProductSummary {
  id: number;
  name: string;
  slug: string;
  short_description: string | null;
  customer_ingredient_summary: string | null;
  image_path: string | null;
}

export interface CartVariantSummary {
  id: number;
  name: string;
  serving_size_value: string;
  serving_size_unit: string | null;
  price: string;
}

export interface CartItem {
  id: number;
  quantity: number;
  unit_price: string | null;
  line_total: string | null;
  is_available: boolean;
  product: CartProductSummary | null;
  variant: CartVariantSummary | null;
}

export interface CartDiscount {
  promotion_id?: number;
  name: string;
  code?: string | null;
  discount_type?: string;
  discount_value?: string;
  amount: string;
}

export interface CartSummary {
  item_count: number;
  subtotal: string;
  discount_total?: string;
  discounts?: CartDiscount[];
  promo_code?: string | null;
  promo_error?: string | null;
  free_drink_benefit?: string;
  referral_coupon_discount?: string;
  referral_rewards?: Array<{
    reward_id: number;
    reward_type: string;
    title: string;
    benefit_amount: string;
    original_amount?: string;
    preserves_gst_basis?: boolean;
    code?: string | null;
  }>;
  reward_error?: string | null;
  total: string;
  has_unavailable_items: boolean;
  tax?: {
    enabled: boolean;
    label: string;
    percent: string;
    inclusive: boolean;
    taxable_amount: string;
    amount: string;
  };
}

export interface Cart {
  id: number;
  items: CartItem[];
  created_at: string | null;
  updated_at: string | null;
}

export interface CartEnvelopeMeta {
  meta?: {
    summary?: CartSummary;
  };
}

export interface CartCountResponse {
  count: number;
}

export interface CartItemMutationPayload {
  product_variant_id: number;
  quantity: number;
  display?: {
    product: CartProductSummary | null;
    variant: CartVariantSummary | null;
  };
}

export interface CartMergePayload {
  items: Array<{
    product_variant_id: number;
    quantity: number;
  }>;
  idempotency_key?: string | null;
}
