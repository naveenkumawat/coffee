import {
  OrderingMode,
  activeDiningTableLabel,
  hasActiveDiningSession,
  setOrderingMode,
} from '../../utils/orderingContext';
import { useOrderingContext } from '../../hooks/useOrderingContext';

interface OrderingModeSwitcherProps {
  className?: string;
}

/**
 * Compact Dining ↔ Takeaway switcher when a dining session is active.
 */
export function OrderingModeSwitcher({ className = '' }: OrderingModeSwitcherProps) {
  const context = useOrderingContext();

  if (!hasActiveDiningSession(context)) {
    return null;
  }

  const tableLabel = activeDiningTableLabel(context) ?? 'Table';
  const diningSelected = context.mode === 'dining';

  function select(mode: OrderingMode): void {
    if (mode === context.mode) {
      return;
    }

    setOrderingMode(mode);
  }

  return (
    <div className={`ordering-mode-switcher ${className}`.trim()} role="group" aria-label="Ordering for">
      <p className="ordering-mode-switcher-label">Ordering for</p>
      <div className="ordering-mode-switcher-control">
        <button
          type="button"
          className={`ordering-mode-option ${diningSelected ? 'is-selected' : ''}`.trim()}
          aria-pressed={diningSelected}
          onClick={() => select('dining')}
        >
          Table {tableLabel}
        </button>
        <button
          type="button"
          className={`ordering-mode-option ${!diningSelected ? 'is-selected' : ''}`.trim()}
          aria-pressed={!diningSelected}
          onClick={() => select('takeaway')}
        >
          Takeaway
        </button>
      </div>
    </div>
  );
}
