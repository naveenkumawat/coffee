export interface ApiEnvelope<T> {
  message?: string | null;
  data: T;
  meta?: Record<string, unknown>;
}

export interface ApiValidationErrors {
  [key: string]: string[];
}

export class ApiError extends Error {
  public readonly status: number;
  public readonly errors: ApiValidationErrors;
  public readonly payload: unknown;
  public readonly code: string | null;

  public constructor(
    message: string,
    status: number,
    errors: ApiValidationErrors = {},
    payload: unknown = null,
    code: string | null = null,
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.payload = payload;
    this.code = code;
  }
}

type UnauthorizedHandler = () => void;

let unauthorizedHandler: UnauthorizedHandler | null = null;

/** Plain CSRF token from Sanctum response header when XSRF cookie is not JS-readable (cross-port SPA). */
let csrfTokenFromHeader: string | null = null;

function isLocalOrPrivateHostname(hostname: string): boolean {
  if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]' || hostname === '::1') {
    return true;
  }

  if (/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(hostname)) {
    return true;
  }

  if (/^192\.168\.\d{1,3}\.\d{1,3}$/.test(hostname)) {
    return true;
  }

  if (/^172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}$/.test(hostname)) {
    return true;
  }

  return false;
}

/**
 * Absolute API base ending in /api/v1.
 * On local/LAN, rewrite the hostname to match the address bar so opening
 * localhost:4173 or 192.168.x.x:4173 both hit Apache on that same host
 * (never copies the PWA port onto the API URL). Production cross-host APIs are left alone.
 */
export function getApiBaseUrl(): string {
  const value = import.meta.env.VITE_API_BASE_URL?.trim();

  if (import.meta.env.PROD && (!value || value.length === 0)) {
    throw new Error('VITE_API_BASE_URL is required for production builds.');
  }

  const configured = value && value.length > 0 ? value.replace(/\/$/, '') : '/api/v1';

  let url: URL;

  try {
    url = new URL(configured, window.location.origin);
  } catch {
    return configured;
  }

  const pageHost = window.location.hostname;

  if (
    url.hostname !== pageHost
    && (isLocalOrPrivateHostname(url.hostname) || isLocalOrPrivateHostname(pageHost))
  ) {
    url.hostname = pageHost;
    url.port = '';
    url.protocol = window.location.protocol;
  }

  return `${url.origin}${url.pathname}`.replace(/\/$/, '');
}

/**
 * Laravel app root (scheme + host + optional subdirectory), derived from VITE_API_BASE_URL.
 * Example: http://192.168.0.10/coffee/api/v1 → http://192.168.0.10/coffee/
 * Used for /sanctum/csrf-cookie (never /api/v1/sanctum/...).
 */
export function getBackendBaseUrl(): string {
  const baseUrl = new URL(getApiBaseUrl(), window.location.origin);
  const normalizedPath = baseUrl.pathname.replace(/\/api\/v1\/?$/, '/');

  baseUrl.pathname = normalizedPath.endsWith('/') ? normalizedPath : `${normalizedPath}/`;
  baseUrl.search = '';
  baseUrl.hash = '';

  return baseUrl.toString();
}

export function readXsrfToken(): string | null {
  return readCookie('XSRF-TOKEN');
}

export function setCsrfTokenFromHeader(token: string | null): void {
  csrfTokenFromHeader = token;
}

export function clearCsrfTokenFromHeader(): void {
  csrfTokenFromHeader = null;
}

function readCookie(name: string): string | null {
  const encodedName = `${encodeURIComponent(name)}=`;
  const parts = document.cookie.split(';');

  for (const part of parts) {
    const cookie = part.trim();

    if (cookie.startsWith(encodedName)) {
      return decodeURIComponent(cookie.slice(encodedName.length));
    }
  }

  return null;
}

function toUrl(path: string): string {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;

  return `${getApiBaseUrl()}${normalizedPath}`;
}

async function parsePayload(response: Response): Promise<unknown> {
  const contentType = response.headers.get('content-type') ?? '';

  if (contentType.includes('application/json')) {
    return response.json();
  }

  return response.text();
}

export function setUnauthorizedHandler(handler: UnauthorizedHandler): void {
  unauthorizedHandler = handler;
}

export async function request<TResponse>(path: string, init: RequestInit = {}): Promise<TResponse> {
  const headers = new Headers(init.headers);

  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json');
  }

  const isBodyJson = init.body !== undefined && !(init.body instanceof FormData);

  if (isBodyJson && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const xsrfToken = readXsrfToken();

  if (xsrfToken && !headers.has('X-XSRF-TOKEN') && !headers.has('X-CSRF-TOKEN')) {
    headers.set('X-XSRF-TOKEN', xsrfToken);
  } else if (csrfTokenFromHeader && !headers.has('X-XSRF-TOKEN') && !headers.has('X-CSRF-TOKEN')) {
    headers.set('X-CSRF-TOKEN', csrfTokenFromHeader);
  }

  if (!headers.has('X-Requested-With')) {
    headers.set('X-Requested-With', 'XMLHttpRequest');
  }

  const response = await fetch(toUrl(path), {
    credentials: 'include',
    ...init,
    headers
  });

  const payload = await parsePayload(response);

  if (!response.ok) {
    const normalizedPayload = typeof payload === 'object' && payload !== null ? payload as Record<string, unknown> : {};
    const message = typeof normalizedPayload.message === 'string' ? normalizedPayload.message : 'Something went wrong.';
    const errors = typeof normalizedPayload.errors === 'object' && normalizedPayload.errors !== null
      ? normalizedPayload.errors as ApiValidationErrors
      : {};

    if (response.status === 401 && unauthorizedHandler) {
      unauthorizedHandler();
    }

    const code = typeof normalizedPayload.code === 'string' ? normalizedPayload.code : null;

    throw new ApiError(message, response.status, errors, payload, code);
  }

  return payload as TResponse;
}

