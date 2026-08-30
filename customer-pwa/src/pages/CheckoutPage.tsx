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
    pickup_notes: ''
  });
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

        setForm((currentValue) => ({
          ...currentValue,
          customer_name: response.meta.customer.name || currentValue.customer_name,
          customer_email: response.meta.customer.email || currentValue.customer_email,
          customer_phone: currentValue.customer_phone || customerPhone,
          pickup_name: currentValue.pickup_name || response.meta.customer.name || '',
          pickup_phone: currentValue.pickup_phone || customerPhone
        }));
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
    setForm((currentValue) => ({ ...currentValue, [field]: value }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (!summaryMeta?.checkout_token || isSubmitting) {
      return;
    }

    setIsSubmitting(true);
    setErrors({});
    setMessage(null);

    try {
      const response = await submitCheckout({
        checkout_token: summaryMeta.checkout_token,
        customer_name: form.customer_name,
        customer_email: form.customer_email,
        customer_phone: form.customer_phone,
        pickup_name: form.pickup_name,
        pickup_phone: form.pickup_phone,
        customer_notes: form.customer_notes.trim() || null,
        pickup_notes: form.pickup_notes.trim() || null
      });

      resetCart();
      navigate(`/orders/${response.data.id}/confirmation`, {
        replace: true,
        state: {
          order: response.data,
          payment: response.meta.payment
        } satisfies CheckoutNavigationState
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
        <PageHeader title="Checkout" description="Confirm your details and pickup order." showBack />
        <LoadingSkeleton cardCount={3} lines={5} />
      </div>
    );
  }

  if (displayState === 'error') {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm your details and pickup order." showBack />
        <ErrorState description={message ?? 'Unable to load checkout right now.'} onRetry={() => void loadSummary()} />
      </div>
    );
  }

  if (displayState === 'empty') {
    return (
      <div className="page-container">
        <PageHeader title="Checkout" description="Confirm your details and pickup order." showBack />
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
        <PageHeader title="Checkout" description="Confirm your details and pickup order." showBack />
        <EmptyState
          title="Review your cart first"
          description={message ?? 'One or more items changed in price or availability. Please review your cart before retrying checkout.'}
          actionLabel="Back to cart"
          actionHref="/cart"
        />
      </div>
    );
  }

  return (
    <div className="page-container checkout-page">
      <PageHeader title="Checkout" description="Live totals and availability are verified by the server before your order is placed." showBack />

      <FormFeedback message={message} variant="error" />

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Order summary</span>
            <h2>Review server totals</h2>
            <p>{summaryMeta.summary.item_count} item(s) ready for pickup checkout.</p>
          </div>
          <Link to="/cart" className="link-button">Edit cart</Link>
        </div>

        <div className="checkout-list">
          {cart.items.map((item) => (
            <CheckoutItemCard
              key={item.id}
              name={item.product?.name ?? 'Coffee item'}
              subtitle={joinLabels([
                item.variant?.name,
                item.variant ? `${item.variant.serving_size_value} ${item.variant.serving_size_unit ?? ''}`.trim() : null
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
          <div>
            <span>Total</span>
            <strong>{formatCurrency(summaryMeta.summary.total)}</strong>
          </div>
        </div>
      </section>

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Pickup details</span>
            <h2>Tell us who is collecting</h2>
            <p>We’ve prefilled your account details where possible to keep checkout quick on mobile.</p>
          </div>
        </div>

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)}>
          <FormField
            label="Customer name"
            name="customer_name"
            autoComplete="name"
            value={form.customer_name}
            onChange={(event) => updateField('customer_name', event.target.value)}
            error={getFieldError(errors, 'customer_name')}
            required
          />
          <FormField
            label="Customer email"
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
            label="Customer phone"
            name="customer_phone"
            type="tel"
            autoComplete="tel"
            inputMode="tel"
            value={form.customer_phone}
            onChange={(event) => updateField('customer_phone', event.target.value)}
            error={getFieldError(errors, 'customer_phone')}
            required
          />
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
          <FormTextarea
            label="Customer notes"
            name="customer_notes"
            rows={3}
            placeholder="Optional notes for the cafe"
            value={form.customer_notes}
            onChange={(event) => updateField('customer_notes', event.target.value)}
            error={getFieldError(errors, 'customer_notes')}
          />
          <FormTextarea
            label="Pickup notes"
            name="pickup_notes"
            rows={3}
            placeholder="Optional pickup notes"
            value={form.pickup_notes}
            onChange={(event) => updateField('pickup_notes', event.target.value)}
            error={getFieldError(errors, 'pickup_notes')}
          />

          <StickyActionBar
            eyebrow="Checkout token secured"
            title="Place order"
            value={formatCurrency(summaryMeta.summary.total)}
            note="The backend validates price, availability, and ownership again before creating the order."
          >
            <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSubmitting}>
              {isSubmitting ? 'Placing order...' : 'Confirm order'}
            </button>
          </StickyActionBar>
        </form>
      </section>
    </div>
  );
}
