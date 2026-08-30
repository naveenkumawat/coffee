import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ApiError, ApiValidationErrors } from '../api/client';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { PageHeader } from '../components/common/PageHeader';
import { useAuthStore } from '../stores/authStore';
import { getFieldError } from '../utils/forms';
import { normalizeRedirectPath } from '../utils/navigation';

export function RegisterPage() {
  const register = useAuthStore((state) => state.register);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: ''
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
      await register({
        ...form,
        phone: form.phone.trim() || null
      });
      navigate(normalizeRedirectPath(searchParams.get('redirect')), { replace: true });
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);
      } else {
        setMessage('Unable to create your account right now.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="page-container">
      <PageHeader title="Register" description="Create a customer account for faster ordering and account access." />
      <section className="auth-card">
        <div className="auth-card-copy">
          <span className="auth-badge">Mobile-first signup</span>
          <h2>Start your Coffee account</h2>
          <p>We only ask for the essentials so you can get into the menu quickly.</p>
        </div>

        <FormFeedback message={message} variant="error" />

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)}>
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
            label="Email address"
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
            label="Phone number"
            name="phone"
            type="tel"
            autoComplete="tel"
            inputMode="tel"
            placeholder="Optional"
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
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSubmitting}>
            {isSubmitting ? 'Creating account...' : 'Create account'}
          </button>
        </form>

        <div className="auth-links">
          <Link to={`/login${searchParams.get('redirect') ? `?redirect=${encodeURIComponent(searchParams.get('redirect') ?? '')}` : ''}`}>Already have an account?</Link>
        </div>
      </section>
    </div>
  );
}
