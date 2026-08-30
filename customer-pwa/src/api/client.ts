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

  public constructor(message: string, status: number, errors: ApiValidationErrors = {}, payload: unknown = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.payload = payload;
  }
}

type UnauthorizedHandler = () => void;

let unauthorizedHandler: UnauthorizedHandler | null = null;

export function getApiBaseUrl(): string {
  const value = import.meta.env.VITE_API_BASE_URL?.trim();

  return value && value.length > 0 ? value.replace(/\/$/, '') : '/api/v1';
}

export function getBackendBaseUrl(): string {
  const baseUrl = new URL(getApiBaseUrl(), window.location.origin);
  const normalizedPath = baseUrl.pathname.replace(/\/api\/v1\/?$/, '/');

  baseUrl.pathname = normalizedPath;
  baseUrl.search = '';
  baseUrl.hash = '';

  return baseUrl.toString();
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

    throw new ApiError(message, response.status, errors, payload);
  }

  return payload as TResponse;
}

export function get<TResponse>(path: string): Promise<TResponse> {
  return request<TResponse>(path, { method: 'GET' });
}

export function post<TResponse, TPayload>(path: string, body?: TPayload): Promise<TResponse> {
  return request<TResponse>(path, {
    method: 'POST',
    body: body === undefined ? undefined : JSON.stringify(body)
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
