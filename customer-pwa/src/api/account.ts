import { Customer, UpdatePasswordPayload, UpdateProfilePayload } from '../types/auth';
import { ApiEnvelope, get, put } from './client';
import { ensureCsrfCookie } from './auth';

export function fetchCustomerProfile(): Promise<ApiEnvelope<Customer>> {
  return get<ApiEnvelope<Customer>>('/customer/me');
}

export async function updateCustomerProfile(payload: UpdateProfilePayload): Promise<ApiEnvelope<Customer>> {
  await ensureCsrfCookie();

  return put<ApiEnvelope<Customer>, UpdateProfilePayload>('/customer/profile', payload);
}

export async function updateCustomerPassword(payload: UpdatePasswordPayload): Promise<ApiEnvelope<Customer>> {
  await ensureCsrfCookie();

  return put<ApiEnvelope<Customer>, UpdatePasswordPayload>('/customer/password', payload);
}
