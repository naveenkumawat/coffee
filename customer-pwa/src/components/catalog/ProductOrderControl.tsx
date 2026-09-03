import { useState } from 'react';
import { Product } from '../../types/catalog';
import { formatCurrency } from '../../utils/format';
import {
  getProductVariants,
  isProductUnavailable,
  startingPrice,
} from '../../utils/productActions';
import { ProductConfiguredPayload, ProductCustomizationSheet } from './ProductCustomizationSheet';

export type ProductOrderControlMode = 'compact' | 'full';

export type ProductOrderPayload = ProductConfiguredPayload;

export type ProductOrderHandler = {
  add: (payload: ProductOrderPayload) => Promise<void>;
};

interface ProductOrderControlProps {
  product: Product;
  mode?: ProductOrderControlMode;
  className?: string;
  /** Fired after a successful first add (0 → positive quantity). */
  onAdded?: () => void;
  /** When set, skips cartStore and routes adds through this handler. */
  orderHandler?: ProductOrderHandler;
  sheetCtaLabel?: string;
}

/**
 * Single ordering control for cards, sheets, and detail pages.
 * Always opens the shared customization sheet — never mutates cart inline.
 */
export function ProductOrderControl({
  product,
  mode = 'compact',
  className = '',
  onAdded,
  orderHandler,
  sheetCtaLabel,
}: ProductOrderControlProps) {
  const variants = getProductVariants(product);
  const unavailable = isProductUnavailable(product);
  const [open, setOpen] = useState(false);

  if (unavailable || variants.length === 0) {
    return (
      <span className={`product-order-control is-disabled ${className}`.trim()}>Unavailable</span>
    );
  }

  const isCompact = mode === 'compact';
  const price = startingPrice(product);
  const destination = orderHandler ? 'order' : 'cart';

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
          aria-label={`Customize and add ${product.name} to ${destination}`}
          title={`Customize and add ${product.name}`}
          onClick={() => setOpen(true)}
        >
          {isCompact ? (
            <i className="bi bi-bag-plus" aria-hidden="true"></i>
          ) : (
            <>
              <i className="bi bi-bag-plus" aria-hidden="true"></i>
              <span>{sheetCtaLabel ?? (orderHandler ? 'Add to order' : 'Add to cart')}</span>
            </>
          )}
        </button>
      </div>

      <ProductCustomizationSheet
        product={product}
        open={open}
        onClose={() => setOpen(false)}
        onSaved={onAdded}
        submitMode={orderHandler ? 'callback' : 'cart'}
        onSubmitConfigured={orderHandler?.add}
        ctaLabel={sheetCtaLabel ?? (orderHandler ? 'Add to order' : undefined)}
      />
    </>
  );
}
