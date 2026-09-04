import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiError, getApiBaseUrl } from '../../api/client';
import { fetchOrder, submitPaymentTransactionId } from '../../api/orders';
import { initiateOrderPayment, verifyPaymentReturn } from '../../api/payments';
import { Order, OrderPaymentInstructions } from '../../types/order';
import { copyTextToClipboard } from '../../utils/clipboard';
import { formatCurrency } from '../../utils/format';
import { resolveCatalogMediaUrl } from '../../utils/images';
import { isCashPayment, isDineInOrder, isPendingPayment } from '../../utils/orders';
import { paymentStatePresentation } from '../../utils/paymentState';
import { useToastStore } from '../../stores/toastStore';
import { OrderStatusBadge } from '../orders/OrderStatusBadge';

interface PaymentInstructionsCardProps {
  order: Order;
  payment: OrderPaymentInstructions | null;
  secondaryHref?: string;
  secondaryLabel?: string;
  showSecondaryAction?: boolean;
  onOrderUpdated?: (order: Order) => void;
  onCancelOrder?: () => void;
  isCancelling?: boolean;
  cancelError?: string | null;
}

type CopyField = 'order' | 'upi' | 'phone';

declare global {
  interface Window {
    Razorpay?: new (options: Record<string, unknown>) => { open: () => void };
  }
}

function isOnlinePaymentMethod(method: string | null | undefined): boolean {
  return ['razorpay', 'payu', 'paytm', 'phonepe'].includes(method ?? '');
}

async function loadRazorpayScript(): Promise<boolean> {
  if (window.Razorpay) {
    return true;
  }

  return new Promise((resolve) => {
    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.async = true;
    script.onload = () => resolve(true);
    script.onerror = () => resolve(false);
    document.body.appendChild(script);
  });
}

