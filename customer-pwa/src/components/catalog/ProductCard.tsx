import { useCallback, useState } from 'react';
import { Product } from '../../types/catalog';
import { isProductUnavailable } from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductDetailSheet } from './ProductDetailSheet';
import { ProductOrderControl, ProductOrderHandler } from './ProductOrderControl';
import { ProductRatingSummary } from './ProductRatingSummary';
import { ProductTags } from './ProductTags';

interface ProductCardProps {
  product: Product;
  showFavouriteToggle?: boolean;
  layout?: 'grid' | 'rail';
  orderHandler?: ProductOrderHandler;
  sheetCtaLabel?: string;
}

export function ProductCard({
  product,
  showFavouriteToggle = true,
  layout = 'grid',
  orderHandler,
  sheetCtaLabel,
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
          <div className="product-card-media-clip">
            <div className="product-card-image">
              <ProductImage name={product.name} imagePath={product.image_path} alt="" fit="cover" />
            </div>
          </div>
          {showFavouriteToggle ? (
            <FavouriteToggle productId={product.id} className="favourite-toggle-float" size="sm" />
          ) : null}
          <button
            type="button"
            className="product-card-details"
            onClick={openDetails}
            aria-label={`View details for ${product.name}`}
          >
            <i className="bi bi-eye" aria-hidden="true"></i>
          </button>
        </div>

        <div className="product-card-body">
          <div className="product-card-copy">
            <span className="product-card-category">{product.category?.name ?? 'Menu'}</span>
            <h3 className="product-card-title">{product.name}</h3>
            <ProductRatingSummary summary={product.rating_summary} />
            <div className="product-card-tags-slot">
              <ProductTags tags={product.tags} mode="compact" maxVisible={2} />
            </div>
            {unavailable ? <span className="availability-chip">Unavailable</span> : null}
          </div>

          <div className="product-card-action-zone">
            <ProductOrderControl
              product={product}
              mode="compact"
              orderHandler={orderHandler}
              sheetCtaLabel={sheetCtaLabel}
            />
          </div>
        </div>
      </article>

      <ProductDetailSheet
        product={product}
        open={detailOpen}
        onClose={closeDetails}
        showFavouriteToggle={showFavouriteToggle}
        orderHandler={orderHandler}
        sheetCtaLabel={sheetCtaLabel}
      />
    </>
  );
}
