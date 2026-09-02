import { FormEvent, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { fetchCheckoutSummary, submitCheckout } from '../api/checkout';
import { ApiError, ApiValidationErrors } from '../api/client';
import { CheckoutItemCard } from '../components/checkout/CheckoutItemCard';
import { FulfilmentMethodSelector } from '../components/checkout/FulfilmentMethodSelector';
import { PaymentMethodSelector } from '../components/checkout/PaymentMethodSelector';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { FlowIntro } from '../components/common/FlowIntro';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { StickyActionBar } from '../components/common/StickyActionBar';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { FormTextarea } from '../components/forms/FormTextarea';
import { OrderTaxBreakdown } from '../components/orders/OrderTaxBreakdown';
import { useCartStore } from '../stores/cartStore';
import { selectAvailability, useContentStore } from '../stores/contentStore';
import { Cart } from '../types/cart';
import {
  CheckoutFulfilmentMethod,
  CheckoutPaymentInstructions,
  CheckoutPaymentMethod,
  CheckoutSummaryMeta,
} from '../types/checkout';
import { Order } from '../types/order';
import { cartDiscounts } from '../utils/discounts';
import { formatCurrency, joinLabels } from '../utils/format';
import { getFieldError } from '../utils/forms';

interface CheckoutNavigationState {
  order?: Order;
  payment?: CheckoutPaymentInstructions | null;
}

export function CheckoutPage() {
  const navigate = useNavigate();
  const syncCart = useCartStore((state) => state.sync);
  const resetCart = useCartStore((state) => state.reset);
  const [cart, setCart] = useState<Cart | null>(null);
  const [summaryMeta, setSummaryMeta] = useState<CheckoutSummaryMeta | null>(null);
  const [fulfilmentMethod, setFulfilmentMethod] = useState<CheckoutFulfilmentMethod>('takeaway');
  const [paymentMethod, setPaymentMethod] = useState<CheckoutPaymentMethod>('manual_upi');
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
  const availability = useContentStore((state) => selectAvailability(state.content));
  const orderingClosed = Boolean(availability && !availability.available);

  async function loadSummary(
    preserveForm = false,
    method: CheckoutFulfilmentMethod = fulfilmentMethod,
  ): Promise<void> {
    const showFullLoading = !didHydrateDefaults.current;

    if (showFullLoading) {
      setIsLoading(true);
    }

    setMessage(null);

    try {
      const response = await fetchCheckoutSummary(method);
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
      if (showFullLoading) {
        setIsLoading(false);
      }
    }
  }

  useEffect(() => {
    const preserveForm = didHydrateDefaults.current;
    void loadSummary(preserveForm, fulfilmentMethod);
  }, [fulfilmentMethod]);


  useEffect(() => {
    const available = summaryMeta?.fulfilment?.methods?.map((method) => method.value) ?? ['takeaway', 'delivery'];

    if (!available.includes(fulfilmentMethod)) {
      setFulfilmentMethod(available[0] ?? 'takeaway');
    }
  }, [summaryMeta?.fulfilment?.methods, fulfilmentMethod]);

  useEffect(() => {
    const eligible = summaryMeta?.payment_methods?.[fulfilmentMethod] ?? [];
    const keys = eligible.map((method) => (method.key === 'manual' ? 'manual_upi' : method.key));

    if (keys.length === 0) {
      setPaymentMethod('manual_upi');

      return;
    }

    if (!keys.includes(paymentMethod)) {
      setPaymentMethod((keys[0] as CheckoutPaymentMethod) ?? 'manual_upi');
    }
  }, [summaryMeta?.payment_methods, fulfilmentMethod, paymentMethod]);

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
        payment_method: paymentMethod,
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
        const securityCode = error.code;
        const securityMessage =
          error.errors.ordering?.[0]
          ?? error.errors.checkout?.[0]
          ?? error.errors.payment_proof?.[0]
          ?? error.message;

        if (
          securityCode === 'pending_limit'
          || securityCode === 'ordering_blocked'
          || securityCode === 'rate_limit'
          || securityCode === 'cafe_closed'
        ) {
          setErrors(error.errors);
          setMessage(securityMessage);
          return;
        }

        if (error.status === 429) {
          setErrors(error.errors);
          setMessage(securityMessage || 'Too many order attempts. Please try again shortly.');
          return;
        }

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
      <div className="page-container checkout-page">
        <FlowIntro title="Checkout" subtitle="Loading your order…" />
        <LoadingSkeleton cardCount={3} lines={4} />
      </div>
    );
  }

  if (displayState === 'error') {
    return (
      <div className="page-container checkout-page">
        <FlowIntro title="Checkout" />
        <ErrorState description={message ?? 'Unable to load checkout right now.'} onRetry={() => void loadSummary()} />
      </div>
    );
  }

  if (displayState === 'empty') {
    return (
      <div className="page-container checkout-page">
        <FlowIntro title="Checkout" />
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
      <div className="page-container checkout-page">
        <FlowIntro title="Checkout" />
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
  const deliveryDisclaimer = summaryMeta.fulfilment?.delivery_disclaimer?.trim() || null;
  const fulfilmentMethods = summaryMeta.fulfilment?.methods ?? [
    { value: 'takeaway' as const, label: 'Takeaway' },
    { value: 'delivery' as const, label: 'Delivery' },
  ];
  const paymentMethods = summaryMeta.payment_methods?.[fulfilmentMethod] ?? [
    { key: 'manual_upi', label: 'UPI / QR', subtitle: 'Pay now and submit payment proof.' },
  ];
  const isCashSelected = paymentMethod === 'cash';
  const placeOrderLabel = isSubmitting
    ? 'Placing order…'
    : orderingClosed
      ? 'Ordering Closed'
      : `Place Order · ${formatCurrency(summaryMeta.summary.total)}`;
  const stickyNote = orderingClosed
    ? availability?.message ?? 'Checkout unavailable right now.'
    : isCashSelected
      ? fulfilmentMethod === 'takeaway'
        ? 'Cash at pickup — no payment screenshot needed'
        : 'Pay cash at the cafe — no payment screenshot needed'
      : 'Payment comes next — Pending Payment until confirmed';
  const paymentSectionSubtitle = isCashSelected
    ? fulfilmentMethod === 'takeaway'
      ? 'Pay cash when collecting'
      : 'Pay at the cafe'
    : paymentMethods.some((method) => method.key === 'cash')
      ? 'Choose how you will pay'
      : 'Pay after placing your order';
  return (
    <div className="page-container checkout-page has-sticky-cta">
      <FlowIntro
        title="Checkout"
        subtitle={`${summaryMeta.summary.item_count} item${summaryMeta.summary.item_count === 1 ? '' : 's'} · cafe total only`}
        trailing={
          <Link to="/cart" className="link-button">
            Edit cart
          </Link>
        }
      />

      <FormFeedback message={message} variant="error" />

      <form className="checkout-form" onSubmit={(event) => void handleSubmit(event)}>
        <section className="checkout-section" aria-labelledby="checkout-fulfilment-heading">
          <div className="checkout-section-heading">
            <h2 id="checkout-fulfilment-heading">Fulfilment</h2>
            <p>How will you get your order?</p>
          </div>

          <FulfilmentMethodSelector
            methods={fulfilmentMethods}
            value={fulfilmentMethod}
            onChange={setFulfilmentMethod}
            error={getFieldError(errors, 'fulfilment_method')}
          />
        </section>

        <section className="checkout-section" aria-labelledby="checkout-contact-heading">
          <div className="checkout-section-heading">
            <h2 id="checkout-contact-heading">Contact</h2>
            <p>For order updates</p>
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
          <section className="checkout-section" aria-labelledby="checkout-pickup-heading">
            <div className="checkout-section-heading">
              <h2 id="checkout-pickup-heading">Pickup</h2>
              <p>Collect from the cafe when ready.</p>
            </div>

            {pickupAddress ? (
              <div className="checkout-inline-note">
                <span>Cafe address</span>
                <strong className="checkout-prewrap">{pickupAddress}</strong>
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
        ) : null}


        {fulfilmentMethod === 'delivery' ? (
          <section className="checkout-section" aria-labelledby="checkout-delivery-heading">
            <div className="checkout-section-heading">
              <h2 id="checkout-delivery-heading">Delivery</h2>
              <p>Third-party delivery — charges paid separately.</p>
            </div>

            {deliveryDisclaimer ? (
              <div className="checkout-inline-note" role="note">
                {deliveryDisclaimer}
              </div>
            ) : null}

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
        ) : null}

        <section className="checkout-section" aria-labelledby="checkout-summary-heading">
          <div className="checkout-section-heading checkout-section-heading-row">
            <div>
              <h2 id="checkout-summary-heading">Order summary</h2>
              <p>{summaryMeta.summary.item_count} item(s)</p>
            </div>
          </div>

          <div className="checkout-list">
            {cart.items.map((item) => (
              <CheckoutItemCard
                key={item.id}
                name={item.product?.name ?? 'Coffee item'}
                subtitle={joinLabels([
                  item.variant?.name,
                  item.variant
                    ? `${item.variant.serving_size_value} ${item.variant.serving_size_unit ?? ''}`.trim()
                    : null,
                ])}
                addOns={item.add_ons}
                editHref="/cart"
                quantity={item.quantity}
                unitPrice={item.unit_price ?? item.variant?.price}
                amount={item.line_total}
                compact
              />
            ))}
          </div>

          <OrderTaxBreakdown
            subtotal={summaryMeta.summary.subtotal}
            total={summaryMeta.summary.total}
            tax={summaryMeta.summary.tax}
            discounts={cartDiscounts(summaryMeta.summary)}
            discountTotal={summaryMeta.summary.discount_total}
            totalLabel="Cafe total"
          />
        </section>

        <section className="checkout-section checkout-payment-section" aria-labelledby="checkout-payment-heading">
          <div className="checkout-section-heading">
            <h2 id="checkout-payment-heading">Payment method</h2>
            <p>{paymentSectionSubtitle}</p>
          </div>

          <PaymentMethodSelector
            methods={paymentMethods}
            value={paymentMethod}
            onChange={setPaymentMethod}
            error={getFieldError(errors, 'payment_method')}
          />
        </section>

        <StickyActionBar
          eyebrow="Cafe total"
          title={orderingClosed ? 'Ordering Closed' : isSubmitting ? 'Placing order…' : 'Ready to place'}
          value={formatCurrency(summaryMeta.summary.total)}
          note={stickyNote}
        >
          <button
            type="submit"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSubmitting || orderingClosed}
            aria-busy={isSubmitting}
          >
            {placeOrderLabel}
          </button>
        </StickyActionBar>
      </form>
    </div>
  );
}
