import { InputHTMLAttributes } from 'react';

interface FormFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'className'> {
  label: string;
  error?: string;
  hint?: string;
}

export function FormField({ label, error, hint, id, ...inputProps }: FormFieldProps) {
  const fieldId = id ?? inputProps.name;

  return (
    <label className="form-field" htmlFor={fieldId}>
      <span className="form-label">{label}</span>
      <input
        {...inputProps}
        id={fieldId}
        className={`form-control form-control-lg coffee-input ${error ? 'is-invalid' : ''}`}
      />
      {hint ? <small className="form-hint">{hint}</small> : null}
      {error ? <span className="field-error">{error}</span> : null}
    </label>
  );
}
