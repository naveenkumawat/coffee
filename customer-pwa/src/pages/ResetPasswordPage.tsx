import { FormEvent, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { resetCustomerPassword } from '../api/auth';
import { ApiError, ApiValidationErrors } from '../api/client';
import { PageHeader } from '../components/common/PageHeader';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { useAuthStore } from '../stores/authStore';
import { useCartStore } from '../stores/cartStore';
import { getFieldError } from '../utils/forms';

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const syncCustomer = useAuthStore((state) => state.syncCustomer);
  const refreshCartCount = useCartStore((state) => state.refreshCount);
  const [form, setForm] = useState({
    email: searchParams.get('email') ?? '',
    token: searchParams.get('token') ?? '',
    password: '',
    password_confirmation: ''
  });
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const isTokenMissing = useMemo(() => !form.token.trim() || !form.email.trim(), [form.email, form.token]);

  function updateField(field: keyof typeof form, value: string): void {
    setForm((currentValue) => ({ ...currentValue, [field]: value }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (isTokenMissing) {
      setMessage('This reset link is incomplete. Please use the full email link or request a new one.');
      return;
    }

    setIsSubmitting(true);
    setErrors({});
    setMessage(null);

    try {
      const response = await resetCustomerPassword(form);
      syncCustomer(response.data);
      await refreshCartCount();
      navigate('/account', { replace: true });
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);
      } else {
        setMessage('Unable to reset your password right now.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="page-container">
      <PageHeader title="Reset password" description="Choose a new password for your customer account." showBack />
      <section className="auth-card">
        <div className="auth-card-copy">
          <span className="auth-badge">Secure reset</span>
          <h2>Create a new password</h2>
          <p>The Laravel API remains authoritative for reset-token validation and password rules.</p>
        </div>

        <FormFeedback message={message} variant="error" />

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)}>
          <FormField
            label="Email address"
            name="email"
            type="email"
            autoComplete="email"
            inputMode="email"
            value={form.email}
            onChange={(event) => updateField('email', event.target.value)}
            error={getFieldError(errors, 'email')}
            required
          />
          <FormField
            label="Reset token"
            name="token"
            value={form.token}
            onChange={(event) => updateField('token', event.target.value)}
            error={getFieldError(errors, 'token')}
            hint="This is normally prefilled from the reset link."
            required
          />
          <PasswordField
            label="New password"
            name="password"
            autoComplete="new-password"
            value={form.password}
            onChange={(event) => updateField('password', event.target.value)}
            error={getFieldError(errors, 'password')}
            required
          />
          <PasswordField
            label="Confirm new password"
            name="password_confirmation"
            autoComplete="new-password"
            value={form.password_confirmation}
            onChange={(event) => updateField('password_confirmation', event.target.value)}
            error={getFieldError(errors, 'password_confirmation')}
            required
          />
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSubmitting}>
            {isSubmitting ? 'Resetting password...' : 'Reset password'}
          </button>
        </form>
      </section>
    </div>
  );
}
