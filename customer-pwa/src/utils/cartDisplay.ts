import { Product, ProductVariant } from '../types/catalog';
import {
  CartAddOnSelection,
  CartItemMutationPayload,
  CartProductSummary,
  CartVariantSummary,
} from '../types/cart';
import { buildCartAddOnDisplay } from './addOns';

export function buildCartDisplayFromProduct(
  product: Product,
  variant: ProductVariant,
  addOns: CartAddOnSelection[] = [],
): NonNullable<CartItemMutationPayload['display']> {
  const productSummary: CartProductSummary = {
    id: product.id,
    name: product.name,
    slug: product.slug,
    short_description: product.short_description,
    customer_ingredient_summary: product.customer_ingredient_summary,
    image_path: product.image_path,
  };

  const variantSummary: CartVariantSummary = {
    id: variant.id,
    name: variant.name,
    serving_size_value: variant.serving_size.value,
    serving_size_unit: variant.serving_size.unit,
    price: variant.price,
  };

  return {
    product: productSummary,
    variant: variantSummary,
    add_ons: buildCartAddOnDisplay(product.add_ons ?? [], addOns),
  };
}
