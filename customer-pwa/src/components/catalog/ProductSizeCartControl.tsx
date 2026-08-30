import { useEffect, useRef, useState } from 'react';
import { ApiError } from '../../api/client';
import { useCartStore } from '../../stores/cartStore';
import { useToastStore } from '../../stores/toastStore';
import { Product, ProductVariant } from '../../types/catalog';
import { buildCartDisplayFromProduct } from '../../utils/cartDisplay';
import { quantityForVariant } from '../../utils/cartQuantity';
import { formatCurrency } from '../../utils/format';
import {
  getProductVariants,
  getVariantCupKind,
  getVariantShortLabel,
} from '../../utils/productActions';
import { QuantityStepper } from '../common/QuantityStepper';
import { CupIcon } from './CupIcon';

interface ProductSizeCartControlProps {
  product: Product;
  className?: string;
}

export function ProductSizeCartControl({ product, className = '' }: ProductSizeCartControlProps) {
  const rootRef = useRef<HTMLDivElement>(null);
  const [expandedVariantId, setExpandedVariantId] = useState<number | null>(null);
  const cart = useCartStore((state) => state.cart);
  const pendingVariantIds = useCartStore((state) => state.pendingVariantIds);
  const setVariantQuantity = useCartStore((state) => state.setVariantQuantity);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const variants = getProductVariants(product);

  useEffect(() => {
    if (expandedVariantId === null) {
      return;
    }

    const handlePointerDown = (event: PointerEvent): void => {
      const target = event.target;

      if (!(target instanceof Node) || !rootRef.current?.contains(target)) {
        setExpandedVariantId(null);
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);

    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
    };
  }, [expandedVariantId]);

  async function mutate(variant: ProductVariant, nextQuantity: number): Promise<boolean> {
    if (!variant.is_available) {
      return false;
    }

    const previousQuantity = quantityForVariant(cart, variant.id);

    try {
      await setVariantQuantity(
        variant.id,
        nextQuantity,
        buildCartDisplayFromProduct(product, variant),
      );

      if (previousQuantity <= 0 && nextQuantity > 0) {
        toastSuccess('Added to cart');
      }

      if (nextQuantity <= 0) {
        setExpandedVariantId(null);
      }

      return true;
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to update your cart.');

      return false;
    }
  }

  return (
    <div
      ref={rootRef}
      className={`product-size-controls ${className}`.trim()}
      role="group"
      aria-label={`Sizes for ${product.name}`}
      style={{ ['--size-count' as string]: String(Math.max(variants.length, 1)) }}
    >
      {variants.map((variant) => {
        const quantity = quantityForVariant(cart, variant.id);
        const isExpanded = expandedVariantId === variant.id;
        const isPending = pendingVariantIds.includes(variant.id);
        const cupKind = getVariantCupKind(variant) ?? 'small';
        const shortLabel = getVariantShortLabel(variant);
        const disabled = !variant.is_available;

        if (isExpanded && !disabled) {
          return (
            <div
              key={variant.id}
              className="product-size-control is-expanded"
              data-variant-id={variant.id}
            >
              <QuantityStepper
                value={Math.max(quantity, 1)}
                size="sm"
                allowRemove
                disabled={isPending}
                onChange={(next) => {
                  void mutate(variant, next);
                }}
              />
            </div>
          );
        }

        return (
          <button
            key={variant.id}
            type="button"
            className={[
              'product-size-control',
              `is-cup-${cupKind}`,
              quantity > 0 ? 'has-quantity' : '',
              disabled ? 'is-disabled' : '',
            ]
              .filter(Boolean)
              .join(' ')}
            disabled={disabled || isPending}
            aria-busy={isPending || undefined}
            aria-label={
              disabled
                ? `${variant.name}, unavailable`
                : quantity > 0
                  ? `${variant.name}, ${formatCurrency(variant.price)}, ${quantity} in cart`
                  : `Add ${variant.name}, ${formatCurrency(variant.price)}`
            }
            onClick={() => {
              if (disabled) {
                return;
              }

              void (async () => {
                if (quantity <= 0) {
                  const ok = await mutate(variant, 1);

                  if (ok) {
                    setExpandedVariantId(variant.id);
                  }

                  return;
                }

                setExpandedVariantId(variant.id);
              })();
            }}
          >
            {quantity > 0 ? (
              <span className="product-size-qty-badge" aria-hidden="true" key={quantity}>
                {quantity > 99 ? '99+' : quantity}
              </span>
            ) : null}
            <span className="product-size-main">
              <span className="product-size-glyph" aria-hidden="true">
                <CupIcon kind={cupKind} />
                <span className="product-size-label">{shortLabel}</span>
              </span>
            </span>
            <span className="product-size-price">{formatCurrency(variant.price)}</span>
          </button>
        );
      })}
    </div>
  );
}
