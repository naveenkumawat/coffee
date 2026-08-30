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
  hasMultipleVariants,
  isProductUnavailable,
} from '../../utils/productActions';
import { QuantityStepper } from '../common/QuantityStepper';

interface ProductCartControlProps {
  product: Product;
  /** When set (e.g. Quick View / Product Detail), controls that exact variant. */
  variant?: ProductVariant | null;
  size?: 'sm' | 'lg';
  className?: string;
  addLabel?: string;
  /** Opens Quick Product for multi-variant / configure flows. */
  onRequestConfigure?: () => void;
}

export function ProductCartControl({
  product,
  variant = null,
  size = 'sm',
  className = '',
  addLabel = 'Add',
  onRequestConfigure,
}: ProductCartControlProps) {
  const cart = useCartStore((state) => state.cart);
  const pendingVariantIds = useCartStore((state) => state.pendingVariantIds);
  const setVariantQuantity = useCartStore((state) => state.setVariantQuantity);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);

  const unavailable = isProductUnavailable(product);
  const multiVariant = hasMultipleVariants(product) && !variant;
  const quickVariant = variant ?? (canQuickAddProduct(product) ? getProductVariants(product)[0] : null);
  const detailHref = `/menu/${product.id}`;
  const quantity = quickVariant ? quantityForVariant(cart, quickVariant.id) : 0;
  const isPending = quickVariant ? pendingVariantIds.includes(quickVariant.id) : false;
  const isCompact = size === 'sm';

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

  if (multiVariant) {
    if (onRequestConfigure) {
      return (
        <button
          type="button"
          className={`btn btn-outline-dark ${isCompact ? 'btn-sm' : 'btn-lg'} rounded-pill product-card-action ${className}`.trim()}
          onClick={onRequestConfigure}
        >
          Choose size
        </button>
      );
    }

    return (
      <Link
        to={detailHref}
        className={`btn btn-outline-dark ${isCompact ? 'btn-sm' : 'btn-lg'} rounded-pill product-card-action ${className}`.trim()}
      >
        Choose size
      </Link>
    );
  }

  if (!quickVariant?.is_available) {
    if (variant) {
      return (
        <span className={`product-card-action is-disabled ${className}`.trim()}>Unavailable</span>
      );
    }

    if (onRequestConfigure) {
      return (
        <button
          type="button"
          className={`btn btn-outline-dark ${isCompact ? 'btn-sm' : 'btn-lg'} rounded-pill product-card-action ${className}`.trim()}
          onClick={onRequestConfigure}
        >
          View
        </button>
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
    return (
      <div className={`product-cart-control is-add ${className}`.trim()}>
        <button
          type="button"
          className={`btn btn-primary ${isCompact ? 'btn-sm' : 'btn-lg'} rounded-pill product-card-action`}
          disabled={isPending}
          aria-busy={isPending}
          onClick={() => void mutate(1)}
        >
          {isPending ? 'Adding…' : (
            <>
              <i className="bi bi-plus-lg" aria-hidden="true"></i>
              <span>{addLabel}</span>
            </>
          )}
        </button>
      </div>
    );
  }

  return (
    <div className={`product-cart-control is-stepper ${className}`.trim()}>
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
