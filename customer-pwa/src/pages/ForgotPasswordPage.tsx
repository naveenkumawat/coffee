import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { forgotCustomerPassword } from '../api/auth';
import { ApiError, ApiValidationErrors } from '../api/client';
import { AuthCard } from '../components/auth/AuthCard';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { useToastStore } from '../stores/toastStore';
import { getFieldError } from '../utils/forms';

export function ForgotPasswordPage() {
  const navigate = useNavigate();
  const toastSuccess = useToastStore((state) => state.success);
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
      const message =
        response.message ?? 'If an account exists for that email, reset instructions are on the way.';
      setSuccessMessage(message);
      toastSuccess('Check your email');
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors);
        setErrorMessage(error.message);
      } else {
        setErrorMessage('Unable to send reset instructions right now. Please try again.');
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
        badge="Password help"
        title="Reset your password"
        description="Enter the email on your account and we’ll send the next step."
        footer={<Link to="/login">Back to sign in</Link>}
      >
        <FormFeedback message={successMessage} />
        <FormFeedback message={errorMessage} variant="error" />

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <FormField
            label="Email"
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
          <button
            type="submit"
            className="btn btn-primary btn-lg rounded-pill w-100"
            disabled={isSubmitting}
            aria-busy={isSubmitting}
          >
            {isSubmitting ? 'Sending…' : 'Send reset link'}
          </button>
        </form>
      </AuthCard>
    </div>
  );
}
