import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '../../api/client';
import {
  WaiterDiningSession,
  closeWaiterSession,
  fetchWaiterSession,
  markWaiterCashReceived,
  reopenWaiterSession,
  requestWaiterBill,
  setWaiterPaymentMethod,
} from '../../api/waiterDining';
import { ErrorState } from '../../components/common/ErrorState';
import { LoadingSkeleton } from '../../components/common/LoadingSkeleton';
import { PageHeader } from '../../components/common/PageHeader';
import { StickyActionBar } from '../../components/common/StickyActionBar';
import { useToastStore } from '../../stores/toastStore';
import { formatCurrency } from '../../utils/format';
import {
  clearRememberedWaiterSession,
  rememberWaiterSession,
} from '../../utils/waiterSession';

export function WaiterSessionPage() {
  const { sessionId = '' } = useParams();
  const navigate = useNavigate();
  const toastError = useToastStore((state) => state.error);
  const toastSuccess = useToastStore((state) => state.success);
  const [session, setSession] = useState<WaiterDiningSession | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [billConfirmOpen, setBillConfirmOpen] = useState(false);

  const loadSession = useCallback(async (): Promise<void> => {
    setErrorMessage(null);

    try {
      const response = await fetchWaiterSession(sessionId);
      setSession(response.data);
      rememberWaiterSession(response.data.id);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load session.');
      setSession(null);
    } finally {
      setIsLoading(false);
    }
  }, [sessionId]);

  useEffect(() => {
    void loadSession();
  }, [loadSession]);

  const billTotal = useMemo(() => {
    if (!session) {
      return '0.00';
    }

    return session.final_bill?.total ?? session.totals?.total ?? session.running_bill?.total ?? '0.00';
  }, [session]);

  const caps = session?.capabilities ?? {};
  const draftCount =
    caps.draft_item_count ?? session?.drafts.reduce((sum, draft) => sum + draft.quantity, 0) ?? 0;
  const hasDraft = Boolean(caps.has_unsent_draft) || draftCount > 0;

  async function run(action: () => Promise<WaiterDiningSession>, successMessage: string): Promise<void> {
    setBusy(true);

    try {
      const next = await action();
      setSession(next);
      toastSuccess(successMessage);
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Action failed.');
      await loadSession();
    } finally {
      setBusy(false);
    }
  }

  async function handleRequestBill(discardDraft = false): Promise<void> {
    setBillConfirmOpen(false);
    await run(
      async () => (await requestWaiterBill(sessionId, { discard_draft: discardDraft })).data,
      'Bill requested',
    );
  }

  function onRequestBillTap(): void {
    if (hasDraft) {
      setBillConfirmOpen(true);

      return;
    }

    void handleRequestBill(false);
  }

  if (isLoading && !session) {
    return (
      <div className="page-container waiter-page">
        <PageHeader title="Session" showBack />
        <LoadingSkeleton cardCount={3} lines={3} />
      </div>
    );
  }

  if (!session) {
    return (
      <div className="page-container waiter-page">
        <PageHeader title="Session" showBack />
        <ErrorState
          description={errorMessage ?? 'Session not found.'}
          onRetry={() => {
            setIsLoading(true);
            void loadSession();
          }}
        />
      </div>
    );
  }

  return (
    <div className="page-container waiter-page has-sticky-cta">
      <PageHeader
        title={session.table.label}
        description={`${session.session_number} · ${session.status_label ?? session.status}`}
        showBack
      />

      <section className="waiter-session-summary">
        <div>
          <p className="eyebrow">Running bill</p>
          <strong className="waiter-session-total">{formatCurrency(billTotal)}</strong>
        </div>
        <div className="waiter-session-stats">
          <span>{session.rounds.length} rounds</span>
          {hasDraft ? <span className="waiter-draft-pill">{draftCount} in draft</span> : null}
          {session.guest_count ? <span>{session.guest_count} guests</span> : null}
        </div>
      </section>

      {hasDraft ? (
        <section className="waiter-panel">
          <div className="waiter-panel-head">
            <h2>Unsent draft</h2>
            <Link to={`/waiter/sessions/${sessionId}/review`} className="link-button">
              Review
            </Link>
          </div>
          <ul className="waiter-draft-list">
            {session.drafts.map((draft) => (
              <li key={draft.id}>
                <span>
                  {draft.quantity}× {draft.product_name}
                  {draft.variant_name ? ` (${draft.variant_name})` : ''}
                </span>
                <strong>{formatCurrency(draft.line_total)}</strong>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <section className="waiter-panel">
        <div className="waiter-panel-head">
          <h2>Rounds</h2>
        </div>
        {session.rounds.length === 0 ? <p className="muted">No rounds sent yet.</p> : null}
        <div className="waiter-round-list">
          {session.rounds.map((round) => (
            <article
              key={round.id}
              className={[
                'waiter-round-card',
                round.ready_to_serve ? 'is-ready' : '',
                round.is_preparing ? 'is-preparing' : '',
              ]
                .filter(Boolean)
                .join(' ')}
            >
              <div className="waiter-round-card-top">
                <strong>
                  Round {round.round_number}
                  {round.order_number ? ` · ${round.order_number}` : ''}
                </strong>
                <span className="status-badge">
                  {round.ready_to_serve
                    ? 'Ready to serve'
                    : (round.status_label ?? round.status ?? 'Placed')}
                </span>
              </div>
              {round.stations.length > 0 ? (
                <div className="waiter-station-chips">
                  {round.stations.map((station, index) => (
                    <span key={`${round.id}-${station.station ?? index}`}>
                      {station.station_label ?? station.station}: {station.status_label ?? station.status}
                    </span>
                  ))}
                </div>
              ) : null}
              <ul className="waiter-round-items">
                {round.items.map((item) => (
                  <li key={item.id}>
                    {item.quantity}× {item.product_name}
                    {item.variant_name ? ` (${item.variant_name})` : ''}
                  </li>
                ))}
              </ul>
              <strong className="waiter-round-total">{formatCurrency(round.total_amount)}</strong>
            </article>
          ))}
        </div>
      </section>

      {(caps.can_change_payment_method ||
        caps.can_mark_cash_received ||
        caps.can_close ||
        caps.can_reopen) && (
        <section className="waiter-panel">
          <div className="waiter-panel-head">
            <h2>Payment & close</h2>
          </div>
          <div className="waiter-action-stack">
            {caps.can_change_payment_method ? (
              <>
                <button
                  type="button"
                  className="btn btn-secondary rounded-pill"
                  disabled={busy}
                  onClick={() =>
                    void run(
                      async () => (await setWaiterPaymentMethod(sessionId, 'cash')).data,
                      'Cash selected',
                    )
                  }
                >
                  Pay by cash
                </button>
                <button
                  type="button"
                  className="btn btn-secondary rounded-pill"
                  disabled={busy}
                  onClick={() =>
                    void run(
                      async () => (await setWaiterPaymentMethod(sessionId, 'manual_upi')).data,
                      'UPI selected',
                    )
                  }
                >
                  Pay by UPI
                </button>
              </>
            ) : null}
            {caps.can_mark_cash_received ? (
              <button
                type="button"
                className="btn btn-primary rounded-pill"
                disabled={busy}
                onClick={() =>
                  void run(async () => (await markWaiterCashReceived(sessionId)).data, 'Cash received')
                }
              >
                Mark cash received
              </button>
            ) : null}
            {caps.can_close ? (
              <button
                type="button"
                className="btn btn-primary rounded-pill"
                disabled={busy}
                onClick={() =>
                  void run(async () => {
                    const next = (await closeWaiterSession(sessionId)).data;
                    clearRememberedWaiterSession();
                    navigate('/waiter');

                    return next;
                  }, 'Session closed')
                }
              >
                Close table
              </button>
            ) : null}
            {caps.can_reopen ? (
              <button
                type="button"
                className="btn btn-secondary rounded-pill"
                disabled={busy}
                onClick={() =>
                  void run(async () => {
                    const next = (await reopenWaiterSession(sessionId)).data;
                    rememberWaiterSession(sessionId);

                    return next;
                  }, 'Session reopened')
                }
              >
                Reopen session
              </button>
            ) : null}
          </div>
        </section>
      )}

      <StickyActionBar
        eyebrow={session.table.label}
        title={hasDraft ? 'Draft ready' : 'Table actions'}
        value={formatCurrency(billTotal)}
        note={
          hasDraft
            ? `${draftCount} item${draftCount === 1 ? '' : 's'} waiting to send`
            : caps.can_add_rounds
              ? 'Add items or request the bill'
              : 'Session in progress'
        }
      >
        <div className="sticky-action-stack waiter-sticky-actions">
          {caps.can_add_rounds ? (
            <Link
              to={`/waiter/sessions/${sessionId}/menu`}
              className="btn btn-secondary btn-lg rounded-pill w-100"
            >
              Add order
            </Link>
          ) : null}
          {hasDraft ? (
            <Link
              to={`/waiter/sessions/${sessionId}/review`}
              className="btn btn-primary btn-lg rounded-pill w-100"
            >
              Review & send
            </Link>
          ) : null}
          {caps.can_request_bill ? (
            <button type="button" className="btn btn-text" disabled={busy} onClick={onRequestBillTap}>
              Request bill
            </button>
          ) : null}
        </div>
      </StickyActionBar>

      {billConfirmOpen ? (
        <div className="waiter-confirm-overlay" role="presentation">
          <button
            type="button"
            className="waiter-confirm-backdrop"
            aria-label="Cancel"
            onClick={() => setBillConfirmOpen(false)}
          />
          <div
            className="waiter-confirm-sheet"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bill-confirm-title"
          >
            <h2 id="bill-confirm-title">Unsent draft</h2>
            <p>This table has items that have not been sent. Discard the draft, or go review first.</p>
            <div className="waiter-confirm-actions">
              <button
                type="button"
                className="btn btn-secondary rounded-pill"
                onClick={() => {
                  setBillConfirmOpen(false);
                  navigate(`/waiter/sessions/${sessionId}/review`);
                }}
              >
                Go to review
              </button>
              <button
                type="button"
                className="btn btn-primary rounded-pill"
                disabled={busy}
                onClick={() => void handleRequestBill(true)}
              >
                Discard & request bill
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
