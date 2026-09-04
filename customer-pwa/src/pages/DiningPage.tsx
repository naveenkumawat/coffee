import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ApiError } from '../api/client';
import {
  DiningSession,
  DiningTableOption,
  fetchActiveDiningSession,
  fetchDiningTables,
  startDiningSession,
} from '../api/dining';
import { EmptyState } from '../components/common/EmptyState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { QuantityStepper } from '../components/common/QuantityStepper';
import { useContentStore } from '../stores/contentStore';
import {
  clearOrderingContext,
  diningDraftItemCount,
  diningSessionPath,
  writeOrderingContext,
} from '../utils/orderingContext';

const GUEST_MIN = 1;
const GUEST_MAX = 50;

function tableStateLabel(table: DiningTableOption): string {
  if (table.available || table.state === 'available') {
    return 'Available';
  }

  if (table.state === 'inactive') {
    return 'Unavailable';
  }

  return 'Occupied';
}

function isTableSelectable(table: DiningTableOption): boolean {
  return Boolean(table.available) && table.state !== 'inactive';
}

export function DiningPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const preselect = searchParams.get('table')?.trim() ?? '';
  const content = useContentStore((state) => state.content);
  const diningEnabled = Boolean(
    content?.fulfilment?.dining_enabled ?? content?.fulfilment?.dine_in_enabled,
  );

  const [tables, setTables] = useState<DiningTableOption[]>([]);
  const [activeSession, setActiveSession] = useState<DiningSession | null>(null);
  const [tableId, setTableId] = useState<number | null>(null);
  const [guestCount, setGuestCount] = useState(2);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const active = await fetchActiveDiningSession();
        if (cancelled) {
          return;
        }

        if (active.data?.id) {
          setActiveSession(active.data);
          writeOrderingContext({
            type: 'dining',
            diningSessionId: String(active.data.id),
            tableLabel: active.data.table.label,
            draftItemCount: diningDraftItemCount(active.data.drafts),
          });
          setLoading(false);

          return;
        }

        clearOrderingContext();
        setActiveSession(null);

        const response = await fetchDiningTables();
        if (cancelled) {
          return;
        }

        setTables(response.data);
        const matched = response.data.find(
          (table) =>
            isTableSelectable(table) &&
            (table.code.toLowerCase() === preselect.toLowerCase() ||
              table.label.toLowerCase() === preselect.toLowerCase()),
        );
        setTableId(matched?.id ?? response.data.find((table) => isTableSelectable(table))?.id ?? null);
        setGuestCount(2);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'Unable to load dining tables.');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [preselect]);

  const selectedTable = useMemo(
    () => tables.find((table) => table.id === tableId) ?? null,
    [tableId, tables],
  );
  const availableCount = useMemo(
    () => tables.filter((table) => isTableSelectable(table)).length,
    [tables],
  );
  const canStart = Boolean(selectedTable && isTableSelectable(selectedTable) && guestCount >= GUEST_MIN);

  async function onSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();

    if (!canStart || submitting || !tableId) {
      setError('Choose a table to start dining.');

      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const response = await startDiningSession({
        cafe_table_id: tableId,
        guest_count: guestCount,
      });
      writeOrderingContext({
        type: 'dining',
        diningSessionId: String(response.data.id),
        tableLabel: response.data.table.label,
        draftItemCount: diningDraftItemCount(response.data.drafts),
      });
      navigate(diningSessionPath(response.data.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Unable to start dining session.');
    } finally {
      setSubmitting(false);
    }
  }

  if (!diningEnabled) {
    return (
      <div className="page-container dining-page">
        <div className="dining-content">
          <PageHeader
            title="Dining"
            description="Table service is currently unavailable. You can still order takeaway or delivery."
          />
          <EmptyState
            title="Dining is unavailable"
            description="Table service is paused right now. Browse the menu for takeaway or delivery instead."
            actionLabel="Browse menu"
            actionHref="/menu"
          />
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="page-container dining-page">
        <div className="dining-content">
          <PageHeader title="Dining" description="Choose your table and number of guests." />
          <LoadingSkeleton cardCount={3} lines={2} variant="list" />
        </div>
      </div>
    );
  }

  if (activeSession) {
    return (
      <div className="page-container dining-page">
        <div className="dining-content">
          <PageHeader title="Dining" description="You already have a table session in progress." />

          <section className="account-section dining-active-session-card" aria-labelledby="dining-active-title">
            <p className="dining-session-kicker">Active session</p>
            <h2 id="dining-active-title">You&apos;re dining at Table {activeSession.table.label}</h2>
            {activeSession.guest_count ? (
              <p className="muted mb-0">
                {activeSession.guest_count} guest{activeSession.guest_count === 1 ? '' : 's'}
              </p>
            ) : null}
            <Link
              to={diningSessionPath(activeSession.id)}
              className="btn btn-primary btn-lg rounded-pill w-100"
            >
              Return to table
            </Link>
          </section>
        </div>
      </div>
    );
  }

  return (
    <div className="page-container dining-page">
      <div className="dining-content">
        <PageHeader
          title="Dining"
          description="Dine in with us. Choose your table and number of guests. You can order more anytime and pay when you're finished."
        />

        {error ? (
          <p className="form-error-text" role="alert">
            {error}
          </p>
        ) : null}

        {tables.length === 0 ? (
          <EmptyState
            title="No tables available right now"
            description="All tables are currently in use. Please check again shortly or ask our team for help."
          />
        ) : (
          <form className="dining-start-form" onSubmit={(event) => void onSubmit(event)}>
            <section className="account-section dining-table-section" aria-labelledby="dining-tables-title">
              <div className="dining-section-head">
                <h2 id="dining-tables-title">
                  {availableCount > 0 ? 'Available tables' : 'Tables'}
                </h2>
              </div>

              {availableCount === 0 ? (
                <p className="muted" role="status">
                  All tables are currently in use. Please check again shortly or ask our team for help.
                </p>
              ) : null}

              <div className="dining-table-grid" role="listbox" aria-label="Cafe tables">
                {tables.map((table) => {
                  const selectable = isTableSelectable(table);
                  const selected = selectable && table.id === tableId;
                  const stateLabel = tableStateLabel(table);

                  return (
                    <button
                      key={table.id}
                      type="button"
                      role="option"
                      className={[
                        'dining-table-card',
                        selectable ? 'is-available' : 'is-unavailable',
                        selected ? 'is-selected' : '',
                      ]
                        .filter(Boolean)
                        .join(' ')}
                      aria-selected={selected}
                      aria-disabled={!selectable}
                      aria-label={
                        selectable
                          ? `Select Table ${table.label}`
                          : `Table ${table.label}, ${stateLabel}`
                      }
                      disabled={!selectable}
                      onClick={() => {
                        if (selectable) {
                          setTableId(table.id);
                          setError(null);
                        }
                      }}
                    >
                      <span className="dining-table-card-label">{table.label}</span>
                      <span className={`dining-table-card-state ${selectable ? 'is-available' : ''}`}>
                        {stateLabel}
                      </span>
                      {selected ? (
                        <span className="dining-table-card-check" aria-hidden="true">
                          <i className="bi bi-check-lg"></i>
                        </span>
                      ) : null}
                    </button>
                  );
                })}
              </div>
            </section>

            {availableCount > 0 ? (
              <>
                <section className="account-section dining-guest-section" aria-labelledby="dining-guests-title">
                  <div className="dining-section-head">
                    <h2 id="dining-guests-title">Guests</h2>
                  </div>
                  <div className="dining-guest-stepper">
                    <QuantityStepper
                      value={guestCount}
                      min={GUEST_MIN}
                      max={GUEST_MAX}
                      size="lg"
                      decreaseAriaLabel="Decrease guests"
                      increaseAriaLabel="Increase guests"
                      onChange={(value) => setGuestCount(value)}
                    />
                  </div>
                </section>

                <section className="account-section dining-start-summary" aria-labelledby="dining-start-title">
                  <p className="dining-session-kicker">Your table</p>
                  <h2 id="dining-start-title">
                    {selectedTable ? `Table ${selectedTable.label}` : 'Choose a table'}
                  </h2>
                  <p className="muted mb-0">
                    {guestCount} guest{guestCount === 1 ? '' : 's'}
                  </p>
                  <button
                    className="btn btn-primary btn-lg rounded-pill w-100"
                    type="submit"
                    disabled={!canStart || submitting}
                    aria-busy={submitting || undefined}
                  >
                    {submitting ? 'Starting session…' : 'Start dining'}
                  </button>
                </section>
              </>
            ) : null}
          </form>
        )}
      </div>
    </div>
  );
}
