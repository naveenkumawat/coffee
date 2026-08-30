export function normalizeRedirectPath(value: string | null | undefined, fallback = '/account'): string {
  if (!value || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) {
    return fallback;
  }

  return value;
}

export function buildLoginRedirect(pathname: string, search = ''): string {
  const redirectTo = `${pathname}${search}`;

  return `/login?redirect=${encodeURIComponent(redirectTo)}`;
}