export function get<TResponse>(path: string): Promise<TResponse> {
  return request<TResponse>(path, { method: 'GET' });
}

export interface ConditionalGetResult<TResponse> {
  status: number;
  notModified: boolean;
  etag: string | null;
  lastModified: string | null;
  data: TResponse | null;
}

/**
 * GET with optional If-None-Match. Returns 304 without parsing a body.
 * Used for public catalogue revalidation — not for authenticated private data.
 */
export async function getConditional<TResponse>(
  path: string,
  etag: string | null = null,
): Promise<ConditionalGetResult<TResponse>> {
  const headers = new Headers({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  });

  if (etag) {
    headers.set('If-None-Match', etag);
  }

  const xsrfToken = readXsrfToken();

  if (xsrfToken) {
    headers.set('X-XSRF-TOKEN', xsrfToken);
  } else if (csrfTokenFromHeader) {
    headers.set('X-CSRF-TOKEN', csrfTokenFromHeader);
  }

  const response = await fetch(toUrl(path), {
    method: 'GET',
    credentials: 'include',
    headers,
  });

  const responseEtag = response.headers.get('etag');
  const lastModified = response.headers.get('last-modified');

  if (response.status === 304) {
    return {
      status: 304,
      notModified: true,
      etag: responseEtag ?? etag,
      lastModified,
      data: null,
    };
  }

  const payload = await parsePayload(response);

  if (!response.ok) {
    const normalizedPayload = typeof payload === 'object' && payload !== null ? (payload as Record<string, unknown>) : {};
    const message = typeof normalizedPayload.message === 'string' ? normalizedPayload.message : 'Something went wrong.';
    const errors =
      typeof normalizedPayload.errors === 'object' && normalizedPayload.errors !== null
        ? (normalizedPayload.errors as ApiValidationErrors)
        : {};

    if (response.status === 401 && unauthorizedHandler) {
      unauthorizedHandler();
    }

    const code = typeof normalizedPayload.code === 'string' ? normalizedPayload.code : null;

    throw new ApiError(message, response.status, errors, payload, code);
  }

  return {
    status: response.status,
    notModified: false,
    etag: responseEtag,
    lastModified,
    data: payload as TResponse,
  };
}

export function post<TResponse, TPayload>(path: string, body?: TPayload): Promise<TResponse> {
  return request<TResponse>(path, {
    method: 'POST',
    body: body === undefined ? undefined : JSON.stringify(body)
  });
}

export function postForm<TResponse>(path: string, body: FormData): Promise<TResponse> {
  return request<TResponse>(path, {
    method: 'POST',
    body
  });
}

export function put<TResponse, TPayload>(path: string, body: TPayload): Promise<TResponse> {
  return request<TResponse>(path, {
    method: 'PUT',
    body: JSON.stringify(body)
  });
}

export function destroy<TResponse>(path: string): Promise<TResponse> {
  return request<TResponse>(path, { method: 'DELETE' });
}

/**
 * Authenticated binary download (PDF, etc.). Triggers a browser file save.
 */
export async function downloadFile(path: string, fallbackFilename: string): Promise<void> {
  const headers = new Headers({
    Accept: 'application/pdf,application/octet-stream',
    'X-Requested-With': 'XMLHttpRequest',
  });

  const xsrfToken = readXsrfToken();

  if (xsrfToken) {
    headers.set('X-XSRF-TOKEN', xsrfToken);
  } else if (csrfTokenFromHeader) {
    headers.set('X-CSRF-TOKEN', csrfTokenFromHeader);
  }

  const response = await fetch(toUrl(path), {
    method: 'GET',
    credentials: 'include',
    headers,
  });

  if (!response.ok) {
    const payload = await parsePayload(response);
    const normalizedPayload = typeof payload === 'object' && payload !== null ? (payload as Record<string, unknown>) : {};
    const message = typeof normalizedPayload.message === 'string' ? normalizedPayload.message : 'Unable to download file.';

    if (response.status === 401 && unauthorizedHandler) {
      unauthorizedHandler();
    }

    const code = typeof normalizedPayload.code === 'string' ? normalizedPayload.code : null;

    throw new ApiError(message, response.status, {}, payload, code);
  }

  const disposition = response.headers.get('content-disposition') ?? '';
  const matched = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(disposition);
  const filename = matched ? decodeURIComponent(matched[1].replace(/"/g, '')) : fallbackFilename;

  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}
