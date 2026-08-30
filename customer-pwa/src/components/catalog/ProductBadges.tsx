import { Product } from '../../types/catalog';

interface ProductBadgesProps {
  product: Pick<Product, 'is_new' | 'is_bestseller' | 'is_vegetarian' | 'is_customizable'>;
  showCustomizable?: boolean;
}

export function ProductBadges({ product, showCustomizable = false }: ProductBadgesProps) {
  const badges = [
    product.is_new ? { key: 'new', label: 'New', className: 'is-new' } : null,
    product.is_bestseller ? { key: 'bestseller', label: 'Bestseller', className: 'is-bestseller' } : null,
    product.is_vegetarian ? { key: 'veg', label: 'Veg', className: 'is-veg' } : null,
    showCustomizable && product.is_customizable
      ? { key: 'customizable', label: 'Customizable', className: 'is-customizable' }
      : null
  ].filter(Boolean) as Array<{ key: string; label: string; className: string }>;

  if (badges.length === 0) {
    return null;
  }

  return (
    <div className="product-badges">
      {badges.map((badge) => (
        <span key={badge.key} className={`product-badge ${badge.className}`}>
          {badge.label}
        </span>
      ))}
    </div>
  );
}
