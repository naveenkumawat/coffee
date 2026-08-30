import { formatCurrency } from '../../utils/format';
import { ProductImage } from '../common/ProductImage';

interface CheckoutItemCardProps {
  name: string;
  subtitle: string;
  detail?: string | null;
  imageName?: string | null;
  imagePath?: string | null;
  quantity: number;
  amount: string | number | null | undefined;
}

export function CheckoutItemCard({
  name,
  subtitle,
  detail,
  imageName,
  imagePath,
  quantity,
  amount,
}: CheckoutItemCardProps) {
  return (
    <article className="checkout-item-card">
      <ProductImage
        name={imageName ?? name}
        imagePath={imagePath ?? null}
        alt={name}
        className="checkout-item-image"
        fit="cover"
      />
      <div className="checkout-item-body">
        <div>
          <h2>{name}</h2>
          <p>{subtitle}</p>
          {detail ? <p>{detail}</p> : null}
        </div>
        <div className="checkout-item-footer">
          <span>Qty {quantity}</span>
          <strong>{formatCurrency(amount ?? 0)}</strong>
        </div>
      </div>
    </article>
  );
}
