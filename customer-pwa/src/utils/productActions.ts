import { Product, ProductVariant } from '../types/catalog';

export function getProductVariants(product: Product): Product['variants'] {
  if (product.variants.length > 0) {
    return product.variants;
  }

  return product.default_variant ? [product.default_variant] : [];
}

export function productHasAddOns(product: Product): boolean {
  return (product.add_ons?.length ?? 0) > 0;
}

/** Fast-add is retired — every product opens the shared customization sheet. */
export function canQuickAddProduct(_product: Product): boolean {
  return false;
}

/** Always open ProductCustomizationSheet (single/multi variant, with or without add-ons). */
export function needsProductCustomization(product: Product): boolean {
  return !isProductUnavailable(product);
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

export type VariantCupKind = 'small' | 'large' | null;

function normalizeVariantName(name: string): string {
  return name.trim().toLowerCase().replace(/\s+/g, ' ');
}

/** Cup icon only for clearly sized drink variants — never invent mappings. */
export function getVariantCupKind(variant: ProductVariant): VariantCupKind {
  const name = normalizeVariantName(variant.name);

  if (['small', 's', 'short', 'tall', 'regular', 'reg', 'medium', 'm'].includes(name)) {
    return 'small';
  }

  if (['large', 'l', 'grande', 'venti', 'xl', 'extra large', 'extra-large'].includes(name)) {
    return 'large';
  }

  return null;
}

export function getVariantShortLabel(variant: ProductVariant): string {
  const name = normalizeVariantName(variant.name);

  if (name === 'small' || name === 's' || name === 'short' || name === 'tall') {
    return 'S';
  }

  if (name === 'regular' || name === 'reg') {
    return 'R';
  }

  if (name === 'medium' || name === 'm') {
    return 'M';
  }

  if (name === 'large' || name === 'l' || name === 'grande') {
    return 'L';
  }

  if (name === 'xl' || name === 'extra large' || name === 'extra-large' || name === 'venti') {
    return 'XL';
  }

  const compact = variant.name.replace(/[^A-Za-z0-9]/g, '');

  return compact.slice(0, 3).toUpperCase() || variant.name.slice(0, 2).toUpperCase();
}

export function startingPrice(product: Product): string | null {
  const prices = getProductVariants(product)
    .filter((variant) => variant.is_available)
    .map((variant) => Number(variant.price))
    .filter((price) => Number.isFinite(price));

  if (prices.length === 0) {
    return product.default_variant?.price ?? null;
  }

  return Math.min(...prices).toFixed(2);
}

/** True when every variant maps to a known cup size (inline S/L controls). */
export function hasRecognizedSizeControls(product: Product): boolean {
  const variants = getProductVariants(product);

  return variants.length >= 2 && variants.every((variant) => getVariantCupKind(variant) !== null);
}

/** Multi-variant products that need QuickAdd instead of inline cups. */
export function needsQuickAddFallback(product: Product): boolean {
  return hasMultipleVariants(product) && !hasRecognizedSizeControls(product);
}
