import { useState } from 'react';
import { useOrderingAddHandler } from '../../hooks/useOrderingAddHandler';
import { selectCartEnabled, useContentStore } from '../../stores/contentStore';
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
  const autoOrdering = useOrderingAddHandler();
  const cartEnabled = useContentStore((state) => selectCartEnabled(state.content, state.cartEnabled));
  const effectiveHandler = orderHandler ?? autoOrdering.orderHandler;
  const effectiveCta = sheetCtaLabel ?? autoOrdering.sheetCtaLabel;
  const browseOnly = !effectiveHandler && !cartEnabled;

  if (unavailable || variants.length === 0) {
    return (
      <span className={`product-order-control is-disabled ${className}`.trim()}>Unavailable</span>
    );
  }

  const isCompact = mode === 'compact';
  const price = startingPrice(product);
  const destination = effectiveHandler ? 'order' : 'cart';

  if (browseOnly && isCompact) {
    return price ? (
      <div className={`product-order-control is-browse is-compact ${className}`.trim()}>
        <strong className="product-order-price">{formatCurrency(price)}</strong>
      </div>
    ) : null;
  }

  return (
    <>
      <div
        className={`product-order-control is-customize ${isCompact ? 'is-compact' : 'is-full'} ${className}`.trim()}
      >
        {isCompact && price ? (
          <strong className="product-order-price">{formatCurrency(price)}</strong>
        ) : null}
        {browseOnly ? (
          <div className="product-browse-actions">
            <p className="product-overlay-note">Order at the counter</p>
            <button
              type="button"
              className="btn btn-outline-secondary btn-lg rounded-pill product-card-action"
              onClick={() => setOpen(true)}
            >
              View sizes & prices
            </button>
          </div>
        ) : (
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
                <span>{effectiveCta ?? (effectiveHandler ? 'Add to order' : 'Add to cart')}</span>
              </>
            )}
          </button>
        )}
      </div>

      <ProductCustomizationSheet
        product={product}
        open={open}
        onClose={() => setOpen(false)}
        onSaved={onAdded}
        submitMode={browseOnly ? 'browse' : effectiveHandler ? 'callback' : 'cart'}
        onSubmitConfigured={effectiveHandler?.add}
        ctaLabel={effectiveCta ?? (effectiveHandler ? 'Add to order' : undefined)}
      />
    </>
  );
}
