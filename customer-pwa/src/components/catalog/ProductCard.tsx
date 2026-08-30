import { useCallback, useState } from 'react';
import { Product } from '../../types/catalog';
import { formatCurrency } from '../../utils/format';
import {
  hasRecognizedSizeControls,
  isProductUnavailable,
  needsQuickAddFallback,
  startingPrice,
} from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductBadges } from './ProductBadges';
import { ProductCartControl } from './ProductCartControl';
import { ProductDetailSheet } from './ProductDetailSheet';
import { QuickAddSheet } from './QuickAddSheet';

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
  const [quickAddOpen, setQuickAddOpen] = useState(false);
  const unavailable = isProductUnavailable(product);
  const recognizedSizes = hasRecognizedSizeControls(product);
  const quickAddFallback = needsQuickAddFallback(product);
  const price = recognizedSizes
    ? startingPrice(product)
    : product.default_variant?.price ?? product.variants.find((variant) => variant.is_available)?.price;

  const openDetails = useCallback((): void => {
    setDetailOpen(true);
  }, []);

  const closeDetails = useCallback((): void => {
    setDetailOpen(false);
  }, []);

  const openQuickAdd = useCallback((): void => {
    setQuickAddOpen(true);
  }, []);

  const closeQuickAdd = useCallback((): void => {
    setQuickAddOpen(false);
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
          <ProductBadges product={product} compact />
        </div>

        <div className="product-card-body">
          <div className="product-card-copy">
            <span className="product-card-category">{product.category?.name ?? 'Menu'}</span>
            <button type="button" className="product-card-title" onClick={openDetails}>
              {product.name}
            </button>
            {unavailable ? <span className="availability-chip">Unavailable</span> : null}
          </div>

          <div
            className={[
              'product-card-action-zone',
              recognizedSizes ? 'is-multi-size' : '',
              quickAddFallback ? 'is-quick-add-fallback' : '',
              !recognizedSizes && !quickAddFallback ? 'is-single' : '',
            ]
              .filter(Boolean)
              .join(' ')}
          >
            {recognizedSizes ? (
              <ProductCartControl product={product} size="sm" />
            ) : quickAddFallback ? (
              <ProductCartControl
                product={product}
                size="sm"
                onRequestConfigure={openQuickAdd}
              />
            ) : (
              <>
                <div className="product-card-action-zone-price">
                  <strong>{price ? formatCurrency(price) : '—'}</strong>
                </div>
                <div className="product-card-action-zone-cta">
                  <ProductCartControl product={product} size="sm" iconOnly />
                </div>
              </>
            )}
          </div>
        </div>
      </article>

      {quickAddFallback ? (
        <QuickAddSheet product={product} open={quickAddOpen} onClose={closeQuickAdd} />
      ) : null}

      <ProductDetailSheet
        product={product}
        open={detailOpen}
        onClose={closeDetails}
        showFavouriteToggle={showFavouriteToggle}
      />
    </>
  );
}
