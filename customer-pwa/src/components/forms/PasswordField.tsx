import { InputHTMLAttributes, useState } from 'react';

interface PasswordFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'type'> {
  label: string;
  error?: string;
  hint?: string;
}

export function PasswordField({ label, error, hint, id, ...inputProps }: PasswordFieldProps) {
  const [isVisible, setIsVisible] = useState(false);
  const fieldId = id ?? inputProps.name;
  const errorId = fieldId ? `${fieldId}-error` : undefined;
  const hintId = fieldId ? `${fieldId}-hint` : undefined;
  const describedBy = [error && errorId ? errorId : null, hint && hintId ? hintId : null]
    .filter(Boolean)
    .join(' ') || undefined;

  return (
    <label className="form-field" htmlFor={fieldId}>
      <span className="form-label">{label}</span>
      <span className={`password-field ${error ? 'has-error' : ''}`}>
        <input
          {...inputProps}
          id={fieldId}
          type={isVisible ? 'text' : 'password'}
          className={`form-control form-control-lg coffee-input ${error ? 'is-invalid' : ''}`}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
        />
        <button
          type="button"
          className="password-toggle"
          onClick={() => setIsVisible((currentValue) => !currentValue)}
          aria-label={isVisible ? 'Hide password' : 'Show password'}
        >
          <i className={`bi ${isVisible ? 'bi-eye-slash' : 'bi-eye'}`} aria-hidden="true"></i>
        </button>
      </span>
      {hint ? (
        <small id={hintId} className="form-hint">
          {hint}
        </small>
      ) : null}
      {error ? (
        <span id={errorId} className="field-error" role="alert">
          {error}
        </span>
      ) : null}
    </label>
  );
}
