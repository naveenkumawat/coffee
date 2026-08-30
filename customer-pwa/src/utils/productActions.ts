import { Product, ProductVariant } from '../types/catalog';

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

/** Prefer default when available; otherwise first available; else first listed. */
export function getPreferredVariant(product: Product): ProductVariant | null {
  const variants = getProductVariants(product);

  if (variants.length === 0) {
    return null;
  }

  if (product.default_variant?.is_available) {
    const matched = variants.find((variant) => variant.id === product.default_variant?.id);

    if (matched) {
      return matched;
    }
  }

  return variants.find((variant) => variant.is_available) ?? variants[0] ?? null;
}
