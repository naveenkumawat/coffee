import { Product } from '../../types/catalog';

interface ProductBadgesProps {
  product: Pick<Product, 'is_new' | 'is_bestseller' | 'is_vegetarian' | 'is_customizable'>;
  showCustomizable?: boolean;
  compact?: boolean;
}

export function ProductBadges({ product, showCustomizable = false, compact = false }: ProductBadgesProps) {
  const badges = [
    product.is_new ? { key: 'new', label: 'NEW', className: 'is-new' } : null,
    product.is_bestseller ? { key: 'bestseller', label: 'TOP', className: 'is-bestseller' } : null,
    product.is_vegetarian
      ? {
          key: 'veg',
          label: 'VEG',
          className: 'is-veg',
          icon: 'bi-circle-fill',
        }
      : null,
    showCustomizable && product.is_customizable
      ? { key: 'customizable', label: compact ? 'CUSTOM' : 'CUSTOM', className: 'is-customizable' }
      : null,
  ].filter(Boolean) as Array<{ key: string; label: string; className: string; icon?: string }>;

  if (badges.length === 0) {
    return null;
  }

  return (
    <div className={`product-badges ${compact ? 'is-compact' : ''}`.trim()} aria-label="Product badges">
      {badges.map((badge) => (
        <span key={badge.key} className={`product-badge ${badge.className}`}>
          {badge.icon ? <i className={badge.icon} aria-hidden="true"></i> : null}
          <span>{badge.label}</span>
        </span>
      ))}
    </div>
  );
}
