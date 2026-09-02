import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ApiError } from '../../api/client';
import { useProductOverlay } from '../../hooks/useProductOverlay';
import { useCartStore } from '../../stores/cartStore';
import { useToastStore } from '../../stores/toastStore';
import { CartAddOnSelection } from '../../types/cart';
import { Product, ProductAddOn, ProductVariant } from '../../types/catalog';
import { addonUnitTotal, buildCartAddOnDisplay, canonicalizeAddOns } from '../../utils/addOns';
import { buildCartDisplayFromProduct } from '../../utils/cartDisplay';
import { formatCurrency } from '../../utils/format';
import { getPreferredVariant, getProductVariants } from '../../utils/productActions';
import { QuantityStepper } from '../common/QuantityStepper';

export type ProductConfiguredPayload = {
  product_variant_id: number;
  quantity: number;
  add_ons: CartAddOnSelection[];
};

interface ProductCustomizationSheetProps {
  product: Product;
  open: boolean;
  onClose: () => void;
  initialVariantId?: number | null;
  initialAddOns?: CartAddOnSelection[] | null;
  initialQuantity?: number;
  cartItemId?: number | null;
  onSaved?: () => void;
  submitMode?: 'cart' | 'callback';
  onSubmitConfigured?: (payload: ProductConfiguredPayload) => Promise<void>;
  ctaLabel?: string;
}

type AddOnQtyMap = Record<number, number>;

function toQtyMap(addOns: CartAddOnSelection[] | null | undefined, catalog: ProductAddOn[]): AddOnQtyMap {
  const map: AddOnQtyMap = {};
  const selected = canonicalizeAddOns(addOns);

  for (const addOn of catalog) {
    map[addOn.id] = selected.find((row) => row.add_on_id === addOn.id)?.quantity ?? 0;
  }

  return map;
}

function fromQtyMap(map: AddOnQtyMap): CartAddOnSelection[] {
  return canonicalizeAddOns(
    Object.entries(map).map(([add_on_id, quantity]) => ({
      add_on_id: Number(add_on_id),
      quantity,
    })),
  );
}

