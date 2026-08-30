import { ApiValidationErrors } from '../api/client';

export function getFieldError(errors: ApiValidationErrors, field: string): string | undefined {
  return errors[field]?.[0];
}
