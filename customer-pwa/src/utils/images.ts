import featuredCup from '../assets/images/featured/pic1.png';
import featuredBeans from '../assets/images/featured/pic2.png';
import productOne from '../assets/images/products/product1.jpg';
import productTwo from '../assets/images/products/product2.jpg';
import productThree from '../assets/images/products/product3.jpg';

const productImages = [productOne, productTwo, productThree];
const heroImages = [featuredCup, featuredBeans];

function hashString(value: string): number {
  return value.split('').reduce((carry, character) => carry + character.charCodeAt(0), 0);
}

export function pickProductImage(seed: string, preferred?: string | null): string {
  if (preferred) {
    return preferred;
  }

  return productImages[hashString(seed) % productImages.length];
}

export function pickHeroImage(index = 0): string {
  return heroImages[index % heroImages.length];
}
