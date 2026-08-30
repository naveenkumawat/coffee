import { TextareaHTMLAttributes } from 'react';

interface FormTextareaProps extends Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'className'> {
  label: string;
  error?: string;
  hint?: string;
}

export function FormTextarea({ label, error, hint, id, ...textareaProps }: FormTextareaProps) {
  const fieldId = id ?? textareaProps.name;
  const errorId = fieldId ? `${fieldId}-error` : undefined;
  const hintId = fieldId ? `${fieldId}-hint` : undefined;
  const describedBy = [error && errorId ? errorId : null, hint && hintId ? hintId : null]
    .filter(Boolean)
    .join(' ') || undefined;

  return (
    <label className="form-field" htmlFor={fieldId}>
      <span className="form-label">{label}</span>
      <textarea
        {...textareaProps}
        id={fieldId}
        className={`form-control coffee-input coffee-textarea ${error ? 'is-invalid' : ''}`}
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