function toWhatsappHref(number: string, orderNumber: string): string {
  const normalized = number.replace(/[^\d+]/g, '');
  const message = encodeURIComponent(
    `Hi, I have completed the UPI payment for order ${orderNumber}. Sharing my Transaction ID / UTR here.`,
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
  onCancelOrder,
  isCancelling = false,
  cancelError = null,
}: PaymentInstructionsCardProps) {
  const whatsappNumber = payment?.whatsapp_number?.trim() ?? '';
  const upiId = payment?.upi_id?.trim() ?? '';
  const qrPath = payment?.qr_image_path?.trim() ?? '';
  const qrSrc = qrPath ? resolveCatalogMediaUrl(qrPath, '') : '';
  const instructions = payment?.instructions?.trim() ?? '';
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);
  const [copiedField, setCopiedField] = useState<CopyField | null>(null);
  const [isSubmittingTxn, setIsSubmittingTxn] = useState(false);
  const [txnError, setTxnError] = useState<string | null>(null);
  const [transactionId, setTransactionId] = useState(
    order.payment_transaction_id ?? order.payment_proof?.transaction_id ?? '',
  );
  const [isInitiatingOnline, setIsInitiatingOnline] = useState(false);
  const [isVerifyingOnline, setIsVerifyingOnline] = useState(false);
  const [onlineClient, setOnlineClient] = useState<Record<string, unknown> | null>(null);
  const [onlineError, setOnlineError] = useState<string | null>(null);

  const proof = order.payment_proof;
  const presentation = paymentStatePresentation(order);
  const awaitingReview = presentation.state === 'upi_awaiting_review';
  const rejected = presentation.state === 'upi_rejected';
  const paymentConfirmed = presentation.state === 'upi_confirmed' || presentation.state === 'cash_confirmed';
  const canSubmitTransaction = presentation.canSubmitTransaction;

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

  async function handleSubmitTransaction(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (isSubmittingTxn || !canSubmitTransaction) {
      return;
    }

    setIsSubmittingTxn(true);
    setTxnError(null);

    try {
      const response = await submitPaymentTransactionId(order.id, transactionId);
      onOrderUpdated?.(response.data);
      setTransactionId(response.data.payment_transaction_id ?? transactionId);
      toastSuccess('Transaction ID submitted for verification');
    } catch (error) {
      const message =
        error instanceof ApiError
          ? error.errors.transaction_id?.[0] ?? error.message
          : 'Unable to submit Transaction ID.';
      setTxnError(message);
      toastError(message);
    } finally {
      setIsSubmittingTxn(false);
    }
  }

  async function handleStartOnlinePayment(): Promise<void> {
    if (isInitiatingOnline || !order.payment_method) {
      return;
    }

    setIsInitiatingOnline(true);
    setOnlineError(null);

    try {
      const response = await initiateOrderPayment(order.id, order.payment_method);
      const client = response.data.client;
      setOnlineClient(client);

      const redirectUrl = client.redirect_url;
      if (typeof redirectUrl === 'string' && redirectUrl) {
        window.location.assign(redirectUrl);
        return;
      }

      if (typeof client.action_url === 'string' && client.action_url) {
        return;
      }

      if (order.payment_method === 'razorpay') {
        const loaded = await loadRazorpayScript();
        if (!loaded || !window.Razorpay) {
          throw new Error('Unable to load Razorpay checkout.');
        }

        const rzp = new window.Razorpay({
          key: client.key_id,
          amount: client.amount,
          currency: client.currency,
          name: client.name,
          description: client.description,
          order_id: client.order_id,
          prefill: client.prefill,
          handler: async (result: Record<string, string>) => {
            setIsVerifyingOnline(true);
            try {
              await verifyPaymentReturn(response.data.attempt_id, {
                razorpay_order_id: result.razorpay_order_id,
                razorpay_payment_id: result.razorpay_payment_id,
                razorpay_signature: result.razorpay_signature,
              });
              const refreshed = await fetchOrder(order.id);
              onOrderUpdated?.(refreshed.data);
              toastSuccess('Payment received');
            } catch (error) {
              const message =
                error instanceof ApiError ? error.message : 'Payment submitted. Verifying with the café…';
              setOnlineError(message);
              toastError(message);
            } finally {
              setIsVerifyingOnline(false);
            }
          },
        });
        rzp.open();
      }
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to start online payment.';
      setOnlineError(message);
      toastError(message);
    } finally {
      setIsInitiatingOnline(false);
    }
  }

  if (isOnlinePaymentMethod(order.payment_method) && isPendingPayment(order.status) && order.payment_status !== 'confirmed') {
    return (
      <section className="payment-card motion-enter" aria-live="polite">
        <div className="payment-card-header">
          <OrderStatusBadge status="pending_payment" label="Pending Payment" />
          <h2>Pay with {order.payment_method_label ?? order.payment_method}</h2>
          <p>
            {isVerifyingOnline
              ? 'Verifying payment… Your order updates only after the server confirms payment.'
              : 'Complete payment securely. Browser success is not final — the café verifies payment on the server.'}
          </p>
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
        </div>
        {onlineError ? <p className="form-feedback is-error">{onlineError}</p> : null}
        {onlineClient && typeof onlineClient.action_url === 'string' ? (
          <form method="POST" action={String(onlineClient.action_url)}>
            {Object.entries((onlineClient.fields as Record<string, string> | undefined) ?? {}).map(([name, value]) => (
              <input key={name} type="hidden" name={name} value={value} />
            ))}
            <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100">
              Continue to {order.payment_method_label ?? 'payment'}
            </button>
          </form>
        ) : (
          <button
            type="button"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isInitiatingOnline || isVerifyingOnline}
            onClick={() => void handleStartOnlinePayment()}
          >
            {isVerifyingOnline
              ? 'Verifying payment…'
              : isInitiatingOnline
                ? 'Starting…'
                : `Pay ${formatCurrency(order.total_amount)}`}
          </button>
        )}
        {showSecondaryAction ? (
          <div className="payment-actions">
            <Link to={secondaryHref} className="btn btn-outline-dark rounded-pill w-100">
              {secondaryLabel}
            </Link>
          </div>
        ) : null}
      </section>
    );
  }

  if (isCashPayment(order)) {
    return (
      <section
        className={[
          'payment-card motion-enter',
          presentation.state === 'cash_confirmed' ? 'payment-card-confirmed' : '',
        ]
          .filter(Boolean)
          .join(' ')}
        aria-live="polite"
      >
        <div className="payment-card-header">
          <OrderStatusBadge
            status={presentation.state === 'cash_confirmed' ? 'payment_confirmed' : 'pending_payment'}
            label={presentation.badge}
          />
          <h2>{presentation.title}</h2>
          <p>{presentation.body}</p>
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
          <OrderStatusBadge status="payment_confirmed" label={presentation.badge} />
          <h2>{presentation.title}</h2>
          <p>{presentation.body}</p>
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
        <OrderStatusBadge status="pending_payment" label={presentation.badge} />
        <h2>{presentation.title}</h2>
        <p>{presentation.body}</p>
      </div>

      <div className="payment-meta-grid">
        <div>
          <span>Amount to pay</span>
          <strong className="payment-amount">{formatCurrency(order.total_amount)}</strong>
        </div>
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
      </div>

      {upiId ? (
        <div className="payment-detail-block payment-copy-row">
          <div className="payment-copy-value">
            <span>Pay to</span>
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
      ) : payment?.display_name ? (
        <div className="payment-detail-block">
          <span>Pay to</span>
          <strong>{payment.display_name}</strong>
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
      ) : null}

      {rejected && proof?.rejection_notes ? (
        <div className="payment-reminder" role="status">
          <i className="bi bi-exclamation-triangle" aria-hidden="true"></i>
          <div>
            <strong>Not verified</strong>
            <p>{proof.rejection_notes}</p>
          </div>
        </div>
      ) : null}

      {awaitingReview ? (
        <div className="payment-reminder" role="status">
          <i className="bi bi-hourglass-split" aria-hidden="true"></i>
          <div>
            <strong>Payment verification pending</strong>
            <p>
              We&apos;ve received your transaction ID
              {proof?.transaction_id || order.payment_transaction_id
                ? ` (${proof?.transaction_id || order.payment_transaction_id})`
                : ''}
              . Your order will be confirmed once the payment is verified.
            </p>
          </div>
        </div>
      ) : null}

      {canSubmitTransaction ? (
        <form className="payment-detail-block" onSubmit={(event) => void handleSubmitTransaction(event)}>
          <span>Already paid?</span>
          <p className="mb-3">
            Enter the UPI Transaction ID / UTR from your payment app. We&apos;ll verify it against the payment
            received.
          </p>
          <label className="form-label" htmlFor={`upi-txn-${order.id}`}>
            UPI Transaction ID / UTR
          </label>
          <input
            id={`upi-txn-${order.id}`}
            type="text"
            className="form-control mb-3"
            value={transactionId}
            autoComplete="off"
            spellCheck={false}
            inputMode="text"
            placeholder="e.g. 312345678901"
            onChange={(event) => setTransactionId(event.target.value)}
          />
          {txnError ? (
            <p className="form-error-text" role="alert">
              {txnError}
            </p>
          ) : null}
          <button
            type="submit"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSubmittingTxn || transactionId.trim().length < 6}
            aria-busy={isSubmittingTxn}
          >
            {isSubmittingTxn
              ? 'Submitting…'
              : presentation.primaryAction === 'replace_transaction'
                ? 'Resubmit for Verification'
                : 'Submit for Verification'}
          </button>
        </form>
      ) : null}

      <div className="payment-actions">
        {awaitingReview && showSecondaryAction ? (
          <Link to={secondaryHref} className="btn btn-outline-dark rounded-pill w-100">
            {secondaryLabel}
          </Link>
        ) : null}

        {!awaitingReview && showSecondaryAction && !canSubmitTransaction ? (
          <Link to={secondaryHref} className="btn btn-primary btn-lg rounded-pill w-100">
            {secondaryLabel}
          </Link>
        ) : null}

        {whatsappNumber && (presentation.state === 'upi_pending' || rejected) ? (
          <a
            href={toWhatsappHref(whatsappNumber, order.order_number)}
            target="_blank"
            rel="noreferrer"
            className="btn btn-outline-dark rounded-pill payment-action-secondary"
          >
            Send on WhatsApp
          </a>
        ) : null}

        {proof?.has_screenshot ? (
          <a
            href={`${getApiBaseUrl()}/orders/${order.id}/payment-proof`}
            target="_blank"
            rel="noreferrer"
            className="link-button payment-view-proof"
          >
            View historical screenshot
          </a>
        ) : null}

        {onCancelOrder && order.can_cancel && isPendingPayment(order.status) ? (
          <div className="mt-2">
            {cancelError ? <p className="form-feedback is-error">{cancelError}</p> : null}
            <button
              type="button"
              className="btn btn-outline-danger btn-sm rounded-pill w-100"
              disabled={isCancelling}
              onClick={onCancelOrder}
            >
              {isCancelling ? 'Cancelling…' : 'Cancel Order'}
            </button>
          </div>
        ) : null}
      </div>
    </section>
  );
}
