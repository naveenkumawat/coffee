import { InputHTMLAttributes } from 'react';

interface FormFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className'> {
  label: string;
  error?: string;
  hint?: string;
}

export function FormField({ label, error, hint, id, ...inputProps }: FormFieldProps) {
  const fieldId = id ?? inputProps.name;
  const errorId = fieldId ? `${fieldId}-error` : undefined;
  const hintId = fieldId ? `${fieldId}-hint` : undefined;
  const describedBy = [error && errorId ? errorId : null, hint && hintId ? hintId : null]
    .filter(Boolean)
    .join(' ') || undefined;

  return (
    <label className="form-field" htmlFor={fieldId}>
      <span className="form-label">{label}</span>
      <input
        {...inputProps}
        id={fieldId}
        className={`form-control form-control-lg coffee-input ${error ? 'is-invalid' : ''}`}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
      />
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
