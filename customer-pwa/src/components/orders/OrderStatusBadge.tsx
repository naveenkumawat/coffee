import { statusTone } from '../../utils/orders';

interface OrderStatusBadgeProps {
  status: string | null | undefined;
  label?: string | null;
  className?: string;
}

export function OrderStatusBadge({ status, label, className = '' }: OrderStatusBadgeProps) {
  const tone = statusTone(status);
  const text = label?.trim() || 'Order';

  return (
    <span className={`status-badge is-${tone} ${className}`.trim()}>
      {text}
    </span>
  );
}
