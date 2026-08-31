import { ChangeEvent, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { getApiBaseUrl } from '../../api/client';
import { uploadPaymentProof } from '../../api/orders';
import { ApiError } from '../../api/client';
import { Order, OrderPaymentInstructions } from '../../types/order';
import { copyTextToClipboard } from '../../utils/clipboard';
import { formatCurrency } from '../../utils/format';
import { resolveCatalogMediaUrl } from '../../utils/images';
import { isCashPayment, isDineInOrder, isPendingPayment } from '../../utils/orders';
import { useToastStore } from '../../stores/toastStore';
import { OrderStatusBadge } from '../orders/OrderStatusBadge';

interface PaymentInstructionsCardProps {
  order: Order;
  payment: OrderPaymentInstructions | null;
  secondaryHref?: string;
  secondaryLabel?: string;
  showSecondaryAction?: boolean;
  onOrderUpdated?: (order: Order) => void;
}

type CopyField = 'order' | 'upi' | 'phone';

function toWhatsappHref(number: string, orderNumber: string): string {
  const normalized = number.replace(/[^\d+]/g, '');
  const message = encodeURIComponent(
    `Hi, I have completed the payment for order ${orderNumber}. Sharing the screenshot here.`,
  );

  return `https://wa.me/${normalized.replace(/^\+/, '')}?text=${message}`;
}

function successToastForField(field: CopyField): string {
  switch (field) {
    case 'upi':
      return 'UPI ID copied';
    case 'phone':
      return 'Phone number copied';
    default:
      return 'Order number copied';
  }
}

function cashStatusCopy(order: Order): { badge: string; title: string; body: string } {
  const cashReceived = order.payment_status === 'confirmed' || Boolean(order.cash_received_at);

  if (cashReceived) {
    return {
      badge: 'Paid · Cash',
      title: 'Cash received',
      body: 'The cafe has marked your cash payment as received.',
    };
  }

  if (isDineInOrder(order)) {
    return {
      badge: 'Cash',
      title: 'Pay at the cafe',
      body: `Pay ${formatCurrency(order.total_amount)} in cash at your table / the cafe. No payment screenshot is needed.`,
    };
  }

  return {
    badge: 'Cash at Pickup',
    title: 'Pay when collecting',
    body: `Your order has been placed. Pay ${formatCurrency(order.total_amount)} in cash when you collect it.`,
  };
}

export function PaymentInstructionsCard({
  order,
  payment,
  secondaryHref = '/orders',
  secondaryLabel = 'Track order',
  showSecondaryAction = true,
  onOrderUpdated,
}: PaymentInstructionsCardProps) {
  const whatsappNumber = payment?.whatsapp_number?.trim() ?? '';
  const upiId = payment?.upi_id?.trim() ?? '';
  const paymentPhone = payment?.phone?.trim() ?? '';
  const qrPath = payment?.qr_image_path?.trim() ?? '';
  const qrSrc = qrPath ? resolveCatalogMediaUrl(qrPath, '') : '';
  const instructions = payment?.instructions?.trim() ?? '';
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const [copiedField, setCopiedField] = useState<CopyField | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const proof = order.payment_proof;
  const awaitingReview = order.payment_status === 'awaiting_review' && Boolean(proof?.uploaded);
  const rejected = order.payment_status === 'rejected';
  const paymentConfirmed =
    order.payment_status === 'confirmed' ||
    (order.status !== null &&
      !isPendingPayment(order.status) &&
      order.payment_status !== 'awaiting_review' &&
      order.payment_status !== 'rejected' &&
      order.payment_status !== 'pending');
  const canUpload =
    !isCashPayment(order) &&
    Boolean(proof?.can_upload ?? isPendingPayment(order.status)) &&
    !paymentConfirmed;

  async function handleCopy(field: CopyField, value: string): Promise<void> {
    if (!value.trim()) {
      return;
    }

    const ok = await copyTextToClipboard(value);

    if (ok) {
      setCopiedField(field);
      toastSuccess(successToastForField(field));
      window.setTimeout(() => setCopiedField(null), 1600);
    } else {
      toastError('Could not copy. Please copy manually.');
    }
  }

  async function handleFileChange(event: ChangeEvent<HTMLInputElement>): Promise<void> {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file || isUploading) {
      return;
    }

    setIsUploading(true);
    setUploadError(null);

    try {
      const response = await uploadPaymentProof(order.id, file);
      onOrderUpdated?.(response.data);
      toastSuccess('Payment screenshot uploaded');
    } catch (error) {
      const message =
        error instanceof ApiError
          ? error.errors.payment_proof?.[0] ?? error.message
          : 'Unable to upload payment screenshot.';
      setUploadError(message);
      toastError(message);
    } finally {
      setIsUploading(false);
    }
  }

  if (isCashPayment(order)) {
    const cashCopy = cashStatusCopy(order);

    return (
      <section
        className={[
          'payment-card motion-enter',
          order.payment_status === 'confirmed' ? 'payment-card-confirmed' : '',
        ]
          .filter(Boolean)
          .join(' ')}
        aria-live="polite"
      >
        <div className="payment-card-header">
          <OrderStatusBadge
            status={order.payment_status === 'confirmed' ? 'payment_confirmed' : 'pending_payment'}
            label={cashCopy.badge}
          />
          <h2>{cashCopy.title}</h2>
          <p>{cashCopy.body}</p>
        </div>
        <div className="payment-meta-grid">
          <div>
            <span>Order number</span>
            <strong className="payment-order-number user-select-text">{order.order_number}</strong>
          </div>
          <div>
            <span>Amount due</span>
            <strong className="payment-amount">{formatCurrency(order.total_amount)}</strong>
          </div>
          <div>
            <span>Payment</span>
            <strong>
              {order.payment_method_label ??
                (isDineInOrder(order) ? 'Cash — Pay at Cafe' : 'Cash — Pay at Pickup')}
            </strong>
          </div>
        </div>
        {showSecondaryAction ? (
          <div className="payment-actions">
            <Link to={secondaryHref} className="btn btn-primary btn-lg rounded-pill">
              {secondaryLabel}
            </Link>
          </div>
        ) : null}
      </section>
    );
  }

  if (paymentConfirmed) {
    return (
      <section className="payment-card payment-card-confirmed motion-enter" aria-live="polite">
        <div className="payment-card-header">
          <OrderStatusBadge status="payment_confirmed" label="Payment confirmed" />
          <h2>Payment confirmed</h2>
          <p>The cafe has confirmed your payment. Track your order for preparation updates.</p>
        </div>
        {showSecondaryAction ? (
          <div className="payment-actions">
            <Link to={secondaryHref} className="btn btn-primary btn-lg rounded-pill">
              {secondaryLabel}
            </Link>
          </div>
        ) : null}
      </section>
    );
  }

  return (
    <section className="payment-card motion-enter">
      <div className="payment-card-header">
        <OrderStatusBadge
          status="pending_payment"
          label={awaitingReview ? 'Awaiting review' : rejected ? 'Replacement needed' : 'Pending Payment'}
        />
        <h2>Payment details</h2>
        <p>
          {awaitingReview
            ? 'Payment proof submitted. Waiting for cafe confirmation.'
            : rejected
              ? 'Please upload a clearer payment screenshot.'
              : 'Pay after placing your order — then upload your screenshot.'}
        </p>
      </div>

      <div className="payment-meta-grid">
        <div className="payment-meta-row">
          <div>
            <span>Order number</span>
            <strong className="payment-order-number user-select-text">{order.order_number}</strong>
          </div>
          <button
            type="button"
            className="btn btn-outline-dark btn-sm rounded-pill payment-copy-btn"
            aria-label="Copy order number"
            onClick={() => void handleCopy('order', order.order_number)}
          >
            {copiedField === 'order' ? 'Copied' : 'Copy'}
          </button>
        </div>
        <div>
          <span>Amount due</span>
          <strong className="payment-amount">{formatCurrency(order.total_amount)}</strong>
        </div>
        {payment?.display_name ? (
          <div>
            <span>Pay to</span>
            <strong>{payment.display_name}</strong>
          </div>
        ) : null}
      </div>

      {upiId ? (
        <div className="payment-detail-block payment-copy-row">
          <div className="payment-copy-value">
            <span>UPI</span>
            <strong className="user-select-text">{upiId}</strong>
          </div>
          <button
            type="button"
            className="btn btn-outline-dark btn-sm rounded-pill payment-copy-btn"
            aria-label="Copy UPI ID"
            onClick={() => void handleCopy('upi', upiId)}
          >
            {copiedField === 'upi' ? 'Copied' : 'Copy'}
          </button>
        </div>
      ) : null}

      {paymentPhone ? (
        <div className="payment-detail-block payment-copy-row">
          <div className="payment-copy-value">
            <span>Phone</span>
            <strong className="user-select-text">{paymentPhone}</strong>
          </div>
          <button
            type="button"
            className="btn btn-outline-dark btn-sm rounded-pill payment-copy-btn"
            aria-label="Copy payment phone number"
            onClick={() => void handleCopy('phone', paymentPhone)}
          >
            {copiedField === 'phone' ? 'Copied' : 'Copy'}
          </button>
        </div>
      ) : null}

      {qrSrc ? (
        <div className="payment-detail-block payment-qr-block">
          <span>Scan QR</span>
          <img src={qrSrc} alt="Payment QR code" className="payment-qr-image" />
        </div>
      ) : null}

      {instructions ? (
        <div className="payment-detail-block">
          <span>Instructions</span>
          <p>{instructions}</p>
        </div>
      ) : (
        <div className="payment-detail-block">
          <span>Instructions</span>
          <p>Pay the amount due via UPI or phone, then upload your screenshot with the order number.</p>
        </div>
      )}

      {rejected && proof?.rejection_notes ? (
        <div className="payment-reminder" role="status">
          <i className="bi bi-exclamation-triangle" aria-hidden="true"></i>
          <div>
            <strong>Replacement requested</strong>
            <p>{proof.rejection_notes}</p>
          </div>
        </div>
      ) : null}

      {awaitingReview ? (
        <div className="payment-reminder" role="status">
          <i className="bi bi-hourglass-split" aria-hidden="true"></i>
          <div>
            <strong>Payment proof submitted</strong>
            <p>
              Waiting for cafe confirmation
              {proof?.uploaded_at ? ` · uploaded ${new Date(proof.uploaded_at).toLocaleString()}` : ''}.
            </p>
          </div>
        </div>
      ) : null}

      <div className="payment-actions">
        {awaitingReview && showSecondaryAction ? (
          <Link to={secondaryHref} className="btn btn-primary btn-lg rounded-pill">
            {secondaryLabel}
          </Link>
        ) : null}

        {canUpload ? (
          <>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              className="visually-hidden"
              onChange={(event) => void handleFileChange(event)}
            />
            <button
              type="button"
              className={
                awaitingReview
                  ? 'btn btn-outline-dark btn-lg rounded-pill'
                  : 'btn btn-primary btn-lg rounded-pill'
              }
              disabled={isUploading}
              aria-busy={isUploading}
              onClick={() => fileInputRef.current?.click()}
            >
              {isUploading
                ? 'Uploading…'
                : rejected || awaitingReview || proof?.uploaded
                  ? 'Replace payment screenshot'
                  : 'Upload payment screenshot'}
            </button>
            {uploadError ? (
              <p className="form-error-text" role="alert">
                {uploadError}
              </p>
            ) : null}
          </>
        ) : null}

        {!awaitingReview && showSecondaryAction ? (
          <Link
            to={secondaryHref}
            className={canUpload ? 'btn btn-outline-dark btn-lg rounded-pill' : 'btn btn-primary btn-lg rounded-pill'}
          >
            {secondaryLabel}
          </Link>
        ) : null}

        {whatsappNumber && canUpload && !awaitingReview ? (
          <a
            href={toWhatsappHref(whatsappNumber, order.order_number)}
            target="_blank"
            rel="noreferrer"
            className="btn btn-outline-dark rounded-pill payment-action-secondary"
          >
            Send on WhatsApp
          </a>
        ) : null}

        {proof?.uploaded ? (
          <a
            href={`${getApiBaseUrl()}/orders/${order.id}/payment-proof`}
            target="_blank"
            rel="noreferrer"
            className="link-button payment-view-proof"
          >
            View uploaded screenshot
          </a>
        ) : null}
      </div>
    </section>
  );
}
