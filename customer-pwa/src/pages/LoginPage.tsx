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

export function LoginPage() {
  const login = useAuthStore((state) => state.login);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(true);
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSubmitting(true);
    setErrors({});
    setMessage(null);

    try {
      await login({ email, password, remember });
      navigate(normalizeRedirectPath(searchParams.get('redirect')), { replace: true });
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);
      } else {
        setMessage('Unable to sign you in right now.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="page-container">
      <PageHeader title="Login" description="Sign in to sync your cart, orders, and account details." />
      <section className="auth-card">
        <div className="auth-card-copy">
          <span className="auth-badge">Sanctum session</span>
          <h2>Welcome back</h2>
          <p>Use your customer account to continue with the live Coffee ordering flow.</p>
        </div>

        <FormFeedback message={message} variant="error" />

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)}>
          <FormField
            label="Email address"
            name="email"
            type="email"
            autoComplete="email"
            inputMode="email"
            placeholder="you@example.com"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={getFieldError(errors, 'email')}
            required
          />
          <PasswordField
            label="Password"
            name="password"
            autoComplete="current-password"
            placeholder="Enter your password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={getFieldError(errors, 'password')}
            required
          />
          <label className="choice-row">
            <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} />
            <span>Keep me signed in on this device</span>
          </label>
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSubmitting}>
            {isSubmitting ? 'Signing in...' : 'Sign in'}
          </button>
        </form>

        <div className="auth-links">
          <Link to="/forgot-password">Forgot password?</Link>
          <Link to={`/register${searchParams.get('redirect') ? `?redirect=${encodeURIComponent(searchParams.get('redirect') ?? '')}` : ''}`}>Create account</Link>
        </div>
      </section>
    </div>
  );
}
