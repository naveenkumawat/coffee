import { Link } from 'react-router-dom';
import { formatAddOnLabel } from '../../utils/addOns';
import { formatCurrency } from '../../utils/format';
import { ProductImage } from '../common/ProductImage';

interface CheckoutItemAddOn {
  name: string | null;
  quantity: number;
}

interface CheckoutItemCardProps {
  name: string;
  subtitle: string;
  detail?: string | null;
  imageName?: string | null;
  imagePath?: string | null;
  quantity: number;
  unitPrice?: string | number | null;
  amount: string | number | null | undefined;
  addOns?: CheckoutItemAddOn[] | null;
  editHref?: string | null;
  compact?: boolean;
}

export function CheckoutItemCard({
  name,
  subtitle,
  detail,
  imageName,
  imagePath,
  quantity,
  unitPrice,
  amount,
  addOns = null,
  editHref = null,
  compact = false,
}: CheckoutItemCardProps) {
  const qtyPrice =
    unitPrice !== null && unitPrice !== undefined && unitPrice !== ''
      ? `${quantity} × ${formatCurrency(unitPrice)}`
      : `Qty ${quantity}`;
  const visibleAddOns = (addOns ?? []).filter((addOn) => (addOn.name?.trim() || addOn.quantity > 0));

  return (
    <article className={['checkout-item-card', compact ? 'is-compact' : ''].filter(Boolean).join(' ')}>
      {!compact ? (
        <ProductImage
          name={imageName ?? name}
          imagePath={imagePath ?? null}
          alt={name}
          className="checkout-item-image"
          fit="cover"
        />
      ) : null}
      <div className="checkout-item-body">
        <div>
          <h2>{name}</h2>
          {subtitle ? <p>{subtitle}</p> : null}
          {visibleAddOns.length > 0 ? (
            <ul className="checkout-item-addons">
              {visibleAddOns.map((addOn, index) => (
                <li key={`${addOn.name ?? 'addon'}-${addOn.quantity}-${index}`}>
                  {formatAddOnLabel(addOn)}
                </li>
              ))}
            </ul>
          ) : null}
          {!compact && detail ? <p>{detail}</p> : null}
          {editHref ? (
            <Link to={editHref} className="link-button checkout-item-edit">
              Edit in cart
            </Link>
          ) : null}
        </div>
        <div className="checkout-item-footer">
          <span>{qtyPrice}</span>
          <strong>{formatCurrency(amount ?? 0)}</strong>
        </div>
      </div>
    </article>
  );
}
