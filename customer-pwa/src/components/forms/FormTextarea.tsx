import { TextareaHTMLAttributes } from 'react';

interface FormTextareaProps extends Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'className'> {
  label: string;
  error?: string;
  hint?: string;
}

export function FormTextarea({ label, error, hint, id, ...textareaProps }: FormTextareaProps) {
  const fieldId = id ?? textareaProps.name;

  return (
    <label className="form-field" htmlFor={fieldId}>
      <span className="form-label">{label}</span>
      <textarea
        {...textareaProps}
        id={fieldId}
        className={`form-control coffee-input coffee-textarea ${error ? 'is-invalid' : ''}`}
      />
      {hint ? <small className="form-hint">{hint}</small> : null}
      {error ? <span className="field-error">{error}</span> : null}
    </label>
  );
}
