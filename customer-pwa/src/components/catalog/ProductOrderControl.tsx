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
  hasRecognizedSizeControls,
  isProductUnavailable,
  needsProductCustomization,
  startingPrice,
} from '../../utils/productActions';
import { QuantityStepper } from '../common/QuantityStepper';
import { CupIcon } from './CupIcon';
import { ProductCustomizationSheet } from './ProductCustomizationSheet';

export type ProductOrderControlMode = 'compact' | 'full';

interface ProductOrderControlProps {
  product: Product;
  mode?: ProductOrderControlMode;
  className?: string;
  /** Fired after a successful first add (0 → positive quantity). */
  onAdded?: () => void;
}

/**
 * Single ordering control for cards, sheets, and detail pages.
 * Density differs by mode; interaction model stays identical.
 */
export function ProductOrderControl({
  product,
  mode = 'compact',
  className = '',
  onAdded,
}: ProductOrderControlProps) {
  const variants = getProductVariants(product);
  const unavailable = isProductUnavailable(product);

  if (unavailable || variants.length === 0) {
    return (
      <span className={`product-order-control is-disabled ${className}`.trim()}>Unavailable</span>
    );
  }

  if (needsProductCustomization(product)) {
    return (
      <CustomizeOrderControl
        product={product}
        mode={mode}
        className={className}
        onAdded={onAdded}
      />
    );
  }

  if (variants.length === 1) {
    return (
      <SingleVariantOrderControl
        product={product}
        variant={variants[0]}
        mode={mode}
        className={className}
        onAdded={onAdded}
      />
    );
  }

  return (
    <MultiVariantOrderControl
      product={product}
      variants={variants}
      mode={mode}
      className={className}
      onAdded={onAdded}
    />
  );
}

interface CustomizeOrderControlProps {
  product: Product;
  mode: ProductOrderControlMode;
  className?: string;
  onAdded?: () => void;
}

function CustomizeOrderControl({
  product,
  mode,
  className = '',
  onAdded,
}: CustomizeOrderControlProps) {
  const [open, setOpen] = useState(false);
  const isCompact = mode === 'compact';
  const price = startingPrice(product);

  return (
    <>
      <div
        className={`product-order-control is-customize ${isCompact ? 'is-compact' : 'is-full'} ${className}`.trim()}
      >
        {isCompact && price ? (
          <strong className="product-order-price">{formatCurrency(price)}</strong>
        ) : null}
        <button
          type="button"
          className={
            isCompact
              ? 'product-card-bag-add'
              : 'btn btn-primary btn-lg rounded-pill product-card-action'
          }
          aria-label={`Customize ${product.name}`}
          title={`Customize ${product.name}`}
          onClick={() => setOpen(true)}
        >
          {isCompact ? (
            <i className="bi bi-sliders" aria-hidden="true"></i>
          ) : (
            <>
              <i className="bi bi-sliders" aria-hidden="true"></i>
              <span>Customize</span>
            </>
          )}
        </button>
      </div>

      <ProductCustomizationSheet
        product={product}
        open={open}
        onClose={() => setOpen(false)}
        onSaved={onAdded}
      />
    </>
  );
}

interface SingleVariantOrderControlProps {
  product: Product;
  variant: ProductVariant;
  mode: ProductOrderControlMode;
  className?: string;
  onAdded?: () => void;
}

function SingleVariantOrderControl({
  product,
  variant,
  mode,
  className = '',
  onAdded,
}: SingleVariantOrderControlProps) {
  const cart = useCartStore((state) => state.cart);
  const pendingVariantIds = useCartStore((state) => state.pendingVariantIds);
  const setVariantQuantity = useCartStore((state) => state.setVariantQuantity);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const quantity = quantityForVariant(cart, variant.id);
  const isPending = pendingVariantIds.includes(variant.id);
  const isCompact = mode === 'compact';

  async function mutate(nextQuantity: number): Promise<void> {
    if (!variant.is_available) {
      return;
    }

    const previousQuantity = quantity;

    try {
      await setVariantQuantity(
        variant.id,
        nextQuantity,
        buildCartDisplayFromProduct(product, variant),
      );

      if (previousQuantity <= 0 && nextQuantity > 0) {
        toastSuccess('Added to cart');
        onAdded?.();
      }
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to update your cart.');
    }
  }

  if (!variant.is_available) {
    return (
      <span className={`product-order-control is-disabled ${className}`.trim()}>Unavailable</span>
    );
  }

  if (quantity <= 0) {
    if (isCompact) {
      return (
        <div className={`product-order-control is-single is-compact ${className}`.trim()}>
          <strong className="product-order-price">{formatCurrency(variant.price)}</strong>
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
      <div className={`product-order-control is-single is-full ${className}`.trim()}>
        <button
          type="button"
          className="btn btn-primary btn-lg rounded-pill product-card-action"
          disabled={isPending}
          aria-busy={isPending}
          onClick={() => void mutate(1)}
        >
          <i className="bi bi-bag-plus" aria-hidden="true"></i>
          <span>{isPending ? 'Adding…' : 'Add to order'}</span>
        </button>
      </div>
    );
  }

  return (
    <div
      className={`product-order-control is-single is-stepper ${isCompact ? 'is-compact' : 'is-full'} ${className}`.trim()}
    >
      {isCompact ? (
        <strong className="product-order-price">{formatCurrency(variant.price)}</strong>
      ) : null}
      <QuantityStepper
        value={quantity}
        size={isCompact ? 'sm' : 'lg'}
        allowRemove
        disabled={isPending || !variant.is_available}
        onChange={(next) => void mutate(next)}
      />
    </div>
  );
}

interface MultiVariantOrderControlProps {
  product: Product;
  variants: ProductVariant[];
  mode: ProductOrderControlMode;
  className?: string;
  onAdded?: () => void;
}

function MultiVariantOrderControl({
  product,
  variants,
  mode,
  className = '',
  onAdded,
}: MultiVariantOrderControlProps) {
  const rootRef = useRef<HTMLDivElement>(null);
  const [expandedVariantId, setExpandedVariantId] = useState<number | null>(null);
  const cart = useCartStore((state) => state.cart);
  const pendingVariantIds = useCartStore((state) => state.pendingVariantIds);
  const setVariantQuantity = useCartStore((state) => state.setVariantQuantity);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const useCups = hasRecognizedSizeControls(product);
  const isCompact = mode === 'compact';

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
        onAdded?.();
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
      className={[
        'product-order-control',
        'product-size-controls',
        useCups ? 'is-cup-set' : 'is-generic-set',
        isCompact ? 'is-compact' : 'is-full',
        className,
      ]
        .filter(Boolean)
        .join(' ')}
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
                size={isCompact ? 'sm' : 'md'}
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
              useCups ? `is-cup-${cupKind}` : 'is-generic',
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
              {useCups ? (
                <span className="product-size-glyph" aria-hidden="true">
                  <CupIcon kind={cupKind} />
                  <span className="product-size-label">{shortLabel}</span>
                </span>
              ) : (
                <span className="product-size-generic-name">{variant.name}</span>
              )}
            </span>
            <span className="product-size-price">{formatCurrency(variant.price)}</span>
          </button>
        );
      })}
    </div>
  );
}
