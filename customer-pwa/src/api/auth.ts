import { Customer, ForgotPasswordPayload, LoginPayload, RegisterPayload, ResetPasswordPayload } from '../types/auth';
import { ApiEnvelope, get, getBackendBaseUrl, post } from './client';

export function fetchCurrentCustomer(): Promise<ApiEnvelope<Customer>> {
  return get<ApiEnvelope<Customer>>('/auth/me');
}

export async function ensureCsrfCookie(): Promise<void> {
  await fetch(new URL('sanctum/csrf-cookie', getBackendBaseUrl()), {
    method: 'GET',
    credentials: 'include'
  });
}

export async function loginCustomer(payload: LoginPayload): Promise<ApiEnvelope<Customer>> {
  await ensureCsrfCookie();

  return post<ApiEnvelope<Customer>, LoginPayload>('/auth/login', payload);
}

export async function registerCustomer(payload: RegisterPayload): Promise<ApiEnvelope<Customer>> {
  await ensureCsrfCookie();

  return post<ApiEnvelope<Customer>, RegisterPayload>('/auth/register', payload);
}

export async function logoutCustomer(): Promise<ApiEnvelope<null>> {
  await ensureCsrfCookie();

  return post<ApiEnvelope<null>, undefined>('/auth/logout');
}

export async function forgotCustomerPassword(payload: ForgotPasswordPayload): Promise<ApiEnvelope<null>> {
  await ensureCsrfCookie();

  return post<ApiEnvelope<null>, ForgotPasswordPayload>('/auth/forgot-password', payload);
}

export async function resetCustomerPassword(payload: ResetPasswordPayload): Promise<ApiEnvelope<Customer>> {
  await ensureCsrfCookie();

  return post<ApiEnvelope<Customer>, ResetPasswordPayload>('/auth/reset-password', payload);
}
