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

export interface CartSummary {
  item_count: number;
  subtotal: string;
  total: string;
  has_unavailable_items: boolean;
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
