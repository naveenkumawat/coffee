import { FormEvent, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { fetchCheckoutSummary, submitCheckout } from '../api/checkout';
import { ApiError, ApiValidationErrors } from '../api/client';
import { CheckoutItemCard } from '../components/checkout/CheckoutItemCard';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { StickyActionBar } from '../components/common/StickyActionBar';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { FormTextarea } from '../components/forms/FormTextarea';
import { useCartStore } from '../stores/cartStore';
import { Cart } from '../types/cart';
import {
  CheckoutFulfilmentMethod,
  CheckoutPaymentInstructions,
  CheckoutSummaryMeta,
} from '../types/checkout';
import { Order } from '../types/order';
import { formatCurrency, joinLabels } from '../utils/format';
import { getFieldError } from '../utils/forms';

interface CheckoutNavigationState {
  order?: Order;
  payment?: CheckoutPaymentInstructions;
}

export function CheckoutPage() {
  const navigate = useNavigate();
  const syncCart = useCartStore((state) => state.sync);
  const resetCart = useCartStore((state) => state.reset);
  const [cart, setCart] = useState<Cart | null>(null);
  const [summaryMeta, setSummaryMeta] = useState<CheckoutSummaryMeta | null>(null);
  const [fulfilmentMethod, setFulfilmentMethod] = useState<CheckoutFulfilmentMethod>('takeaway');
  const [form, setForm] = useState({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    pickup_name: '',
    pickup_phone: '',
    customer_notes: '',
    pickup_notes: '',
    delivery_address: '',
    delivery_phone: '',
    delivery_contact_name: '',
    delivery_notes: '',
  });
  const [sameAsContact, setSameAsContact] = useState(true);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [displayState, setDisplayState] = useState<'summary' | 'empty' | 'review-cart' | 'error'>('summary');
  const didHydrateDefaults = useRef(false);

  async function loadSummary(preserveForm = false): Promise<void> {
    setIsLoading(true);
    setMessage(null);

    try {
      const response = await fetchCheckoutSummary();
      setCart(response.data);
      setSummaryMeta(response.meta);
      syncCart(response.data, response.meta.summary);
      setDisplayState('summary');

      if (!didHydrateDefaults.current || !preserveForm) {
        const customerPhone = response.meta.customer.phone ?? '';
        const customerName = response.meta.customer.name || '';

        setForm((currentValue) => ({
          ...currentValue,
          customer_name: customerName || currentValue.customer_name,
          customer_email: response.meta.customer.email || currentValue.customer_email,
          customer_phone: currentValue.customer_phone || customerPhone,
          pickup_name: currentValue.pickup_name || customerName,
          pickup_phone: currentValue.pickup_phone || customerPhone,
          delivery_phone: currentValue.delivery_phone || customerPhone,
          delivery_contact_name: currentValue.delivery_contact_name || customerName,
        }));
        setSameAsContact(true);
        didHydrateDefaults.current = true;
      }
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const cartMessage = error.errors.cart?.[0] ?? error.message;
        setMessage(cartMessage);
        setDisplayState(cartMessage.toLowerCase().includes('empty') ? 'empty' : 'review-cart');
      } else {
        setMessage(error instanceof ApiError ? error.message : 'Unable to load checkout right now.');
        setDisplayState('error');
      }
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    void loadSummary();
  }, []);

  function updateField(field: keyof typeof form, value: string): void {
    setForm((currentValue) => {
      const nextValue = { ...currentValue, [field]: value };

      if (sameAsContact && (field === 'customer_name' || field === 'customer_phone')) {
        if (field === 'customer_name') {
          nextValue.pickup_name = value;
          nextValue.delivery_contact_name = value;
        }

        if (field === 'customer_phone') {
          nextValue.pickup_phone = value;
          nextValue.delivery_phone = value;
        }
      }

      return nextValue;
    });
  }

  function handleSameAsContactChange(checked: boolean): void {
    setSameAsContact(checked);

    if (checked) {
      setForm((currentValue) => ({
        ...currentValue,
        pickup_name: currentValue.customer_name,
        pickup_phone: currentValue.customer_phone,
        delivery_contact_name: currentValue.customer_name,
        delivery_phone: currentValue.customer_phone,
      }));
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (!summaryMeta?.checkout_token || isSubmitting) {
      return;
    }

    setIsSubmitting(true);
    setErrors({});
    setMessage(null);

    const pickupName = sameAsContact ? form.customer_name : form.pickup_name;
    const pickupPhone = sameAsContact ? form.customer_phone : form.pickup_phone;
    const deliveryContactName = sameAsContact ? form.customer_name : form.delivery_contact_name;
    const deliveryPhone = sameAsContact ? form.customer_phone : form.delivery_phone;

    try {
      const response = await submitCheckout({
        checkout_token: summaryMeta.checkout_token,
        fulfilment_method: fulfilmentMethod,
        customer_name: form.customer_name,
        customer_email: form.customer_email,
        customer_phone: form.customer_phone,
        customer_notes: form.customer_notes.trim() || null,
        ...(fulfilmentMethod === 'takeaway'
          ? {
              pickup_name: pickupName,
              pickup_phone: pickupPhone,
              pickup_notes: form.pickup_notes.trim() || null,
            }
          : {
              delivery_address: form.delivery_address.trim(),
              delivery_phone: deliveryPhone,
              delivery_contact_name: deliveryContactName.trim() || null,
              delivery_notes: form.delivery_notes.trim() || null,
            }),
      });

      resetCart();
      navigate(`/orders/${response.data.id}/confirmation`, {
        replace: true,
        state: {
          order: response.data,
          payment: response.meta.payment,
        } satisfies CheckoutNavigationState,
      });
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);

        if (error.status === 422) {
          await loadSummary(true);
        }
      } else {
        setMessage('Unable to place your order right now.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm fulfilment details." showBack />
        <LoadingSkeleton cardCount={3} lines={4} />
      </div>
    );
  }

  if (displayState === 'error') {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm fulfilment details." showBack />
        <ErrorState description={message ?? 'Unable to load checkout right now.'} onRetry={() => void loadSummary()} />
      </div>
    );
  }

  if (displayState === 'empty') {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm fulfilment details." showBack />
        <EmptyState
          title="Your cart is empty"
          description={message ?? 'Add a few menu items before continuing to checkout.'}
          actionLabel="Browse menu"
          actionHref="/menu"
        />
      </div>
    );
  }

  if (displayState === 'review-cart' || !cart || !summaryMeta) {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm fulfilment details." showBack />
        <EmptyState
          title="Review your cart first"
          description={message ?? 'One or more items changed. Please review your cart before trying again.'}
          actionLabel="Back to cart"
          actionHref="/cart"
        />
      </div>
    );
  }

  const pickupAddress = summaryMeta.fulfilment?.pickup_address;
  const deliveryDisclaimer =
    summaryMeta.fulfilment?.delivery_disclaimer ||
    'Delivery will be arranged through a third-party service. Delivery charges are payable separately by the customer.';

  return (
    <div className="page-container checkout-page has-sticky-cta">
      <PageHeader title="Checkout" description="Confirm details, then pay after placing the order." showBack />

      <FormFeedback message={message} variant="error" />

      <section className="account-section checkout-summary-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Order summary</span>
            <h2>Your order</h2>
            <p>{summaryMeta.summary.item_count} item(s) · cafe total only — no delivery fee added</p>
          </div>
          <Link to="/cart" className="link-button">
            Edit cart
          </Link>
        </div>

        <div className="checkout-list">
          {cart.items.map((item) => (
            <CheckoutItemCard
              key={item.id}
              name={item.product?.name ?? 'Coffee item'}
              subtitle={joinLabels([
                item.variant?.name,
                item.variant ? `${item.variant.serving_size_value} ${item.variant.serving_size_unit ?? ''}`.trim() : null,
              ])}
              detail={item.product?.customer_ingredient_summary}
              imageName={item.product?.name}
              imagePath={item.product?.image_path}
              quantity={item.quantity}
              amount={item.line_total}
            />
          ))}
        </div>

        <div className="summary-card checkout-summary-grid">
          <div>
            <span>Subtotal</span>
            <strong>{formatCurrency(summaryMeta.summary.subtotal)}</strong>
          </div>
          <div className="cart-summary-total">
            <span>Total due to cafe</span>
            <strong>{formatCurrency(summaryMeta.summary.total)}</strong>
          </div>
        </div>
      </section>

      <form className="checkout-form" onSubmit={(event) => void handleSubmit(event)}>
        <section className="account-section">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Fulfilment</span>
              <h2>How will you get it?</h2>
              <p>Takeaway pickup or third-party delivery.</p>
            </div>
          </div>

          <div className="checkout-fulfilment-toggle" role="radiogroup" aria-label="Fulfilment method">
            {(summaryMeta.fulfilment?.methods ?? [
              { value: 'takeaway' as const, label: 'Takeaway' },
              { value: 'delivery' as const, label: 'Delivery' },
            ]).map((method) => (
              <label
                key={method.value}
                className={[
                  'choice-row checkout-choice',
                  fulfilmentMethod === method.value ? 'is-selected' : '',
                ]
                  .filter(Boolean)
                  .join(' ')}
              >
                <input
                  type="radio"
                  name="fulfilment_method"
                  value={method.value}
                  checked={fulfilmentMethod === method.value}
                  onChange={() => setFulfilmentMethod(method.value)}
                />
                <span>{method.label}</span>
              </label>
            ))}
          </div>
          {getFieldError(errors, 'fulfilment_method') ? (
            <p className="form-error-text">{getFieldError(errors, 'fulfilment_method')}</p>
          ) : null}
        </section>

        <section className="account-section">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Contact</span>
              <h2>Your details</h2>
              <p>We’ll use these for order updates.</p>
            </div>
          </div>

          <div className="checkout-field-group">
            <FormField
              label="Full name"
              name="customer_name"
              autoComplete="name"
              value={form.customer_name}
              onChange={(event) => updateField('customer_name', event.target.value)}
              error={getFieldError(errors, 'customer_name')}
              required
            />
            <FormField
              label="Email"
              name="customer_email"
              type="email"
              autoComplete="email"
              inputMode="email"
              value={form.customer_email}
              onChange={(event) => updateField('customer_email', event.target.value)}
              error={getFieldError(errors, 'customer_email')}
              required
            />
            <FormField
              label="Phone"
              name="customer_phone"
              type="tel"
              autoComplete="tel"
              inputMode="tel"
              value={form.customer_phone}
              onChange={(event) => updateField('customer_phone', event.target.value)}
              error={getFieldError(errors, 'customer_phone')}
              required
            />
          </div>
        </section>

        {fulfilmentMethod === 'takeaway' ? (
          <section className="account-section">
            <div className="account-section-heading">
              <div>
                <span className="auth-badge">Takeaway</span>
                <h2>Pickup details</h2>
                <p>Collect from the cafe when ready.</p>
              </div>
            </div>

            {pickupAddress ? (
              <div className="checkout-pickup-address">
                <span>Cafe address</span>
                <strong style={{ whiteSpace: 'pre-wrap' }}>{pickupAddress}</strong>
              </div>
            ) : null}

            <label className="choice-row checkout-choice">
              <input
                type="checkbox"
                checked={sameAsContact}
                onChange={(event) => handleSameAsContactChange(event.target.checked)}
              />
              <span>Same as my contact details</span>
            </label>

            {!sameAsContact ? (
              <div className="checkout-field-group">
                <FormField
                  label="Pickup name"
                  name="pickup_name"
                  autoComplete="name"
                  value={form.pickup_name}
                  onChange={(event) => updateField('pickup_name', event.target.value)}
                  error={getFieldError(errors, 'pickup_name')}
                  required
                />
                <FormField
                  label="Pickup phone"
                  name="pickup_phone"
                  type="tel"
                  autoComplete="tel"
                  inputMode="tel"
                  value={form.pickup_phone}
                  onChange={(event) => updateField('pickup_phone', event.target.value)}
                  error={getFieldError(errors, 'pickup_phone')}
                  required
                />
              </div>
            ) : null}

            <div className="checkout-field-group">
              <FormTextarea
                label="Notes for the cafe (optional)"
                name="customer_notes"
                rows={2}
                placeholder="Extra hot, less sweet, allergy notes…"
                value={form.customer_notes}
                onChange={(event) => updateField('customer_notes', event.target.value)}
                error={getFieldError(errors, 'customer_notes')}
              />
              <FormTextarea
                label="Pickup notes (optional)"
                name="pickup_notes"
                rows={2}
                placeholder="Arriving in 10 minutes…"
                value={form.pickup_notes}
                onChange={(event) => updateField('pickup_notes', event.target.value)}
                error={getFieldError(errors, 'pickup_notes')}
              />
            </div>
          </section>
        ) : (
          <section className="account-section">
            <div className="account-section-heading">
              <div>
                <span className="auth-badge">Delivery</span>
                <h2>Delivery details</h2>
                <p>Third-party delivery — charges paid separately.</p>
              </div>
            </div>

            <div className="checkout-delivery-disclaimer" role="note">
              {deliveryDisclaimer}
            </div>

            <label className="choice-row checkout-choice">
              <input
                type="checkbox"
                checked={sameAsContact}
                onChange={(event) => handleSameAsContactChange(event.target.checked)}
              />
              <span>Contact name and phone same as above</span>
            </label>

            {!sameAsContact ? (
              <div className="checkout-field-group">
                <FormField
                  label="Delivery contact name"
                  name="delivery_contact_name"
                  autoComplete="name"
                  value={form.delivery_contact_name}
                  onChange={(event) => updateField('delivery_contact_name', event.target.value)}
                  error={getFieldError(errors, 'delivery_contact_name')}
                />
                <FormField
                  label="Delivery phone"
                  name="delivery_phone"
                  type="tel"
                  autoComplete="tel"
                  inputMode="tel"
                  value={form.delivery_phone}
                  onChange={(event) => updateField('delivery_phone', event.target.value)}
                  error={getFieldError(errors, 'delivery_phone')}
                  required
                />
              </div>
            ) : null}

            <div className="checkout-field-group">
              <FormTextarea
                label="Delivery address"
                name="delivery_address"
                rows={3}
                placeholder="Full address with landmark…"
                value={form.delivery_address}
                onChange={(event) => updateField('delivery_address', event.target.value)}
                error={getFieldError(errors, 'delivery_address')}
              />
              <FormTextarea
                label="Notes for the cafe (optional)"
                name="customer_notes"
                rows={2}
                placeholder="Extra hot, less sweet, allergy notes…"
                value={form.customer_notes}
                onChange={(event) => updateField('customer_notes', event.target.value)}
                error={getFieldError(errors, 'customer_notes')}
              />
              <FormTextarea
                label="Delivery notes (optional)"
                name="delivery_notes"
                rows={2}
                placeholder="Gate code, leave with concierge…"
                value={form.delivery_notes}
                onChange={(event) => updateField('delivery_notes', event.target.value)}
                error={getFieldError(errors, 'delivery_notes')}
              />
            </div>
          </section>
        )}

        <p className="checkout-payment-note">
          After you place the order, you’ll get UPI payment instructions. The cafe confirms payment before preparing.
        </p>

        <StickyActionBar
          eyebrow="Total due to cafe"
          title={isSubmitting ? 'Placing order…' : 'Place order'}
          value={formatCurrency(summaryMeta.summary.total)}
          note="You’ll pay next — Pending Payment until confirmed"
        >
          <button
            type="submit"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSubmitting}
            aria-busy={isSubmitting}
          >
            {isSubmitting ? 'Placing order…' : 'Confirm & place order'}
          </button>
        </StickyActionBar>
      </form>
    </div>
  );
}
