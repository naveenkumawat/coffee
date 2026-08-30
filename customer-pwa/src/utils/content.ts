export function digitsOnly(value: string | null | undefined): string {
  return (value ?? '').replace(/\D+/g, '');
}

export function buildWhatsAppUrl(phone: string | null | undefined, message?: string): string | null {
  const digits = digitsOnly(phone);

  if (!digits) {
    return null;
  }

  const url = new URL(`https://wa.me/${digits}`);

  if (message?.trim()) {
    url.searchParams.set('text', message.trim());
  }

  return url.toString();
}

export function resolveMediaUrl(path: string | null | undefined, fallback: string): string {
  const value = path?.trim();

  if (!value) {
    return fallback;
  }

  if (/^https?:\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) {
    return value;
  }

  if (value.startsWith('/')) {
    return value;
  }

  return `/${value}`;
}
