import { CartDiscount } from '../../types/cart';
import { formatCurrency } from '../../utils/format';
import { discountAmount, hasDiscountSavings } from '../../utils/discounts';

export interface TaxBreakdownValue {
  enabled: boolean;
  label: string;
  percent: string;
  inclusive: boolean;
  taxable_amount: string;
  amount: string;
}

interface OrderTaxBreakdownProps {
  subtotal: string | number;
  total: string | number;
  tax?: TaxBreakdownValue | null;
  discounts?: Array<Pick<CartDiscount, 'name' | 'amount' | 'code'> | { name: string; amount: string; code?: string | null }>;
  discountTotal?: string | number | null;
  totalLabel?: string;
  estimateNote?: string | null;
  showSavingsNote?: boolean;
}

function formatPercent(percent: string): string {
  const trimmed = percent.replace(/\.?0+$/, '');
  return trimmed.length > 0 ? trimmed : '0';
}

export function OrderTaxBreakdown({
  subtotal,
  total,
  tax,
  discounts = [],
  discountTotal = null,
  totalLabel = 'Total',
  estimateNote = null,
  showSavingsNote = true,
}: OrderTaxBreakdownProps) {
  const enabled = Boolean(tax?.enabled);
  const lines = discounts.filter((discount) => discountAmount(discount.amount) > 0);
  const showDiscountTotal =
    lines.length === 0 && hasDiscountSavings(discountTotal);

  return (
    <div className="summary-card checkout-summary-grid">
      <div>
        <span>{enabled && tax?.inclusive ? 'Price' : 'Subtotal'}</span>
        <strong>{formatCurrency(subtotal)}</strong>
      </div>
      {lines.map((discount, index) => (
        <div key={`${discount.name}-${discount.code ?? index}`} className="summary-discount-row">
          <span>{discount.name}</span>
          <strong>−{formatCurrency(discount.amount)}</strong>
        </div>
      ))}
      {showDiscountTotal ? (
        <div className="summary-discount-row">
          <span>Discount</span>
          <strong>−{formatCurrency(discountTotal)}</strong>
        </div>
      ) : null}
      {enabled && tax ? (
        <div>
          <span>
            {tax.inclusive
              ? `Includes ${tax.label} (${formatPercent(tax.percent)}%)`
              : `${tax.label} (${formatPercent(tax.percent)}%)`}
          </span>
          <strong>{formatCurrency(tax.amount)}</strong>
        </div>
      ) : null}
      <div className="cart-summary-total">
        <span>{totalLabel}</span>
        <strong>{formatCurrency(total)}</strong>
      </div>
      {showSavingsNote && hasDiscountSavings(discountTotal) ? (
        <p className="summary-savings">You saved {formatCurrency(discountTotal)}</p>
      ) : null}
      {estimateNote ? <p className="summary-warning">{estimateNote}</p> : null}
    </div>
  );
}
