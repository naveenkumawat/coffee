import { FormEvent, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { resetCustomerPassword } from '../api/auth';
import { ApiError, ApiValidationErrors } from '../api/client';
import { AuthCard } from '../components/auth/AuthCard';
import { PageHeader } from '../components/common/PageHeader';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { useAuthStore } from '../stores/authStore';
import { useCartStore } from '../stores/cartStore';
import { useToastStore } from '../stores/toastStore';
import { getFieldError } from '../utils/forms';

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const syncCustomer = useAuthStore((state) => state.syncCustomer);
  const refreshCartCount = useCartStore((state) => state.refreshCount);
  const toastSuccess = useToastStore((state) => state.success);
  const tokenFromLink = searchParams.get('token') ?? '';
  const [form, setForm] = useState({
    email: searchParams.get('email') ?? '',
    token: tokenFromLink,
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const isTokenMissing = useMemo(() => !form.token.trim() || !form.email.trim(), [form.email, form.token]);
  const showTokenField = !tokenFromLink.trim();

  function updateField(field: keyof typeof form, value: string): void {
    setForm((currentValue) => ({ ...currentValue, [field]: value }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (isTokenMissing) {
      setMessage('This reset link is incomplete. Request a new one from Forgot password.');
      return;
    }

    setIsSubmitting(true);
    setErrors({});
    setMessage(null);

    try {
      const response = await resetCustomerPassword(form);
      syncCustomer(response.data);
      await refreshCartCount();
      toastSuccess('Password updated');
      navigate('/account', { replace: true });
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);
      } else {
        setMessage('Unable to reset your password right now. Please try again.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="page-container auth-page">
      <PageHeader title="Reset password" description="Choose a new password for your account." showBack />
      <AuthCard
        badge="New password"
        title="Create a new password"
        description="Pick something memorable that you haven’t used here before."
        footer={<Link to="/forgot-password">Need a new reset link?</Link>}
      >
        <FormFeedback message={message} variant="error" />

        {isTokenMissing && !showTokenField ? (
          <FormFeedback
            message="This reset link looks incomplete. Request a fresh link and open it from your email."
            variant="error"
          />
        ) : null}

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <FormField
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            inputMode="email"
            value={form.email}
            onChange={(event) => updateField('email', event.target.value)}
            error={getFieldError(errors, 'email')}
            required
          />
          {showTokenField ? (
            <FormField
              label="Reset code from email"
              name="token"
              value={form.token}
              onChange={(event) => updateField('token', event.target.value)}
              error={getFieldError(errors, 'token')}
              hint="Paste the code from your reset email if it wasn’t filled automatically."
              required
            />
          ) : (
            <input type="hidden" name="token" value={form.token} />
          )}
          <PasswordField
            label="New password"
            name="password"
            autoComplete="new-password"
            value={form.password}
            onChange={(event) => updateField('password', event.target.value)}
            error={getFieldError(errors, 'password')}
            hint="Use at least 8 characters."
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
          <button
            type="submit"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSubmitting || isTokenMissing}
            aria-busy={isSubmitting}
          >
            {isSubmitting ? 'Updating…' : 'Save new password'}
          </button>
        </form>
      </AuthCard>
    </div>
  );
}
