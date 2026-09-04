import { CART_MAX_QUANTITY } from '../../utils/cartQuantity';

interface QuantityStepperProps {
  value: number;
  min?: number;
  max?: number;
  disabled?: boolean;
  size?: 'sm' | 'md' | 'lg';
  /** When true, decreasing at 1 emits 0 so the parent can remove the line. */
  allowRemove?: boolean;
  decreaseAriaLabel?: string;
  increaseAriaLabel?: string;
  onChange: (value: number) => void;
}

export function QuantityStepper({
  value,
  min = 1,
  max = CART_MAX_QUANTITY,
  disabled = false,
  size = 'md',
  allowRemove = false,
  decreaseAriaLabel = 'Decrease quantity',
  increaseAriaLabel = 'Increase quantity',
  onChange,
}: QuantityStepperProps) {
  const canDecrease = allowRemove ? value > 0 : value > min;
  const canIncrease = value < max;

  return (
    <div className={`quantity-stepper size-${size}`} role="group" aria-label="Quantity">
      <button
        type="button"
        className="icon-button"
        aria-label={allowRemove && value <= 1 ? 'Remove from cart' : decreaseAriaLabel}
        disabled={disabled || !canDecrease}
        onClick={() => {
          if (allowRemove && value <= 1) {
            onChange(0);

            return;
          }

          onChange(Math.max(min, value - 1));
        }}
      >
        <i className="bi bi-dash-lg" aria-hidden="true"></i>
      </button>
      <span className="quantity-stepper-value" aria-live="polite" key={value}>
        {value}
      </span>
      <button
        type="button"
        className="icon-button"
        aria-label={increaseAriaLabel}
        disabled={disabled || !canIncrease}
        onClick={() => onChange(Math.min(max, value + 1))}
      >
        <i className="bi bi-plus-lg" aria-hidden="true"></i>
      </button>
    </div>
  );
}
