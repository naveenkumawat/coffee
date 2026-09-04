import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import {
  DiningSession,
  fetchDiningSession,
  setDiningPaymentMethod,
  submitDiningPaymentTransactionId,
} from '../api/dining';
import { PaymentMethodSelector } from '../components/checkout/PaymentMethodSelector';
import { confirmYes } from '../components/common/ConfirmDialog';
import { PageHeader } from '../components/common/PageHeader';
import { OrderTaxBreakdown } from '../components/orders/OrderTaxBreakdown';
import { useDiningOpsSync } from '../notifications/useDiningOpsSync';
import { useLiveCanonicalSync } from '../notifications/useLiveCanonicalSync';
import { useToastStore } from '../stores/toastStore';
import { CheckoutPaymentInstructions, CheckoutPaymentMethodOption } from '../types/checkout';
import { copyTextToClipboard } from '../utils/clipboard';
import { diningDiscountLines } from '../utils/discounts';
import { formatCurrency } from '../utils/format';
import { resolveCatalogMediaUrl } from '../utils/images';
import {
  clearOrderingContext,
  diningDraftItemCount,
  diningSessionPath,
  isDiningSessionTerminal,
  writeOrderingContext,
} from '../utils/orderingContext';

type ApiMeta = {
  payment?: CheckoutPaymentInstructions | null;
  payment_methods?: CheckoutPaymentMethodOption[];
};

function isOnlineMethod(method: string | null | undefined): boolean {
  return ['razorpay', 'payu', 'paytm', 'phonepe'].includes(method ?? '');
}

