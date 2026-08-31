import { formatCurrency } from '../../utils/format';
import { ProductImage } from '../common/ProductImage';

interface CheckoutItemCardProps {
  name: string;
  subtitle: string;
  detail?: string | null;
  imageName?: string | null;
  imagePath?: string | null;
  quantity: number;
  unitPrice?: string | number | null;
  amount: string | number | null | undefined;
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
  compact = false,
}: CheckoutItemCardProps) {
  const qtyPrice =
    unitPrice !== null && unitPrice !== undefined && unitPrice !== ''
      ? `${quantity} × ${formatCurrency(unitPrice)}`
      : `Qty ${quantity}`;

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
          {!compact && detail ? <p>{detail}</p> : null}
        </div>
        <div className="checkout-item-footer">
          <span>{qtyPrice}</span>
          <strong>{formatCurrency(amount ?? 0)}</strong>
        </div>
      </div>
    </article>
  );
}
