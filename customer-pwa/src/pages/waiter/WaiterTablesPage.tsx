import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ApiError } from '../../api/client';
import {
  WaiterTable,
  WaiterTableDisplayState,
  fetchWaiterTables,
  startWaiterSession,
} from '../../api/waiterDining';
import { BrandLogo } from '../../components/common/BrandLogo';
import { EmptyState } from '../../components/common/EmptyState';
import { ErrorState } from '../../components/common/ErrorState';
import { LoadingSkeleton } from '../../components/common/LoadingSkeleton';
import { useDiningOpsSync } from '../../notifications/useDiningOpsSync';
import { useAuthStore } from '../../stores/authStore';
import { useToastStore } from '../../stores/toastStore';
import { formatCurrency } from '../../utils/format';
import { clearRememberedWaiterSession, rememberWaiterSession } from '../../utils/waiterSession';

const STATE_ORDER: WaiterTableDisplayState[] = [
  'ready_to_serve',
  'preparing',
  'active',
  'bill_requested',
  'payment_pending',
  'paid',
  'available',
  'inactive',
];

function displayStateClass(state: WaiterTableDisplayState): string {
  switch (state) {
    case 'available':
      return 'is-available';
    case 'preparing':
      return 'is-preparing';
    case 'ready_to_serve':
      return 'is-ready';
    case 'bill_requested':
    case 'payment_pending':
      return 'is-billing';
    case 'paid':
      return 'is-paid';
    case 'inactive':
      return 'is-inactive';
    default:
      return 'is-active';
  }
}

export function WaiterTablesPage() {
  const navigate = useNavigate();
  const customer = useAuthStore((state) => state.customer);
  const logout = useAuthStore((state) => state.logout);
  const toastError = useToastStore((state) => state.error);
  const toastSuccess = useToastStore((state) => state.success);
  const [tables, setTables] = useState<WaiterTable[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [busyTableId, setBusyTableId] = useState<number | null>(null);
  const [confirmTable, setConfirmTable] = useState<WaiterTable | null>(null);

  const loadTables = useCallback(async (): Promise<void> => {
    setErrorMessage(null);

    try {
      const response = await fetchWaiterTables();
      const sorted = [...response.data].sort((a, b) => {
        const ai = STATE_ORDER.indexOf(a.display_state);
        const bi = STATE_ORDER.indexOf(b.display_state);

        return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi) || a.label.localeCompare(b.label);
      });
      setTables(sorted);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load tables.');
      setTables([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadTables();
  }, [loadTables]);

  useDiningOpsSync(() => {
    void loadTables();
  });

  async function handleStartSession(table: WaiterTable): Promise<void> {
    setBusyTableId(table.id);
    setConfirmTable(null);

    try {
      const response = await startWaiterSession({ cafe_table_id: table.id });
      rememberWaiterSession(response.data.id);
      toastSuccess(`Opened ${table.label}`);
      navigate(`/waiter/sessions/${response.data.id}`);
    } catch (error) {
      toastError(error instanceof ApiError ? error.message : 'Unable to start session.');
      setIsLoading(true);
      await loadTables();
    } finally {
      setBusyTableId(null);
    }
  }

  function handleTableTap(table: WaiterTable): void {
    if (table.display_state === 'inactive') {
      return;
    }

    if (table.session?.id) {
      rememberWaiterSession(table.session.id);
      navigate(`/waiter/sessions/${table.session.id}`);

      return;
    }

    if (table.available || table.display_state === 'available') {
      setConfirmTable(table);
    }
  }

  async function handleLogout(): Promise<void> {
    clearRememberedWaiterSession();
    await logout();
    navigate('/login', { replace: true });
  }

  if (isLoading) {
    return (
      <div className="page-container waiter-page">
        <LoadingSkeleton cardCount={4} lines={2} variant="list" />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="page-container waiter-page">
        <ErrorState description={errorMessage} onRetry={() => void loadTables()} />
      </div>
    );
  }

  return (
    <div className="page-container waiter-page">
      <header className="waiter-page-header">
        <div className="waiter-page-brand">
          <BrandLogo linked={false} size="sm" showWordmark />
          <div>
            <p className="eyebrow">Waiter</p>
            <h1>Tables</h1>
            {customer?.name ? <p className="waiter-page-subtitle">{customer.name}</p> : null}
          </div>
        </div>
        <div className="waiter-header-actions">
          <button type="button" className="btn btn-text" onClick={() => void loadTables()}>
            Refresh
          </button>
          <button type="button" className="btn btn-text" onClick={() => void handleLogout()}>
            Sign out
          </button>
        </div>
      </header>

      {tables.length === 0 ? (
        <EmptyState title="No tables" description="No active cafe tables are configured yet." />
      ) : (
        <div className="waiter-table-grid motion-enter">
          {tables.map((table) => {
            const session = table.session;
            const ready = Boolean(session?.ready_to_serve) || table.display_state === 'ready_to_serve';

            return (
              <button
                key={table.id}
                type="button"
                className={[
                  'waiter-table-card',
                  displayStateClass(table.display_state),
                  ready ? 'is-ready-highlight' : '',
                ]
                  .filter(Boolean)
                  .join(' ')}
                disabled={table.display_state === 'inactive' || busyTableId === table.id}
                aria-busy={busyTableId === table.id || undefined}
                onClick={() => handleTableTap(table)}
              >
                <div className="waiter-table-card-top">
                  <strong>{table.label}</strong>
                  <span className={`waiter-state-chip ${displayStateClass(table.display_state)}`}>
                    {table.display_state_label}
                  </span>
                </div>

                {session ? (
                  <div className="waiter-table-card-meta">
                    <span>
                      {session.round_count} round{session.round_count === 1 ? '' : 's'}
                      {session.has_unsent_draft ? ' · draft' : ''}
                    </span>
                    <strong>{formatCurrency(session.running_total)}</strong>
                  </div>
                ) : (
                  <p className="waiter-table-card-hint">Tap to start session</p>
                )}
              </button>
            );
          })}
        </div>
      )}

      {confirmTable ? (
        <div className="waiter-confirm-overlay" role="presentation">
          <button
            type="button"
            className="waiter-confirm-backdrop"
            aria-label="Cancel"
            onClick={() => setConfirmTable(null)}
          />
          <div
            className="waiter-confirm-sheet"
            role="dialog"
            aria-modal="true"
            aria-labelledby="start-session-title"
          >
            <h2 id="start-session-title">Start session?</h2>
            <p>
              Open a new dining session on <strong>{confirmTable.label}</strong>.
            </p>
            <div className="waiter-confirm-actions">
              <button type="button" className="btn btn-secondary rounded-pill" onClick={() => setConfirmTable(null)}>
                Cancel
              </button>
              <button
                type="button"
                className="btn btn-primary rounded-pill"
                disabled={busyTableId === confirmTable.id}
                aria-busy={busyTableId === confirmTable.id}
                onClick={() => void handleStartSession(confirmTable)}
              >
                {busyTableId === confirmTable.id ? 'Starting…' : 'Start session'}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
