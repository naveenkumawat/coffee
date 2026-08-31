import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ApiError, ApiValidationErrors } from '../api/client';
import { AuthCard } from '../components/auth/AuthCard';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { useAuthStore } from '../stores/authStore';
import { selectBrandName, useContentStore } from '../stores/contentStore';
import { useToastStore } from '../stores/toastStore';
import { withRedirectQuery } from '../utils/contentPages';
import { getFieldError } from '../utils/forms';
import { normalizeRedirectPath } from '../utils/navigation';

export function RegisterPage() {
  const register = useAuthStore((state) => state.register);
  const brandName = useContentStore((state) => selectBrandName(state.content));
  const toastSuccess = useToastStore((state) => state.success);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const redirect = searchParams.get('redirect');
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function updateField(field: keyof typeof form, value: string): void {
    setForm((currentValue) => ({ ...currentValue, [field]: value }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSubmitting(true);
    setErrors({});
    setMessage(null);

    try {
      const result = await register({
        ...form,
        phone: form.phone.trim() || null,
      });
      toastSuccess(result.mergedGuestCart ? 'Account created — your cart was saved' : 'Account created');
      navigate(normalizeRedirectPath(redirect), { replace: true });
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);
      } else {
        setMessage('Unable to create your account right now. Please try again.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="page-container auth-page">
      <button type="button" className="auth-back-link" onClick={() => navigate(-1)}>
        <i className="bi bi-arrow-left" aria-hidden="true"></i>
        Back
      </button>
      <AuthCard
        badge={`Join ${brandName}`}
        title="Create your account"
        description="Just the essentials so you can get into the menu quickly."
        footer={
          <Link to={withRedirectQuery('/login', redirect)}>Already have an account? Sign in</Link>
        }
      >
        <FormFeedback message={message} variant="error" />

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <FormField
            label="Full name"
            name="name"
            autoComplete="name"
            placeholder="Your name"
            value={form.name}
            onChange={(event) => updateField('name', event.target.value)}
            error={getFieldError(errors, 'name')}
            required
          />
          <FormField
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            inputMode="email"
            placeholder="you@example.com"
            value={form.email}
            onChange={(event) => updateField('email', event.target.value)}
            error={getFieldError(errors, 'email')}
            required
          />
          <FormField
            label="Phone (optional)"
            name="phone"
            type="tel"
            autoComplete="tel"
            inputMode="tel"
            placeholder="For pickup updates"
            value={form.phone}
            onChange={(event) => updateField('phone', event.target.value)}
            error={getFieldError(errors, 'phone')}
          />
          <PasswordField
            label="Password"
            name="password"
            autoComplete="new-password"
            placeholder="Create a password"
            value={form.password}
            onChange={(event) => updateField('password', event.target.value)}
            error={getFieldError(errors, 'password')}
            hint="Use at least 8 characters."
            required
          />
          <PasswordField
            label="Confirm password"
            name="password_confirmation"
            autoComplete="new-password"
            placeholder="Re-enter your password"
            value={form.password_confirmation}
            onChange={(event) => updateField('password_confirmation', event.target.value)}
            error={getFieldError(errors, 'password_confirmation')}
            required
          />
          <button
            type="submit"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSubmitting}
            aria-busy={isSubmitting}
          >
            {isSubmitting ? 'Creating account…' : 'Create account'}
          </button>
        </form>
      </AuthCard>
    </div>
  );
}
