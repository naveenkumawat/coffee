interface FormFeedbackProps {
  message: string | null;
  variant?: 'success' | 'error';
}

export function FormFeedback({ message, variant = 'success' }: FormFeedbackProps) {
  if (!message) {
    return null;
  }

  return (
    <div
      className={`form-feedback form-feedback-${variant}`}
      role={variant === 'error' ? 'alert' : 'status'}
      aria-live={variant === 'error' ? 'assertive' : 'polite'}
    >
      {message}
    </div>
  );
}
