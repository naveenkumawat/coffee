import { FormEvent, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  createDeliveryAddress,
  deleteDeliveryAddress,
  DeliveryAddress,
  DeliveryAddressPayload,
  fetchDeliveryAddresses,
  makeDefaultDeliveryAddress,
} from '../api/deliveryAddresses';
import { ApiError, ApiValidationErrors } from '../api/client';
import { confirmYes } from '../components/common/ConfirmDialog';
import { EmptyState } from '../components/common/EmptyState';
import { ErrorState } from '../components/common/ErrorState';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { PageHeader } from '../components/common/PageHeader';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { getFieldError } from '../utils/forms';

const emptyForm: DeliveryAddressPayload = {
  label: '',
  recipient_name: '',
  phone: '',
  address_line_1: '',
  address_line_2: '',
  landmark: '',
  city: '',
  state: '',
  postal_code: '',
  is_default: false,
};

export function DeliveryAddressesPage() {
  const [addresses, setAddresses] = useState<DeliveryAddress[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [message, setMessage] = useState<string | null>(null);
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [form, setForm] = useState<DeliveryAddressPayload>(emptyForm);
  const [isSaving, setIsSaving] = useState(false);
  const [showForm, setShowForm] = useState(false);

  async function loadAddresses(): Promise<void> {
    setIsLoading(true);
    setMessage(null);

    try {
      const response = await fetchDeliveryAddresses();
      setAddresses(response.data);
    } catch (error) {
      setMessage(error instanceof ApiError ? error.message : 'Unable to load delivery addresses.');
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    void loadAddresses();
  }, []);

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSaving(true);
    setErrors({});
    setMessage(null);

    try {
      await createDeliveryAddress({
        ...form,
        label: form.label?.trim() || null,
        address_line_2: form.address_line_2?.trim() || null,
        landmark: form.landmark?.trim() || null,
      });
      setForm(emptyForm);
      setShowForm(false);
      await loadAddresses();
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);
      } else {
        setMessage('Unable to save address.');
      }
    } finally {
      setIsSaving(false);
    }
  }

  if (isLoading) {
    return (
      <div className="page-container">
        <PageHeader title="Delivery Addresses" />
        <LoadingSkeleton cardCount={2} lines={3} />
      </div>
    );
  }

  if (message && addresses.length === 0 && !showForm) {
    return (
      <div className="page-container">
        <PageHeader title="Delivery Addresses" />
        <ErrorState description={message} onRetry={() => void loadAddresses()} />
      </div>
    );
  }

  return (
    <div className="page-container">
      <PageHeader
        title="Delivery Addresses"
        description="Saved for faster checkout"
        showBack
        rightSlot={
          <Link to="/account" className="link-button">
            Account
          </Link>
        }
      />

      <FormFeedback message={message} variant="error" />

      {addresses.length === 0 && !showForm ? (
        <EmptyState
          title="No saved addresses"
          description="Add a delivery address to reuse it at checkout."
          actionLabel="Add address"
          onAction={() => setShowForm(true)}
        />
      ) : (
        <div className="account-section">
          {addresses.map((address) => (
            <div key={address.id} className="account-link-row" style={{ display: 'block' }}>
              <div className="d-flex justify-content-between gap-3">
                <div>
                  <strong>
                    {address.label || 'Address'}
                    {address.is_default ? ' · Default' : ''}
                  </strong>
                  <p className="checkout-prewrap mb-0">{address.formatted_address}</p>
                  <p className="text-muted mb-0">
                    {address.recipient_name} · {address.phone}
                  </p>
                </div>
              </div>
              <div className="d-flex gap-2 mt-3 flex-wrap">
                {!address.is_default ? (
                  <button
                    type="button"
                    className="btn btn-sm btn-outline-dark rounded-pill"
                    onClick={() => void makeDefaultDeliveryAddress(address.id).then(() => loadAddresses())}
                  >
                    Make default
                  </button>
                ) : null}
                <button
                  type="button"
                  className="btn btn-sm btn-outline-dark rounded-pill"
                  onClick={() => {
                    void (async () => {
                      const confirmed = await confirmYes({
                        title: 'Delete this address?',
                        body: 'This removes the saved delivery address from your account.',
                        confirmLabel: 'Delete address',
                        tone: 'danger',
                      });

                      if (!confirmed) {
                        return;
                      }

                      await deleteDeliveryAddress(address.id);
                      await loadAddresses();
                    })();
                  }}
                >
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {!showForm ? (
        <button type="button" className="btn btn-dark rounded-pill w-100 mt-3" onClick={() => setShowForm(true)}>
          Add address
        </button>
      ) : (
        <form className="account-section mt-4" onSubmit={(event) => void handleSubmit(event)}>
          <h2>New address</h2>
          <div className="checkout-field-group">
            <FormField
              label="Label (optional)"
              name="label"
              value={form.label ?? ''}
              onChange={(event) => setForm((current) => ({ ...current, label: event.target.value }))}
            />
            <FormField
              label="Recipient name"
              name="recipient_name"
              value={form.recipient_name}
              onChange={(event) => setForm((current) => ({ ...current, recipient_name: event.target.value }))}
              error={getFieldError(errors, 'recipient_name')}
              required
            />
            <FormField
              label="Phone"
              name="phone"
              value={form.phone}
              onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))}
              error={getFieldError(errors, 'phone')}
              required
            />
            <FormField
              label="Address line 1"
              name="address_line_1"
              value={form.address_line_1}
              onChange={(event) => setForm((current) => ({ ...current, address_line_1: event.target.value }))}
              error={getFieldError(errors, 'address_line_1')}
              required
            />
            <FormField
              label="Address line 2"
              name="address_line_2"
              value={form.address_line_2 ?? ''}
              onChange={(event) => setForm((current) => ({ ...current, address_line_2: event.target.value }))}
            />
            <FormField
              label="Landmark"
              name="landmark"
              value={form.landmark ?? ''}
              onChange={(event) => setForm((current) => ({ ...current, landmark: event.target.value }))}
            />
            <FormField
              label="City"
              name="city"
              value={form.city}
              onChange={(event) => setForm((current) => ({ ...current, city: event.target.value }))}
              error={getFieldError(errors, 'city')}
              required
            />
            <FormField
              label="State"
              name="state"
              value={form.state}
              onChange={(event) => setForm((current) => ({ ...current, state: event.target.value }))}
              error={getFieldError(errors, 'state')}
              required
            />
            <FormField
              label="Postal code"
              name="postal_code"
              value={form.postal_code}
              onChange={(event) => setForm((current) => ({ ...current, postal_code: event.target.value }))}
              error={getFieldError(errors, 'postal_code')}
              required
            />
            <label className="choice-row checkout-choice">
              <input
                type="checkbox"
                checked={Boolean(form.is_default)}
                onChange={(event) => setForm((current) => ({ ...current, is_default: event.target.checked }))}
              />
              <span>Make default</span>
            </label>
          </div>
          <button type="submit" className="btn btn-dark rounded-pill w-100" disabled={isSaving}>
            {isSaving ? 'Saving…' : 'Save address'}
          </button>
        </form>
      )}
    </div>
  );
}
