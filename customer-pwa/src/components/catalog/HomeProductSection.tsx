import { Product } from '../../types/catalog';
import { ProductCard } from './ProductCard';
import { ProductRail } from './ProductRail';

interface HomeProductSectionProps {
  title: string;
  subtitle?: string | null;
  products: Product[];
}

export function HomeProductSection({ title, subtitle, products }: HomeProductSectionProps) {
  if (products.length === 0) {
    return null;
  }

  return (
    <ProductRail eyebrow={subtitle ?? undefined} title={title} seeAllHref="/menu" seeAllLabel="See all">
      {products.map((product) => (
        <div key={product.id} className="product-rail-item" role="listitem">
          <ProductCard product={product} layout="rail" />
        </div>
      ))}
    </ProductRail>
  );
}
