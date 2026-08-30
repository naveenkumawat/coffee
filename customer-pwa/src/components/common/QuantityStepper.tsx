interface QuantityStepperProps {
  value: number;
  min?: number;
  max?: number;
  disabled?: boolean;
  onChange: (value: number) => void;
}

export function QuantityStepper({ value, min = 1, max = 20, disabled = false, onChange }: QuantityStepperProps) {
  return (
    <div className="quantity-stepper">
      <button
        type="button"
        className="icon-button"
        disabled={disabled || value <= min}
        onClick={() => onChange(Math.max(min, value - 1))}
      >
        <i className="bi bi-dash-lg"></i>
      </button>
      <span>{value}</span>
      <button
        type="button"
        className="icon-button"
        disabled={disabled || value >= max}
        onClick={() => onChange(Math.min(max, value + 1))}
      >
        <i className="bi bi-plus-lg"></i>
      </button>
    </div>
  );
}
