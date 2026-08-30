import { Product } from '../types/catalog';

export function getProductVariants(product: Product): Product['variants'] {
  if (product.variants.length > 0) {
    return product.variants;
  }

  return product.default_variant ? [product.default_variant] : [];
}

export function canQuickAddProduct(product: Product): boolean {
  const variants = getProductVariants(product);

  return variants.length === 1 && variants[0].is_available;
}

export function isProductUnavailable(product: Product): boolean {
  const variants = getProductVariants(product);

  return variants.length === 0 || variants.every((variant) => !variant.is_available);
}

export function hasMultipleVariants(product: Product): boolean {
  return getProductVariants(product).length > 1;
}
