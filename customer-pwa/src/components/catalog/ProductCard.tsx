import { useCallback, useState } from 'react';
import { Product } from '../../types/catalog';
import { formatCurrency } from '../../utils/format';
import { isProductUnavailable } from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductBadges } from './ProductBadges';
import { ProductCartControl } from './ProductCartControl';
import { ProductQuickView } from './ProductQuickView';

interface ProductCardProps {
  product: Product;
  showFavouriteToggle?: boolean;
  layout?: 'grid' | 'rail';
}

export function ProductCard({
  product,
  showFavouriteToggle = true,
  layout = 'grid',
}: ProductCardProps) {
  const [quickOpen, setQuickOpen] = useState(false);
  const unavailable = isProductUnavailable(product);
  const price = product.default_variant?.price ?? product.variants.find((variant) => variant.is_available)?.price;

  const openQuickView = useCallback((): void => {
    setQuickOpen(true);
  }, []);

  const closeQuickView = useCallback((): void => {
    setQuickOpen(false);
  }, []);

  return (
    <>
      <article className={`product-card product-card-${layout} ${unavailable ? 'is-unavailable' : ''}`.trim()}>
        <div className="product-card-media">
          <button
            type="button"
            className="product-card-image"
            onClick={openQuickView}
            aria-label={`View ${product.name}`}
          >
            <ProductImage name={product.name} imagePath={product.image_path} alt="" />
          </button>
          <button
            type="button"
            className="product-card-details"
            onClick={openQuickView}
            aria-label={`Details for ${product.name}`}
          >
            <i className="bi bi-eye" aria-hidden="true"></i>
          </button>
          {showFavouriteToggle ? (
            <FavouriteToggle productId={product.id} className="favourite-toggle-float" size="sm" />
          ) : null}
          <ProductBadges product={product} compact />
        </div>

        <div className="product-card-body">
          <div className="product-card-copy">
            <span className="product-card-category">{product.category?.name ?? 'Menu'}</span>
            <button type="button" className="product-card-title" onClick={openQuickView}>
              {product.name}
            </button>
            {unavailable ? <span className="availability-chip">Unavailable</span> : null}
          </div>

          <div className="product-card-footer">
            <strong className="product-card-price">{price ? formatCurrency(price) : '—'}</strong>
            <div className="product-card-action-slot">
              <ProductCartControl product={product} size="sm" onRequestConfigure={openQuickView} />
            </div>
          </div>
        </div>
      </article>

      <ProductQuickView
        product={product}
        open={quickOpen}
        onClose={closeQuickView}
        showFavouriteToggle={showFavouriteToggle}
      />
    </>
  );
}
