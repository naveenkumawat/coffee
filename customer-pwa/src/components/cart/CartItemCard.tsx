import { CartItem } from '../../types/cart';
import { formatAddOnLabel } from '../../utils/addOns';
import { formatCurrency } from '../../utils/format';
import { ProductImage } from '../common/ProductImage';
import { QuantityStepper } from '../common/QuantityStepper';

interface CartItemCardProps {
  item: CartItem;
  isBusy?: boolean;
  onChangeQuantity: (quantity: number) => void;
  onRemove: () => void;
  onEdit?: () => void;
}

export function CartItemCard({
  item,
  isBusy = false,
  onChangeQuantity,
  onRemove,
  onEdit,
}: CartItemCardProps) {
  const unitPrice = item.unit_price ?? item.variant?.price ?? null;
  const baseUnit = item.base_unit_price ?? item.variant?.price ?? null;
  const addonLine = Number(item.addon_line_total ?? 0);
  const addOns = item.add_ons ?? [];
  const sizeLabel = [
    item.variant?.name,
    item.variant?.serving_size_value
      ? `${item.variant.serving_size_value}${item.variant.serving_size_unit ? ` ${item.variant.serving_size_unit}` : ''}`
      : null,
  ]
    .filter(Boolean)
    .join(' · ');

  return (
    <article className={`cart-item-card ${item.is_available ? '' : 'is-unavailable'}`.trim()}>
      <ProductImage
        name={item.product?.name ?? 'Coffee'}
        imagePath={item.product?.image_path}
        alt={item.product?.name ?? 'Cart item'}
        className="cart-item-image"
      />
      <div className="cart-item-body">
        <div className="cart-item-copy">
          <div className="cart-item-heading">
            <h2>{item.product?.name ?? 'Coffee item'}</h2>
            <strong className="cart-item-line-total">{formatCurrency(item.line_total ?? 0)}</strong>
          </div>
          <p className="cart-item-variant">{sizeLabel || 'Selected size'}</p>
          {addOns.length > 0 ? (
            <ul className="cart-item-addons">
              {addOns.map((addOn) => (
                <li key={`${addOn.add_on_id}-${addOn.quantity}`}>{formatAddOnLabel(addOn)}</li>
              ))}
            </ul>
          ) : null}
          {addonLine > 0 && baseUnit ? (
            <p className="cart-item-unit">
              {formatCurrency(baseUnit)} base
              {addonLine > 0 ? ` + ${formatCurrency(item.addon_line_total)} add-ons` : ''}
            </p>
          ) : unitPrice ? (
            <p className="cart-item-unit">{formatCurrency(unitPrice)} each</p>
          ) : null}
          {!item.is_available ? <span className="availability-chip">Unavailable — remove or refresh</span> : null}
          {isBusy ? <span className="availability-chip availability-chip-soft">Updating…</span> : null}
        </div>
        <div className="cart-item-footer">
          <QuantityStepper value={item.quantity} onChange={onChangeQuantity} disabled={isBusy || !item.is_available} />
          <div className="cart-item-actions">
            {onEdit ? (
              <button
                type="button"
                className="cart-item-edit"
                onClick={onEdit}
                disabled={isBusy}
                aria-label={`Edit ${item.product?.name ?? 'item'}`}
              >
                <i className="bi bi-sliders" aria-hidden="true"></i>
                <span>Edit</span>
              </button>
            ) : null}
            <button
              type="button"
              className="cart-item-remove"
              onClick={onRemove}
              disabled={isBusy}
              aria-label={`Remove ${item.product?.name ?? 'item'}`}
            >
              <i className="bi bi-trash3" aria-hidden="true"></i>
              <span>Remove</span>
            </button>
          </div>
        </div>
      </div>
    </article>
  );
}
