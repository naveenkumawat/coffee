import featuredCup from '../assets/images/featured/pic1.png';
import featuredBeans from '../assets/images/featured/pic2.png';
import productOne from '../assets/images/products/product1.jpg';
import productTwo from '../assets/images/products/product2.jpg';
import productThree from '../assets/images/products/product3.jpg';
import { getBackendBaseUrl } from '../api/client';

const productImages = [productOne, productTwo, productThree];
const heroImages = [featuredCup, featuredBeans];

function hashString(value: string): number {
  return value.split('').reduce((carry, character) => carry + character.charCodeAt(0), 0);
}

export function pickProductImage(seed: string, preferred?: string | null): string {
  if (preferred?.trim()) {
    return resolveCatalogMediaUrl(preferred, productImages[hashString(seed) % productImages.length]);
  }

  return productImages[hashString(seed) % productImages.length];
}

export function pickHeroImage(index = 0): string {
  return heroImages[index % heroImages.length];
}

/**
 * Resolve CMS/catalog media for absolute URLs and Laravel public-disk paths.
 * Falls back when the path is empty or unresolvable. Never hardcodes a host —
 * uses VITE_API_BASE_URL / current origin via getBackendBaseUrl().
 */
export function resolveCatalogMediaUrl(path: string | null | undefined, fallback: string): string {
  const value = path?.trim();

  if (!value) {
    return fallback;
  }

  if (/^https?:\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) {
    return value;
  }

  try {
    const backend = getBackendBaseUrl();
    let relative = value.replace(/^\//, '');

    // Managed public-disk paths are served under /storage/...
    if (
      !relative.startsWith('storage/') &&
      (relative.startsWith('products/') ||
        relative.startsWith('categories/') ||
        relative.startsWith('website/'))
    ) {
      relative = `storage/${relative}`;
    }

    return new URL(relative, backend.endsWith('/') ? backend : `${backend}/`).toString();
  } catch {
    return fallback;
  }
}
