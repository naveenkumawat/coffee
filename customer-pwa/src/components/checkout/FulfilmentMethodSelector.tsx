import { CheckoutFulfilmentMethod } from '../../types/checkout';

interface FulfilmentOption {
  value: CheckoutFulfilmentMethod;
  label: string;
}

interface FulfilmentMethodSelectorProps {
  methods: FulfilmentOption[];
  value: CheckoutFulfilmentMethod;
  onChange: (method: CheckoutFulfilmentMethod) => void;
  error?: string | null;
}

const METHOD_META: Record<
  CheckoutFulfilmentMethod,
  { icon: string; subtitle: string }
> = {
  takeaway: {
    icon: 'bi-bag',
    subtitle: 'Collect from the cafe when ready.',
  },
  delivery: {
    icon: 'bi-bicycle',
    subtitle: 'Third-party delivery — fees paid separately.',
  },
};

export function FulfilmentMethodSelector({
  methods,
  value,
  onChange,
  error = null,
}: FulfilmentMethodSelectorProps) {
  return (
    <div className="fulfilment-selector-wrap">
      <div
        className={`fulfilment-selector count-${Math.min(methods.length, 3)}`}
        role="radiogroup"
        aria-label="Fulfilment method"
      >
        {methods.map((method) => {
          const meta = METHOD_META[method.value];
          const selected = value === method.value;

          return (
            <label
              key={method.value}
              className={['fulfilment-option', selected ? 'is-selected' : ''].filter(Boolean).join(' ')}
            >
              <input
                type="radio"
                name="fulfilment_method"
                value={method.value}
                checked={selected}
                onChange={() => onChange(method.value)}
              />
              <span className="fulfilment-option-icon" aria-hidden="true">
                <i className={`bi ${meta.icon}`}></i>
              </span>
              <span className="fulfilment-option-copy">
                <strong>{method.label}</strong>
                <span>{meta.subtitle}</span>
              </span>
            </label>
          );
        })}
      </div>
      {error ? (
        <p className="form-error-text" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}
