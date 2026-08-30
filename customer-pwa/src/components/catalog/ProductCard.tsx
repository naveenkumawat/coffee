import { Link } from 'react-router-dom';
import { Product } from '../../types/catalog';
import { formatCurrency, joinLabels } from '../../utils/format';
import { pickProductImage } from '../../utils/images';

interface ProductCardProps {
  product: Product;
  onAddToCart?: (product: Product) => void;
  isBusy?: boolean;
}

export function ProductCard({ product, onAddToCart, isBusy = false }: ProductCardProps) {
  const image = pickProductImage(product.name, product.image_path);

  return (
    <article className="product-card">
      <Link to={`/menu/${product.id}`} className="product-card-image">
        <img src={image} alt={product.name} />
      </Link>
      <div className="product-card-body">
        <div className="product-card-copy">
          <span className="product-card-category">{product.category?.name ?? 'Coffee menu'}</span>
          <Link to={`/menu/${product.id}`} className="product-card-title">
            {product.name}
          </Link>
          <p className="product-card-description">
            {product.short_description || product.description || 'Freshly prepared for quick pickup.'}
          </p>
          <p className="product-card-meta">
            {joinLabels([
              product.customer_ingredient_summary,
              product.default_variant?.serving_size.label,
              product.preparation_time_minutes ? `${product.preparation_time_minutes} min` : null
            ])}
          </p>
        </div>
        <div className="product-card-footer">
          <strong>{formatCurrency(product.default_variant?.price ?? 0)}</strong>
          <button
            type="button"
            className="btn btn-primary rounded-pill px-3"
            disabled={!product.default_variant || isBusy}
            onClick={() => onAddToCart?.(product)}
          >
            {isBusy ? 'Adding...' : 'Add'}
          </button>
        </div>
      </div>
    </article>
  );
}
