import { CartItem } from '../../types/cart';
import { formatCurrency } from '../../utils/format';
import { pickProductImage } from '../../utils/images';
import { QuantityStepper } from '../common/QuantityStepper';

interface CartItemCardProps {
  item: CartItem;
  isBusy?: boolean;
  onChangeQuantity: (quantity: number) => void;
  onRemove: () => void;
}

export function CartItemCard({ item, isBusy = false, onChangeQuantity, onRemove }: CartItemCardProps) {
  const image = pickProductImage(item.product?.name ?? 'Coffee', item.product?.image_path);

  return (
    <article className="cart-item-card">
      <img src={image} alt={item.product?.name ?? 'Cart item'} className="cart-item-image" loading="lazy" decoding="async" />
      <div className="cart-item-body">
        <div>
          <h2>{item.product?.name ?? 'Coffee item'}</h2>
          <p>{item.variant?.name} {item.variant?.serving_size_value} {item.variant?.serving_size_unit ?? ''}</p>
          {!item.is_available ? <span className="availability-chip">Needs refresh</span> : null}
          {isBusy ? <span className="availability-chip availability-chip-soft">Updating...</span> : null}
        </div>
        <div className="cart-item-footer">
          <QuantityStepper value={item.quantity} onChange={onChangeQuantity} disabled={isBusy} />
          <div className="text-end">
            <strong>{formatCurrency(item.line_total ?? 0)}</strong>
            <button type="button" className="link-button" onClick={onRemove} disabled={isBusy}>
              Remove
            </button>
          </div>
        </div>
      </div>
    </article>
  );
}
