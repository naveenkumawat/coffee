import { Link } from 'react-router-dom';
import { ApiError } from '../../api/client';
import { useCartStore } from '../../stores/cartStore';
import { useToastStore } from '../../stores/toastStore';
import { Product, ProductVariant } from '../../types/catalog';
import { buildCartDisplayFromProduct } from '../../utils/cartDisplay';
import { quantityForVariant } from '../../utils/cartQuantity';
import {
  canQuickAddProduct,
  getProductVariants,
  hasRecognizedSizeControls,
  isProductUnavailable,
  needsQuickAddFallback,
} from '../../utils/productActions';
import { QuantityStepper } from '../common/QuantityStepper';
import { ProductSizeCartControl } from './ProductSizeCartControl';

interface ProductCartControlProps {
  product: Product;
  /** When set (e.g. Product Detail sheet/page), controls that exact variant. */
  variant?: ProductVariant | null;
  size?: 'sm' | 'lg';
  className?: string;
  addLabel?: string;
  /** Card compact mode: bag-plus icon instead of text. */
  iconOnly?: boolean;
  /** Opens QuickAdd fallback for unusual multi-size products. */
  onRequestConfigure?: () => void;
  /** Fired after a successful first add (0 → positive quantity). */
  onAdded?: () => void;
}

export function ProductCartControl({
  product,
  variant = null,
  size = 'sm',
  className = '',
  addLabel = 'Add',
  iconOnly = false,
  onRequestConfigure,
  onAdded,
}: ProductCartControlProps) {
  const cart = useCartStore((state) => state.cart);
  const pendingVariantIds = useCartStore((state) => state.pendingVariantIds);
  const setVariantQuantity = useCartStore((state) => state.setVariantQuantity);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);

  const unavailable = isProductUnavailable(product);
  const isCompact = size === 'sm';

  if (!variant && hasRecognizedSizeControls(product)) {
    return <ProductSizeCartControl product={product} className={className} />;
  }

  if (!variant && needsQuickAddFallback(product)) {
    return (
      <button
        type="button"
        className={`product-card-choose-size ${className}`.trim()}
        onClick={onRequestConfigure}
      >
        Choose size
      </button>
    );
  }

  const quickVariant = variant ?? (canQuickAddProduct(product) ? getProductVariants(product)[0] : null);
  const detailHref = `/menu/${product.id}`;
  const quantity = quickVariant ? quantityForVariant(cart, quickVariant.id) : 0;
  const isPending = quickVariant ? pendingVariantIds.includes(quickVariant.id) : false;

  async function mutate(nextQuantity: number): Promise<void> {
    if (!quickVariant?.is_available) {
      return;
    }

    const previousQuantity = quantity;

    try {
      await setVariantQuantity(
        quickVariant.id,
        nextQuantity,
        buildCartDisplayFromProduct(product, quickVariant),
      );

      if (previousQuantity <= 0 && nextQuantity > 0) {
        toastSuccess('Added to cart');
        onAdded?.();
      }
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to update your cart.');
    }
  }

  if (unavailable) {
    return (
      <span className={`product-card-action is-disabled ${className}`.trim()}>Unavailable</span>
    );
  }

  if (!quickVariant?.is_available) {
    if (variant) {
      return (
        <span className={`product-card-action is-disabled ${className}`.trim()}>Unavailable</span>
      );
    }

    return (
      <Link
        to={detailHref}
        className={`btn btn-outline-dark ${isCompact ? 'btn-sm' : 'btn-lg'} rounded-pill product-card-action ${className}`.trim()}
      >
        View
      </Link>
    );
  }

  if (quantity <= 0) {
    if (iconOnly) {
      return (
        <div className={`product-cart-control is-icon-add ${className}`.trim()}>
          <button
            type="button"
            className="product-card-bag-add"
            disabled={isPending}
            aria-busy={isPending}
            aria-label={`Add ${product.name} to cart`}
            title={`Add ${product.name} to cart`}
            onClick={() => void mutate(1)}
          >
            <i className="bi bi-bag-plus" aria-hidden="true"></i>
          </button>
        </div>
      );
    }

    return (
      <div className={`product-cart-control is-add ${className}`.trim()}>
        <button
          type="button"
          className={`btn btn-primary ${isCompact ? 'btn-sm' : 'btn-lg'} rounded-pill product-card-action`}
          disabled={isPending}
          aria-busy={isPending}
          onClick={() => void mutate(1)}
        >
          {isPending ? 'Adding…' : addLabel}
        </button>
      </div>
    );
  }

  return (
    <div className={`product-cart-control is-stepper ${iconOnly ? 'is-icon-add' : ''} ${className}`.trim()}>
      <QuantityStepper
        value={quantity}
        size={isCompact ? 'sm' : 'lg'}
        allowRemove
        disabled={isPending || !quickVariant.is_available}
        onChange={(next) => void mutate(next)}
      />
    </div>
  );
}
