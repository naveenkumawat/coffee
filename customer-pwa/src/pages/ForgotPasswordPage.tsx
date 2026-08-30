import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotCustomerPassword } from '../api/auth';
import { ApiError, ApiValidationErrors } from '../api/client';
import { PageHeader } from '../components/common/PageHeader';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { getFieldError } from '../utils/forms';

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSubmitting(true);
    setErrors({});
    setSuccessMessage(null);
    setErrorMessage(null);

    try {
      const response = await forgotCustomerPassword({ email });
      setSuccessMessage(response.message ?? 'Password reset instructions have been sent to your email address.');
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setErrorMessage(error.message);
      } else {
        setErrorMessage('Unable to start the reset flow right now.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="page-container">
      <PageHeader title="Forgot password" description="We’ll send reset instructions to your customer email address." showBack />
      <section className="auth-card">
        <div className="auth-card-copy">
          <span className="auth-badge">Password help</span>
          <h2>Reset your password</h2>
          <p>Enter your email and we’ll hand the next step back to the secure reset flow.</p>
        </div>

        <FormFeedback message={successMessage} />
        <FormFeedback message={errorMessage} variant="error" />

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
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSubmitting}>
            {isSubmitting ? 'Sending link...' : 'Send reset instructions'}
          </button>
        </form>

        <div className="auth-links">
          <Link to="/login">Back to login</Link>
        </div>
      </section>
    </div>
  );
}
