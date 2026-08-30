import { Link } from 'react-router-dom';
import { Order, OrderPaymentInstructions } from '../../types/order';
import { formatCurrency } from '../../utils/format';

interface PaymentInstructionsCardProps {
  order: Order;
  payment: OrderPaymentInstructions | null;
  secondaryHref?: string;
  secondaryLabel?: string;
}

function toWhatsappHref(number: string, orderNumber: string): string {
  const normalized = number.replace(/[^\d+]/g, '');
  const message = encodeURIComponent(`Hi, I have completed the payment for order ${orderNumber}. Sharing the screenshot here.`);

  return `https://wa.me/${normalized.replace(/^\+/, '')}?text=${message}`;
}

export function PaymentInstructionsCard({
  order,
  payment,
  secondaryHref = '/orders',
  secondaryLabel = 'My Orders'
}: PaymentInstructionsCardProps) {
  const whatsappNumber = payment?.whatsapp_number?.trim() ?? '';

  return (
    <section className="payment-card">
      <div className="payment-card-header">
        <span className="auth-badge">Pending Payment</span>
        <h2>Complete your payment</h2>
        <p>Your order is reserved, and it will stay in `Pending Payment` until the Administrator confirms it.</p>
      </div>

      <div className="payment-meta-grid">
        <div>
          <span>Order number</span>
          <strong>{order.order_number}</strong>
        </div>
        <div>
          <span>Total amount</span>
          <strong>{formatCurrency(order.total_amount)}</strong>
        </div>
        <div>
          <span>Status</span>
          <strong>{order.status_label ?? 'Pending Payment'}</strong>
        </div>
        <div>
          <span>Payment to</span>
          <strong>{payment?.display_name ?? 'Coffee Cafe'}</strong>
        </div>
      </div>

      <div className="payment-detail-block">
        <span>UPI ID</span>
        <strong>{payment?.upi_id || 'Will be shared by the cafe team.'}</strong>
      </div>

      <div className="payment-detail-block">
        <span>Instructions</span>
        <p>{payment?.instructions || 'Complete the payment and share your screenshot with your order number on WhatsApp.'}</p>
      </div>

      <div className="payment-reminder">
        <i className="bi bi-whatsapp"></i>
        <div>
          <strong>Send your screenshot with the order number.</strong>
          <p>Payment is not auto-confirmed in the app. The cafe team will confirm it after reviewing your proof.</p>
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
            Send on WhatsApp
          </a>
        ) : null}
        <Link to={secondaryHref} className="btn btn-outline-dark btn-lg rounded-pill">
          {secondaryLabel}
        </Link>
      </div>
    </section>
  );
}
