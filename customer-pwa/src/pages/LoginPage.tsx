import { FormEvent, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ApiError, ApiValidationErrors } from '../api/client';
import { AuthCard } from '../components/auth/AuthCard';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { useAuthStore } from '../stores/authStore';
import { useToastStore } from '../stores/toastStore';
import { withRedirectQuery } from '../utils/contentPages';
import { getFieldError } from '../utils/forms';
import { normalizeRedirectPath } from '../utils/navigation';
import { isWaiter } from '../utils/roles';

export function LoginPage() {
  const login = useAuthStore((state) => state.login);
  const toastSuccess = useToastStore((state) => state.success);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const redirect = searchParams.get('redirect');
  const [loginValue, setLoginValue] = useState('');
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
      const result = await login({ login: loginValue.trim(), password, remember });
      if (isWaiter(result.customer)) {
        toastSuccess('Signed in');
        navigate('/waiter', { replace: true });
        return;
      }

      toastSuccess(result.mergedGuestCart ? 'Signed in — your cart was updated' : 'Signed in');
      navigate(normalizeRedirectPath(redirect), { replace: true });
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setMessage(error.message);
      } else {
        setMessage('Unable to sign you in right now. Please try again.');
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
        badge="Customer account"
        title="Welcome back"
        description="Sign in with your email or mobile number to keep favourites and orders in sync."
        footer={
          <>
            <Link to="/forgot-password">Forgot password?</Link>
            <Link to={withRedirectQuery('/register', redirect)}>Create account</Link>
          </>
        }
      >
        <FormFeedback message={message} variant="error" />

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <FormField
            label="Email or mobile number"
            name="login"
            type="text"
            autoComplete="username"
            inputMode="text"
            placeholder="you@example.com or mobile"
            value={loginValue}
            onChange={(event) => setLoginValue(event.target.value)}
            error={getFieldError(errors, 'login') ?? getFieldError(errors, 'email')}
            required
          />
          <PasswordField
            label="Password"
            name="password"
            autoComplete="current-password"
            placeholder="Your password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={getFieldError(errors, 'password')}
            required
          />
          <label className="choice-row">
            <input
              type="checkbox"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
            />
            <span>Keep me signed in on this device</span>
          </label>
          <button
            type="submit"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSubmitting}
            aria-busy={isSubmitting}
          >
            {isSubmitting ? 'Signing in…' : 'Sign in'}
          </button>
        </form>
      </AuthCard>
    </div>
  );
}
