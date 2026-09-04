import { CheckoutPaymentMethod, CheckoutPaymentMethodOption } from '../../types/checkout';

interface PaymentMethodSelectorProps {
  methods: CheckoutPaymentMethodOption[];
  value: CheckoutPaymentMethod;
  onChange: (value: CheckoutPaymentMethod) => void;
  error?: string | null;
}

export function PaymentMethodSelector({ methods, value, onChange, error }: PaymentMethodSelectorProps) {
  if (methods.length === 0) {
    return (
      <p className="summary-warning" role="status">
        No payment methods are currently available. Please contact the café.
      </p>
    );
  }

  return (
    <div className="checkout-field-group">
      <div className="fulfilment-options" role="radiogroup" aria-label="Payment method">
        {methods.map((method) => {
          const key = method.key === 'manual' ? 'manual_upi' : method.key;
          const selected = value === key;

          return (
            <label
              key={method.key}
              className={['fulfilment-option', selected ? 'is-selected' : ''].filter(Boolean).join(' ')}
            >
              <input
                type="radio"
                name="payment_method"
                value={key}
                checked={selected}
                onChange={() => onChange(key as CheckoutPaymentMethod)}
              />
              <span className="fulfilment-option-copy">
                <strong>{method.label}</strong>
                {method.subtitle ? <span>{method.subtitle}</span> : null}
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
