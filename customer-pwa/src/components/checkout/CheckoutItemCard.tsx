import { formatCurrency } from '../../utils/format';
import { pickProductImage } from '../../utils/images';

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
  amount
}: CheckoutItemCardProps) {
  const image = pickProductImage(imageName ?? name, imagePath ?? null);

  return (
    <article className="checkout-item-card">
      <img src={image} alt={name} className="checkout-item-image" loading="lazy" decoding="async" />
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