export function ProductCustomizationSheet({
  product,
  open,
  onClose,
  initialVariantId = null,
  initialAddOns = null,
  initialQuantity = 1,
  cartItemId = null,
  onSaved,
  submitMode = 'cart',
  onSubmitConfigured,
  ctaLabel,
}: ProductCustomizationSheetProps) {
  const titleId = useId();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const variants = getProductVariants(product);
  const catalogAddOns = product.add_ons ?? [];
  const isEditing = cartItemId != null;
  const isCallbackMode = submitMode === 'callback';
  const addItem = useCartStore((state) => state.addItem);
  const replaceConfiguredItem = useCartStore((state) => state.replaceConfiguredItem);
  const isVariantPending = useCartStore((state) => state.isVariantPending);
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);

  const [selectedVariantId, setSelectedVariantId] = useState<number | null>(null);
  const [addOnQty, setAddOnQty] = useState<AddOnQtyMap>({});
  const [quantity, setQuantity] = useState(1);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }

    const nextVariants = getProductVariants(product);
    const nextAddOns = product.add_ons ?? [];
    const preferred =
      nextVariants.find((variant) => variant.id === initialVariantId) ??
      getPreferredVariant(product) ??
      nextVariants[0] ??
      null;

    setSelectedVariantId(preferred?.id ?? null);
    setAddOnQty(toQtyMap(initialAddOns, nextAddOns));
    setQuantity(Math.max(1, initialQuantity));
    setIsSaving(false);
  }, [open, product, initialVariantId, initialAddOns, initialQuantity]);

  useProductOverlay({
    open,
    historyKey: `product-customize:${product.id}:${cartItemId ?? 'new'}`,
    onClose,
    focusRef: closeButtonRef,
  });

  const selectedVariant: ProductVariant | null =
    variants.find((variant) => variant.id === selectedVariantId) ?? null;
  const selectedAddOns = useMemo(() => fromQtyMap(addOnQty), [addOnQty]);
  const previewUnit =
    Number(selectedVariant?.price ?? 0) +
    addonUnitTotal(buildCartAddOnDisplay(catalogAddOns, selectedAddOns));
  const previewTotal = previewUnit * quantity;
  const pending = selectedVariant
    ? (!isCallbackMode && isVariantPending(selectedVariant.id)) || isSaving
    : isSaving;

  const primaryLabel =
    ctaLabel ??
    (isEditing ? 'Update cart' : isCallbackMode ? 'Add to order' : 'Add to cart');

  async function handleSubmit(): Promise<void> {
    if (!selectedVariant || !selectedVariant.is_available || pending) {
      return;
    }

    setIsSaving(true);

    try {
      const payload: ProductConfiguredPayload = {
        product_variant_id: selectedVariant.id,
        quantity,
        add_ons: selectedAddOns,
      };

      if (isCallbackMode) {
        if (!onSubmitConfigured) {
          throw new Error('Missing onSubmitConfigured handler.');
        }

        await onSubmitConfigured(payload);
        toastSuccess('Added to order');
      } else {
        const display = buildCartDisplayFromProduct(product, selectedVariant, selectedAddOns);
        const cartPayload = {
          ...payload,
          display,
        };

        if (isEditing && cartItemId != null) {
          await replaceConfiguredItem(cartItemId, cartPayload);
          toastSuccess('Cart updated');
        } else {
          await addItem(cartPayload);
          toastSuccess('Added to cart');
        }
      }

      onSaved?.();
      onClose();
    } catch (error) {
      toastError(
        error instanceof ApiError
          ? error.message
          : isCallbackMode
            ? 'Unable to add to order.'
            : 'Unable to update your cart.',
      );
    } finally {
      setIsSaving(false);
    }
  }

  if (!open || typeof document === 'undefined') {
    return null;
  }

  return createPortal(
    <div className="product-overlay product-overlay-customize is-open" role="presentation">
      <button
        type="button"
        className="product-overlay-backdrop"
        aria-label="Close customization"
        onClick={onClose}
      />

      <div
        className="product-overlay-panel product-overlay-panel-compact"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="product-overlay-handle" aria-hidden="true" />

        <div className="product-overlay-header product-overlay-header-compact">
          <button
            ref={closeButtonRef}
            type="button"
            className="product-overlay-close"
            aria-label="Close customization"
            onClick={onClose}
          >
            <i className="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </div>

        <div className="product-overlay-scroll">
          <div className="product-overlay-body product-overlay-body-compact">
            <div className="product-overlay-heading">
              <h2 id={titleId} className="product-overlay-title">
                {product.name}
              </h2>
              <strong className="product-overlay-price">{formatCurrency(previewTotal)}</strong>
            </div>

            {variants.length > 1 ? (
              <div className="product-overlay-block">
                <span className="product-overlay-label">Size</span>
                <div className="quick-add-variants" role="group" aria-label="Choose size">
                  {variants.map((variant) => {
                    const selected = variant.id === selectedVariantId;
                    const disabled = !variant.is_available;

                    return (
                      <button
                        key={variant.id}
                        type="button"
                        className={[
                          'quick-add-variant',
                          selected ? 'is-selected' : '',
                          disabled ? 'is-disabled' : '',
                        ]
                          .filter(Boolean)
                          .join(' ')}
                        disabled={disabled}
                        aria-pressed={selected}
                        onClick={() => setSelectedVariantId(variant.id)}
                      >
                        <span className="quick-add-variant-name">{variant.name}</span>
                        <span className="quick-add-variant-meta">
                          {variant.serving_size.label ||
                            `${variant.serving_size.value}${variant.serving_size.unit ? ` ${variant.serving_size.unit}` : ''}`}
                        </span>
                        <span className="quick-add-variant-price">{formatCurrency(variant.price)}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
            ) : null}

            {catalogAddOns.length > 0 ? (
              <div className="product-overlay-block">
                <span className="product-overlay-label">Optional add-ons</span>
                <div className="product-addon-list" role="group" aria-label="Add-ons">
                  {catalogAddOns.map((addOn) => {
                    const qty = addOnQty[addOn.id] ?? 0;
                    const isSingle = addOn.max_quantity <= 1;

                    return (
                      <div key={addOn.id} className="product-addon-row">
                        <div className="product-addon-copy">
                          {isSingle ? (
                            <label className="product-addon-check">
                              <input
                                type="checkbox"
                                checked={qty > 0}
                                onChange={(event) => {
                                  setAddOnQty((current) => ({
                                    ...current,
                                    [addOn.id]: event.target.checked ? 1 : 0,
                                  }));
                                }}
                              />
                              <span>
                                <strong>{addOn.name}</strong>
                                {addOn.description ? <small>{addOn.description}</small> : null}
                              </span>
                            </label>
                          ) : (
                            <>
                              <strong>{addOn.name}</strong>
                              {addOn.description ? <small>{addOn.description}</small> : null}
                            </>
                          )}
                        </div>
                        <div className="product-addon-meta">
                          <span>{formatCurrency(addOn.price)}</span>
                          {!isSingle ? (
                            <QuantityStepper
                              value={qty}
                              min={0}
                              max={addOn.max_quantity}
                              size="sm"
                              allowRemove
                              onChange={(next) => {
                                setAddOnQty((current) => ({
                                  ...current,
                                  [addOn.id]: Math.min(addOn.max_quantity, Math.max(0, next)),
                                }));
                              }}
                            />
                          ) : null}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            ) : null}

            <div className="product-overlay-block product-customize-qty">
              <span className="product-overlay-label">Quantity</span>
              <QuantityStepper value={quantity} size="lg" onChange={setQuantity} />
            </div>
          </div>
        </div>

        <div className="product-overlay-footer product-overlay-footer-compact">
          <div className="product-overlay-footer-meta">
            <span>{isEditing ? 'Updating cart' : 'Ready to add'}</span>
            <strong>{formatCurrency(previewTotal)}</strong>
          </div>
          <button
            type="button"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={!selectedVariant?.is_available || pending}
            aria-busy={pending}
            onClick={() => void handleSubmit()}
          >
            {pending
              ? isEditing
                ? 'Updating…'
                : 'Adding…'
              : primaryLabel}
          </button>
        </div>
      </div>
    </div>,
    document.body,
  );
}
