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
import { CheckoutPaymentInstructions, CheckoutSummaryMeta } from '../types/checkout';
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
  const [form, setForm] = useState({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    pickup_name: '',
    pickup_phone: '',
    customer_notes: '',
    pickup_notes: '',
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
        }

        if (field === 'customer_phone') {
          nextValue.pickup_phone = value;
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

    try {
      const response = await submitCheckout({
        checkout_token: summaryMeta.checkout_token,
        customer_name: form.customer_name,
        customer_email: form.customer_email,
        customer_phone: form.customer_phone,
        pickup_name: pickupName,
        pickup_phone: pickupPhone,
        customer_notes: form.customer_notes.trim() || null,
        pickup_notes: form.pickup_notes.trim() || null,
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
        <PageHeader title="Checkout" description="Confirm pickup details." showBack />
        <LoadingSkeleton cardCount={3} lines={4} />
      </div>
    );
  }

  if (displayState === 'error') {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm pickup details." showBack />
        <ErrorState description={message ?? 'Unable to load checkout right now.'} onRetry={() => void loadSummary()} />
      </div>
    );
  }

  if (displayState === 'empty') {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm pickup details." showBack />
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
        <PageHeader title="Checkout" description="Confirm pickup details." showBack />
        <EmptyState
          title="Review your cart first"
          description={message ?? 'One or more items changed. Please review your cart before trying again.'}
          actionLabel="Back to cart"
          actionHref="/cart"
        />
      </div>
    );
  }

  return (
    <div className="page-container checkout-page has-sticky-cta">
      <PageHeader title="Checkout" description="Confirm details, then pay after placing the order." showBack />

      <FormFeedback message={message} variant="error" />

      <section className="account-section checkout-summary-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Order summary</span>
            <h2>Your pickup order</h2>
            <p>{summaryMeta.summary.item_count} item(s) · pay after you place the order</p>
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
            <span>Total due</span>
            <strong>{formatCurrency(summaryMeta.summary.total)}</strong>
          </div>
        </div>
      </section>

      <form className="checkout-form" onSubmit={(event) => void handleSubmit(event)}>
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

        <section className="account-section">
          <div className="account-section-heading">
            <div>
              <span className="auth-badge">Pickup</span>
              <h2>Who is collecting?</h2>
              <p>Cafe pickup only — no delivery.</p>
            </div>
          </div>

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

        <p className="checkout-payment-note">
          After you place the order, you’ll get UPI payment instructions. The cafe confirms payment before preparing.
        </p>

        <StickyActionBar
          eyebrow="Total due"
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
