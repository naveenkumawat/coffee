export interface ProductCategory {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image_path: string | null;
  products_count: number | null;
}

export interface ProductFlavour {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image_path: string | null;
  products_count?: number | null;
}

export interface ProductServingSize {
  value: string;
  unit: string | null;
  label: string;
}

export interface ProductMajorIngredient {
  id: number;
  label: string;
}

export interface ProductVariant {
  id: number;
  product_id: number;
  product_name: string | null;
  name: string;
  serving_size: ProductServingSize;
  price: string;
  is_available: boolean;
  major_ingredients?: ProductMajorIngredient[];
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  short_description: string | null;
  description: string | null;
  customer_ingredient_summary: string | null;
  image_path: string | null;
  preparation_time_minutes: number | null;
  is_featured: boolean;
  is_new: boolean;
  is_bestseller: boolean;
  is_vegetarian: boolean;
  is_customizable: boolean;
  category: ProductCategory | null;
  flavours: ProductFlavour[];
  default_variant: ProductVariant | null;
  variants: ProductVariant[];
}

export interface ProductListMeta {
  meta?: {
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
      from: number | null;
      to: number | null;
    };
  };
}
