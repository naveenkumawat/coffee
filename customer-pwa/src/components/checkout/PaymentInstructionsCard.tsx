import { ChangeEvent, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { getApiBaseUrl } from '../../api/client';
import { uploadPaymentProof } from '../../api/orders';
import { ApiError } from '../../api/client';
import { Order, OrderPaymentInstructions } from '../../types/order';
import { copyTextToClipboard } from '../../utils/clipboard';
import { formatCurrency } from '../../utils/format';
import { resolveCatalogMediaUrl } from '../../utils/images';
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
  const awaitingReview = order.payment_status === 'awaiting_review' && proof?.uploaded;
  const rejected = order.payment_status === 'rejected';
  const canUpload = proof?.can_upload ?? order.status === 'pending_payment';

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
            ? 'Screenshot received — the cafe will confirm payment shortly.'
            : rejected
              ? 'Please upload a clearer payment screenshot.'
              : 'Pay via UPI or the payment number, then upload your screenshot.'}
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
            <span>UPI ID</span>
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

      <div className="payment-detail-block">
        <span>Payment instructions</span>
        <p>
          {instructions ||
            'Complete the UPI payment for the amount due, then upload your screenshot with the order number.'}
        </p>
      </div>

      {rejected && proof?.rejection_notes ? (
        <div className="payment-reminder">
          <i className="bi bi-exclamation-triangle" aria-hidden="true"></i>
          <div>
            <strong>Replacement requested</strong>
            <p>{proof.rejection_notes}</p>
          </div>
        </div>
      ) : null}

      {awaitingReview ? (
        <div className="payment-reminder">
          <i className="bi bi-hourglass-split" aria-hidden="true"></i>
          <div>
            <strong>Uploaded — awaiting cafe review</strong>
            <p>
              Screenshot received
              {proof?.uploaded_at ? ` at ${new Date(proof.uploaded_at).toLocaleString()}` : ''}. You can replace it
              until payment is confirmed.
            </p>
          </div>
        </div>
      ) : null}

      {canUpload ? (
        <div className="payment-upload-block">
          <input
            ref={fileInputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            className="visually-hidden"
            onChange={(event) => void handleFileChange(event)}
          />
          <button
            type="button"
            className="btn btn-primary btn-lg rounded-pill"
            disabled={isUploading}
            aria-busy={isUploading}
            onClick={() => fileInputRef.current?.click()}
          >
            {isUploading
              ? 'Uploading…'
              : proof?.uploaded
                ? 'Replace payment screenshot'
                : 'Upload payment screenshot'}
          </button>
          {uploadError ? <p className="form-error-text">{uploadError}</p> : null}
        </div>
      ) : null}

      {whatsappNumber ? (
        <div className="payment-reminder">
          <i className="bi bi-whatsapp" aria-hidden="true"></i>
          <div>
            <strong>WhatsApp fallback</strong>
            <p>You can still send the screenshot on WhatsApp if upload is unavailable.</p>
          </div>
        </div>
      ) : null}

      <div className="payment-actions">
        {whatsappNumber ? (
          <a
            href={toWhatsappHref(whatsappNumber, order.order_number)}
            target="_blank"
            rel="noreferrer"
            className="btn btn-outline-dark btn-lg rounded-pill"
          >
            Send on WhatsApp
          </a>
        ) : null}
        {showSecondaryAction ? (
          <Link to={secondaryHref} className="btn btn-outline-dark btn-lg rounded-pill">
            {secondaryLabel}
          </Link>
        ) : null}
        {proof?.uploaded ? (
          <a
            href={`${getApiBaseUrl()}/orders/${order.id}/payment-proof`}
            target="_blank"
            rel="noreferrer"
            className="btn btn-outline-dark btn-lg rounded-pill"
          >
            View uploaded screenshot
          </a>
        ) : null}
      </div>
    </section>
  );
}
