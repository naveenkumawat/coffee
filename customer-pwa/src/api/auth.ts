import { Customer, ForgotPasswordPayload, LoginPayload, RegisterPayload, ResetPasswordPayload } from '../types/auth';
import {
  ApiEnvelope,
  ApiError,
  clearCsrfTokenFromHeader,
  get,
  getBackendBaseUrl,
  post,
  readXsrfToken,
  setCsrfTokenFromHeader,
} from './client';

export function fetchCurrentCustomer(): Promise<ApiEnvelope<Customer>> {
  return get<ApiEnvelope<Customer>>('/auth/me');
}

/**
 * Hit Laravel's SPA CSRF endpoint (NOT under /api/v1).
 * Derived from VITE_API_BASE_URL → {origin}{appBase}/sanctum/csrf-cookie
 *
 * Prefer the readable XSRF-TOKEN cookie; if the browser won't expose it to JS
 * (common when the PWA port differs from Apache), use the X-CSRF-TOKEN header.
 */
export async function ensureCsrfCookie(): Promise<void> {
  const csrfUrl = new URL('sanctum/csrf-cookie', getBackendBaseUrl()).toString();

  let response: Response;

  try {
    response = await fetch(csrfUrl, {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
  } catch {
    throw new ApiError(
      'Unable to reach the sign-in service. Check that the API host matches this page’s host (do not mix localhost and a LAN IP).',
      0,
    );
  }

  if (!response.ok) {
    throw new ApiError('Unable to start a secure sign-in session. Please refresh and try again.', response.status);
  }

  const headerToken = response.headers.get('X-CSRF-TOKEN');
  const cookieToken = readXsrfToken();

  if (cookieToken) {
    clearCsrfTokenFromHeader();

    return;
  }

  if (headerToken) {
    setCsrfTokenFromHeader(headerToken);

    return;
  }

  throw new ApiError(
    'Sign-in cookies were blocked. Use the same host for the app and API, allow cookies for this site, then retry.',
    419,
  );
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

  const result = await post<ApiEnvelope<null>, undefined>('/auth/logout');
  clearCsrfTokenFromHeader();

  return result;
}

export async function forgotCustomerPassword(payload: ForgotPasswordPayload): Promise<ApiEnvelope<null>> {
  await ensureCsrfCookie();

  return post<ApiEnvelope<null>, ForgotPasswordPayload>('/auth/forgot-password', payload);
}

export async function resetCustomerPassword(payload: ResetPasswordPayload): Promise<ApiEnvelope<Customer>> {
  await ensureCsrfCookie();

  return post<ApiEnvelope<Customer>, ResetPasswordPayload>('/auth/reset-password', payload);
}