export function DiningBillPage() {
  const { sessionId = '' } = useParams();
  const navigate = useNavigate();
  const toastSuccess = useToastStore((state) => state.success);
  const toastError = useToastStore((state) => state.error);

  const [session, setSession] = useState<DiningSession | null>(null);
  const [payment, setPayment] = useState<CheckoutPaymentInstructions | null>(null);
  const [methods, setMethods] = useState<CheckoutPaymentMethodOption[]>([]);
  const [selectedMethod, setSelectedMethod] = useState('');
  const [transactionId, setTransactionId] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [methodError, setMethodError] = useState<string | null>(null);
  const [txnError, setTxnError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [isSubmittingTxn, setIsSubmittingTxn] = useState(false);
  const [copiedUpi, setCopiedUpi] = useState(false);
  const [completionNotice, setCompletionNotice] = useState(false);
  const leavingTerminalRef = useRef(false);
  const redirectTimerRef = useRef<number | null>(null);

  const leaveCompletedSession = useCallback(
    (options?: { celebrate?: boolean }): void => {
      if (leavingTerminalRef.current) {
        return;
      }

      leavingTerminalRef.current = true;
      clearOrderingContext();

      if (options?.celebrate) {
        setCompletionNotice(true);
        if (redirectTimerRef.current !== null) {
          window.clearTimeout(redirectTimerRef.current);
        }
        redirectTimerRef.current = window.setTimeout(() => {
          navigate('/dining', { replace: true });
        }, 1200);

        return;
      }

      navigate('/dining', { replace: true });
    },
    [navigate],
  );

  const applySession = useCallback(
    (next: DiningSession, meta?: ApiMeta, options?: { celebratePaid?: boolean }): void => {
      if (isDiningSessionTerminal(next)) {
        leaveCompletedSession({ celebrate: options?.celebratePaid ?? true });

        return;
      }

      setSession(next);
      writeOrderingContext({
        type: 'dining',
        diningSessionId: String(next.id),
        tableLabel: next.table.label,
        draftItemCount: diningDraftItemCount(next.drafts),
      });

      if (meta?.payment) {
        setPayment(meta.payment);
      }

      if (meta?.payment_methods) {
        setMethods(meta.payment_methods);
      }

      const method = next.payment_method ?? '';
      if (method) {
        setSelectedMethod(method === 'manual' ? 'manual_upi' : method);
      }

      if (next.payment_transaction_id) {
        setTransactionId(next.payment_transaction_id);
      }
    },
    [leaveCompletedSession],
  );

  const reload = useCallback(async (): Promise<void> => {
    const response = await fetchDiningSession(sessionId);
    applySession(response.data, (response as { meta?: ApiMeta }).meta);
  }, [applySession, sessionId]);

  useLiveCanonicalSync(
    () => {
      void reload().catch(() => undefined);
    },
    (signal) => {
      if (signal.subject?.type === 'DiningSession' && String(signal.subject.id) === String(sessionId)) {
        return true;
      }

      if (signal.type.startsWith('customer.dining') || signal.type.startsWith('customer.payment')) {
        return Boolean(signal.action_url && signal.action_url.includes(`/dining/sessions/${sessionId}`));
      }

      return false;
    },
  );

  useDiningOpsSync(
    () => {
      void reload().catch(() => undefined);
    },
    (payload) => String(payload.session_id) === String(sessionId),
    { sessionId },
  );

  useEffect(() => {
    leavingTerminalRef.current = false;
    let cancelled = false;

    void (async () => {
      try {
        const response = await fetchDiningSession(sessionId);
        if (cancelled) {
          return;
        }

        applySession(response.data, (response as { meta?: ApiMeta }).meta, { celebratePaid: false });
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'Unable to load bill.');
        }
      }
    })();

    return () => {
      cancelled = true;
      if (redirectTimerRef.current !== null) {
        window.clearTimeout(redirectTimerRef.current);
      }
    };
  }, [applySession, sessionId]);

  useEffect(() => {
    if (!session || selectedMethod || methods.length === 0) {
      return;
    }

    if (!session.payment_method) {
      setSelectedMethod(methods[0]?.key === 'manual' ? 'manual_upi' : (methods[0]?.key ?? ''));
    }
  }, [methods, selectedMethod, session]);

  const paid = session?.payment_status === 'confirmed' || session?.status === 'paid' || session?.status === 'closed';
  const awaitingReview = session?.payment_status === 'awaiting_review';
  const rejected = session?.payment_status === 'rejected';
  const canSubmitTxn = Boolean(session?.capabilities?.can_submit_transaction_id) && !paid;
  const canResubmitTxn = Boolean(session?.capabilities?.can_resubmit_transaction_id) && !paid;
  const canChangeMethod = Boolean(session?.capabilities?.can_change_payment_method) && !paid && !awaitingReview;
  const showUtrForm =
    selectedMethod === 'manual_upi' && (canSubmitTxn || canResubmitTxn) && !awaitingReview;
  const roundCount = session?.rounds?.length ?? 0;
  const itemCount = useMemo(
    () =>
      (session?.rounds ?? []).reduce(
        (sum, round) => sum + (round.items ?? []).reduce((inner, item) => inner + Number(item.quantity ?? 0), 0),
        0,
      ),
    [session?.rounds],
  );

  const upiId = payment?.upi_id?.trim() ?? '';
  const qrSrc = payment?.qr_image_path ? resolveCatalogMediaUrl(payment.qr_image_path, '') : '';

  async function handleSelectMethod(method: string): Promise<void> {
    if (!session || paid || busy || method === selectedMethod) {
      setSelectedMethod(method);

      return;
    }

    if (!canChangeMethod && session.payment_method) {
      setSelectedMethod(session.payment_method === 'manual' ? 'manual_upi' : session.payment_method);

      return;
    }

    setSelectedMethod(method);
    setMethodError(null);
    setBusy(true);
    setError(null);

    try {
      const response = await setDiningPaymentMethod(sessionId, method);
      applySession(response.data, (response as { meta?: ApiMeta }).meta);
      toastSuccess('Payment method saved');
    } catch (err) {
      const message =
        err instanceof ApiError
          ? err.errors.payment_method?.[0] ?? err.message
          : 'Unable to set payment method.';
      setMethodError(message);
      toastError(message);
    } finally {
      setBusy(false);
    }
  }

  async function handleSubmitTransaction(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if ((!canSubmitTxn && !canResubmitTxn) || isSubmittingTxn || awaitingReview) {
      return;
    }

    const confirmed = await confirmYes({
      title: canResubmitTxn ? 'Submit a new Transaction ID?' : 'Submit Transaction ID?',
      body: `Submit ${transactionId.trim()} for staff verification of this dining bill.`,
      confirmLabel: canResubmitTxn ? 'Submit new ID' : 'Submit Transaction ID',
      tone: 'primary',
    });

    if (!confirmed) {
      return;
    }

    setIsSubmittingTxn(true);
    setTxnError(null);

    try {
      const response = await submitDiningPaymentTransactionId(sessionId, transactionId);
      applySession(response.data, (response as { meta?: ApiMeta }).meta);
      toastSuccess('Transaction ID submitted for verification');
    } catch (err) {
      const message =
        err instanceof ApiError
          ? err.errors.transaction_id?.[0] ?? err.message
          : 'Unable to submit Transaction ID.';
      setTxnError(message);
      toastError(message);
    } finally {
      setIsSubmittingTxn(false);
    }
  }

  async function handleCopyUpi(): Promise<void> {
    if (!upiId) {
      return;
    }

    const ok = await copyTextToClipboard(upiId);
    if (ok) {
      setCopiedUpi(true);
      toastSuccess('UPI ID copied');
      window.setTimeout(() => setCopiedUpi(false), 1600);
    } else {
      toastError('Could not copy. Please copy manually.');
    }
  }

  if (completionNotice) {
    return (
      <div className="page-container dining-bill-page">
        <div className="dining-content">
          <PageHeader title="Bill & Payment" showBack={false} />
          <section className="account-section payment-instructions-card" aria-live="polite">
            <div className="payment-state-banner is-success">
              <strong>Payment confirmed</strong>
              <p>Your table session is complete.</p>
            </div>
          </section>
        </div>
      </div>
    );
  }

  if (!session) {
    return (
      <div className="page-container dining-bill-page">
        <div className="dining-content">
          <PageHeader title="Bill & Payment" showBack />
          {error ? <p className="form-error-text">{error}</p> : <p className="muted">Loading…</p>}
        </div>
      </div>
    );
  }

  const totals = session.final_bill ?? session.totals;
  const discountLines = diningDiscountLines(session);

  return (
    <div className="page-container dining-bill-page">
      <div className="dining-content">
        <PageHeader
          title="Bill & Payment"
          description={`Table ${session.table.label} · ${session.session_number}`}
          showBack
        />

        {error ? (
          <p className="form-error-text" role="alert">
            {error}
          </p>
        ) : null}

        <section className="account-section dining-bill-summary" aria-labelledby="dining-bill-summary-title">
          <div className="dining-section-head">
            <h2 id="dining-bill-summary-title">Bill summary</h2>
            <span className={`status-badge ${paid ? 'is-success' : awaitingReview ? 'is-pending' : ''}`}>
              {session.payment_status_label ?? session.status_label ?? session.status}
            </span>
          </div>

          {(roundCount > 0 || itemCount > 0) && (
            <p className="muted mb-0">
              {roundCount} order{roundCount === 1 ? '' : 's'}
              {itemCount > 0 ? ` · ${itemCount} item${itemCount === 1 ? '' : 's'}` : ''}
            </p>
          )}

          <OrderTaxBreakdown
            subtotal={totals.subtotal}
            total={totals.total}
            tax={
              totals.tax_enabled === false || Number(totals.tax) <= 0
                ? null
                : {
                    enabled: true,
                    label: totals.tax_label ?? 'GST',
                    percent: totals.tax_percent ?? '0',
                    inclusive: false,
                    taxable_amount: totals.subtotal,
                    amount: totals.tax,
                  }
            }
            discounts={discountLines}
            discountTotal={totals.discount}
            totalLabel="Total"
            showSavingsNote={false}
          />
        </section>

        {paid ? null : (
          <>
            <section className="account-section" aria-labelledby="dining-pay-method-title">
              <h2 id="dining-pay-method-title" className="h5 mb-3">
                How would you like to pay?
              </h2>
              <PaymentMethodSelector
                methods={methods}
                value={selectedMethod}
                onChange={(value) => void handleSelectMethod(value)}
                error={methodError}
                disabled={!canChangeMethod || busy || paid}
              />
            </section>

            {selectedMethod === 'cash' ? (
              <section className="account-section payment-instructions-card" aria-labelledby="dining-cash-title">
                <h2 id="dining-cash-title">Cash payment</h2>
                <div className="payment-meta-grid">
                  <div>
                    <span>Amount to pay</span>
                    <strong className="payment-amount">{formatCurrency(totals.total)}</strong>
                  </div>
                </div>
                <div className="payment-reminder" role="status">
                  <i className="bi bi-cash-coin" aria-hidden="true"></i>
                  <div>
                    <strong>Please pay the waiter</strong>
                    <p>Your payment will be confirmed by staff. Selecting cash does not mark the bill paid.</p>
                  </div>
                </div>
              </section>
            ) : null}

            {selectedMethod === 'manual_upi' ? (
              <section className="account-section payment-instructions-card" aria-labelledby="dining-upi-title">
                <h2 id="dining-upi-title">Pay via UPI</h2>

                <div className="payment-meta-grid">
                  <div>
                    <span>Amount</span>
                    <strong className="payment-amount">{formatCurrency(totals.total)}</strong>
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
                      onClick={() => void handleCopyUpi()}
                    >
                      {copiedUpi ? 'Copied' : 'Copy'}
                    </button>
                  </div>
                ) : null}

                {qrSrc ? (
                  <div className="payment-detail-block payment-qr-block">
                    <span>Scan QR</span>
                    <img src={qrSrc} alt="Payment QR code" className="payment-qr-image" />
                  </div>
                ) : null}

                {payment?.instructions ? (
                  <div className="payment-detail-block">
                    <span>Instructions</span>
                    <p>{payment.instructions}</p>
                  </div>
                ) : null}

                {rejected && (session.payment_rejection_reason || session.payment_proof?.rejection_notes) ? (
                  <div className="payment-reminder" role="status">
                    <i className="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <div>
                      <strong>Not verified</strong>
                      <p>{session.payment_rejection_reason || session.payment_proof?.rejection_notes}</p>
                    </div>
                  </div>
                ) : null}

                {awaitingReview ? (
                  <div className="payment-reminder" role="status">
                    <i className="bi bi-hourglass-split" aria-hidden="true"></i>
                    <div>
                      <strong>Verification Pending</strong>
                      <p>
                        Your transaction ID has been submitted and is awaiting verification
                        {session.payment_transaction_id ? ` (${session.payment_transaction_id})` : ''}.
                      </p>
                    </div>
                  </div>
                ) : null}

                {showUtrForm ? (
                  <form className="payment-detail-block" onSubmit={(event) => void handleSubmitTransaction(event)}>
                    <span>After payment</span>
                    <p className="mb-3">Enter the transaction ID / UTR from your UPI app.</p>
                    <label className="form-label" htmlFor={`dining-upi-txn-${sessionId}`}>
                      Transaction ID / UTR
                    </label>
                    <input
                      id={`dining-upi-txn-${sessionId}`}
                      type="text"
                      className="form-control coffee-input mb-3"
                      value={transactionId}
                      autoComplete="off"
                      spellCheck={false}
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
                        : canResubmitTxn
                          ? 'Submit new transaction ID'
                          : 'Submit for Verification'}
                    </button>
                  </form>
                ) : null}
              </section>
            ) : null}

            {isOnlineMethod(selectedMethod) ? (
              <section className="account-section payment-instructions-card" aria-labelledby="dining-online-title">
                <h2 id="dining-online-title">Pay securely online</h2>
                <div className="payment-meta-grid">
                  <div>
                    <span>Amount</span>
                    <strong className="payment-amount">{formatCurrency(totals.total)}</strong>
                  </div>
                </div>
                <div className="payment-reminder" role="status">
                  <i className="bi bi-shield-check" aria-hidden="true"></i>
                  <div>
                    <strong>{selectedMethod}</strong>
                    <p>
                      Online dining settlement uses the same configured gateway as retail. Ask your waiter if checkout
                      does not open automatically, or choose Manual UPI / Cash when available.
                    </p>
                  </div>
                </div>
              </section>
            ) : null}
          </>
        )}

        <Link to={diningSessionPath(sessionId)} className="btn btn-text">
          Back to table
        </Link>
      </div>
    </div>
  );
}
