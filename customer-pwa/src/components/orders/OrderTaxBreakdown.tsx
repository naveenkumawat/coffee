import { formatCurrency } from '../../utils/format';

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
  totalLabel?: string;
  estimateNote?: string | null;
}

function formatPercent(percent: string): string {
  const trimmed = percent.replace(/\.?0+$/, '');
  return trimmed.length > 0 ? trimmed : '0';
}

export function OrderTaxBreakdown({
  subtotal,
  total,
  tax,
  totalLabel = 'Total',
  estimateNote = null,
}: OrderTaxBreakdownProps) {
  const enabled = Boolean(tax?.enabled);

  return (
    <div className="summary-card checkout-summary-grid">
      <div>
        <span>{enabled && tax?.inclusive ? 'Price' : 'Subtotal'}</span>
        <strong>{formatCurrency(subtotal)}</strong>
      </div>
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
      {estimateNote ? <p className="summary-warning">{estimateNote}</p> : null}
    </div>
  );
}
