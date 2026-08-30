import { CartItem } from '../../types/cart';
import { formatCurrency } from '../../utils/format';
import { ProductImage } from '../common/ProductImage';
import { QuantityStepper } from '../common/QuantityStepper';

interface CartItemCardProps {
  item: CartItem;
  isBusy?: boolean;
  onChangeQuantity: (quantity: number) => void;
  onRemove: () => void;
}

export function CartItemCard({ item, isBusy = false, onChangeQuantity, onRemove }: CartItemCardProps) {
  const unitPrice = item.unit_price ?? item.variant?.price ?? null;
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
          {unitPrice ? <p className="cart-item-unit">{formatCurrency(unitPrice)} each</p> : null}
          {!item.is_available ? <span className="availability-chip">Unavailable — remove or refresh</span> : null}
          {isBusy ? <span className="availability-chip availability-chip-soft">Updating…</span> : null}
        </div>
        <div className="cart-item-footer">
          <QuantityStepper value={item.quantity} onChange={onChangeQuantity} disabled={isBusy || !item.is_available} />
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
    </article>
  );
}
