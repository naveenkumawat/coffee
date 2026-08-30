import { InputHTMLAttributes, useState } from 'react';

interface PasswordFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'type'> {
  label: string;
  error?: string;
  hint?: string;
}

export function PasswordField({ label, error, hint, id, ...inputProps }: PasswordFieldProps) {
  const [isVisible, setIsVisible] = useState(false);
  const fieldId = id ?? inputProps.name;

  return (
    <label className="form-field" htmlFor={fieldId}>
      <span className="form-label">{label}</span>
      <span className={`password-field ${error ? 'has-error' : ''}`}>
        <input
          {...inputProps}
          id={fieldId}
          type={isVisible ? 'text' : 'password'}
          className={`form-control form-control-lg coffee-input ${error ? 'is-invalid' : ''}`}
        />
        <button
          type="button"
          className="password-toggle"
          onClick={() => setIsVisible((currentValue) => !currentValue)}
          aria-label={isVisible ? 'Hide password' : 'Show password'}
        >
          <i className={`bi ${isVisible ? 'bi-eye-slash' : 'bi-eye'}`}></i>
        </button>
      </span>
      {hint ? <small className="form-hint">{hint}</small> : null}
      {error ? <span className="field-error">{error}</span> : null}
    </label>
  );
}
