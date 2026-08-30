import { Link } from 'react-router-dom';
import { Product } from '../../types/catalog';
import { formatCurrency } from '../../utils/format';
import { canQuickAddProduct, hasMultipleVariants, isProductUnavailable } from '../../utils/productActions';
import { ProductImage } from '../common/ProductImage';
import { FavouriteToggle } from './FavouriteToggle';
import { ProductBadges } from './ProductBadges';

interface ProductCardProps {
  product: Product;
  onAddToCart?: (product: Product) => void;
  isBusy?: boolean;
  showFavouriteToggle?: boolean;
  layout?: 'grid' | 'rail';
}

export function ProductCard({
  product,
  onAddToCart,
  isBusy = false,
  showFavouriteToggle = true,
  layout = 'grid',
}: ProductCardProps) {
  const unavailable = isProductUnavailable(product);
  const quickAdd = canQuickAddProduct(product);
  const multiVariant = hasMultipleVariants(product);
  const detailHref = `/menu/${product.id}`;
  const price = product.default_variant?.price ?? product.variants.find((variant) => variant.is_available)?.price;

  return (
    <article
      className={`product-card product-card-${layout} ${unavailable ? 'is-unavailable' : ''} ${isBusy ? 'is-busy' : ''}`.trim()}
    >
      <div className="product-card-media">
        <Link to={detailHref} className="product-card-image" tabIndex={-1} aria-hidden="true">
          <ProductImage name={product.name} imagePath={product.image_path} alt="" />
        </Link>
        {showFavouriteToggle ? (
          <FavouriteToggle productId={product.id} className="favourite-toggle-float" size="sm" />
        ) : null}
        <ProductBadges product={product} compact />
      </div>

      <div className="product-card-body">
        <div className="product-card-copy">
          <span className="product-card-category">{product.category?.name ?? 'Menu'}</span>
          <Link to={detailHref} className="product-card-title">
            {product.name}
          </Link>
          {unavailable ? <span className="availability-chip">Unavailable</span> : null}
        </div>

        <div className="product-card-footer">
          <strong className="product-card-price">{price ? formatCurrency(price) : '—'}</strong>

          {unavailable ? (
            <span className="product-card-action is-disabled">Unavailable</span>
          ) : multiVariant ? (
            <Link to={detailHref} className="btn btn-outline-dark btn-sm rounded-pill product-card-action">
              Choose size
            </Link>
          ) : quickAdd ? (
            <button
              type="button"
              className="btn btn-primary btn-sm rounded-pill product-card-action"
              disabled={isBusy || !onAddToCart}
              onClick={() => onAddToCart?.(product)}
            >
              {isBusy ? 'Adding…' : 'Add'}
            </button>
          ) : (
            <Link to={detailHref} className="btn btn-outline-dark btn-sm rounded-pill product-card-action">
              View
            </Link>
          )}
        </div>
      </div>
    </article>
  );
}
