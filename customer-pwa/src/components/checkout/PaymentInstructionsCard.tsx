import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Order, OrderPaymentInstructions } from '../../types/order';
import { formatCurrency } from '../../utils/format';
import { useToastStore } from '../../stores/toastStore';
import { OrderStatusBadge } from '../orders/OrderStatusBadge';

interface PaymentInstructionsCardProps {
  order: Order;
  payment: OrderPaymentInstructions | null;
  secondaryHref?: string;
  secondaryLabel?: string;
  showSecondaryAction?: boolean;
}

function toWhatsappHref(number: string, orderNumber: string): string {
  const normalized = number.replace(/[^\d+]/g, '');
  const message = encodeURIComponent(
    `Hi, I have completed the payment for order ${orderNumber}. Sharing the screenshot here.`,
  );

  return `https://wa.me/${normalized.replace(/^\+/, '')}?text=${message}`;
}

async function copyText(value: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(value);

    return true;
  } catch {
    return false;
  }
}

export function PaymentInstructionsCard({
  order,
  payment,
  secondaryHref = '/orders',
  secondaryLabel = 'Track order',
  showSecondaryAction = true,
}: PaymentInstructionsCardProps) {
  const whatsappNumber = payment?.whatsapp_number?.trim() ?? '';
  const upiId = payment?.upi_id?.trim() ?? '';
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const [copiedField, setCopiedField] = useState<string | null>(null);

  async function handleCopy(label: string, value: string): Promise<void> {
    const ok = await copyText(value);

    if (ok) {
      setCopiedField(label);
      toastSuccess(`${label} copied`);
      window.setTimeout(() => setCopiedField(null), 1600);
    } else {
      toastError(`Unable to copy ${label.toLowerCase()}`);
    }
  }

  return (
    <section className="payment-card motion-enter">
      <div className="payment-card-header">
        <OrderStatusBadge status="pending_payment" label="Pending Payment" />
        <h2>Pay to confirm your order</h2>
        <p>Pay via UPI, then send your screenshot with the order number on WhatsApp.</p>
      </div>

      <div className="payment-meta-grid">
        <div className="payment-meta-row">
          <div>
            <span>Order number</span>
            <strong className="payment-order-number">{order.order_number}</strong>
          </div>
          <button
            type="button"
            className="btn btn-outline-dark btn-sm rounded-pill"
            onClick={() => void handleCopy('Order number', order.order_number)}
          >
            {copiedField === 'Order number' ? 'Copied' : 'Copy'}
          </button>
        </div>
        <div>
          <span>Amount due</span>
          <strong className="payment-amount">{formatCurrency(order.total_amount)}</strong>
        </div>
        <div>
          <span>Pay to</span>
          <strong>{payment?.display_name ?? 'Coffee Cafe'}</strong>
        </div>
      </div>

      <div className="payment-detail-block payment-upi-block">
        <div>
          <span>UPI ID</span>
          <strong>{upiId || 'Will be shared by the cafe team.'}</strong>
        </div>
        {upiId ? (
          <button
            type="button"
            className="btn btn-outline-dark btn-sm rounded-pill"
            onClick={() => void handleCopy('UPI ID', upiId)}
          >
            {copiedField === 'UPI ID' ? 'Copied' : 'Copy UPI'}
          </button>
        ) : null}
      </div>

      <div className="payment-detail-block">
        <span>How to pay</span>
        <p>
          {payment?.instructions ||
            'Complete the UPI payment for the amount due, then share your screenshot with the order number on WhatsApp.'}
        </p>
      </div>

      <div className="payment-reminder">
        <i className="bi bi-whatsapp" aria-hidden="true"></i>
        <div>
          <strong>Send proof on WhatsApp</strong>
          <p>The cafe confirms payment manually — your order stays Pending Payment until then.</p>
        </div>
      </div>

      <div className="payment-actions">
        {whatsappNumber ? (
          <a
            href={toWhatsappHref(whatsappNumber, order.order_number)}
            target="_blank"
            rel="noreferrer"
            className="btn btn-primary btn-lg rounded-pill"
          >
            Send payment on WhatsApp
          </a>
        ) : null}
        {showSecondaryAction ? (
          <Link to={secondaryHref} className="btn btn-outline-dark btn-lg rounded-pill">
            {secondaryLabel}
          </Link>
        ) : null}
      </div>
    </section>
  );
}
