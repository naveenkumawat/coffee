import { useCallback, useState } from 'react';
import { Product } from '../../types/catalog';
import { isProductUnavailable } from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductDetailSheet } from './ProductDetailSheet';
import { ProductOrderControl } from './ProductOrderControl';
import { ProductTags } from './ProductTags';

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
  const [detailOpen, setDetailOpen] = useState(false);
  const unavailable = isProductUnavailable(product);

  const openDetails = useCallback((): void => {
    setDetailOpen(true);
  }, []);

  const closeDetails = useCallback((): void => {
    setDetailOpen(false);
  }, []);

  return (
    <>
      <article className={`product-card product-card-${layout} ${unavailable ? 'is-unavailable' : ''}`.trim()}>
        <div className="product-card-media">
          <button
            type="button"
            className="product-card-image"
            onClick={openDetails}
            aria-label={`View ${product.name}`}
          >
            <ProductImage name={product.name} imagePath={product.image_path} alt="" />
          </button>
          <button
            type="button"
            className="product-card-details"
            onClick={openDetails}
            aria-label={`Details for ${product.name}`}
          >
            <i className="bi bi-eye" aria-hidden="true"></i>
          </button>
          {showFavouriteToggle ? (
            <FavouriteToggle productId={product.id} className="favourite-toggle-float" size="sm" />
          ) : null}
          <ProductTags tags={product.tags} mode="compact" maxVisible={2} />
        </div>

        <div className="product-card-body">
          <div className="product-card-copy">
            <span className="product-card-category">{product.category?.name ?? 'Menu'}</span>
            <button type="button" className="product-card-title" onClick={openDetails}>
              {product.name}
            </button>
            {unavailable ? <span className="availability-chip">Unavailable</span> : null}
          </div>

          <div className="product-card-action-zone">
            <ProductOrderControl product={product} mode="compact" />
          </div>
        </div>
      </article>

      <ProductDetailSheet
        product={product}
        open={detailOpen}
        onClose={closeDetails}
        showFavouriteToggle={showFavouriteToggle}
      />
    </>
  );
}
