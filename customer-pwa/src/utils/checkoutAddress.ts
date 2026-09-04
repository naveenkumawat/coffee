export type CheckoutAddressOption = {
  id: number;
  label: string | null;
  formatted_address: string;
  is_default: boolean;
};

/**
 * Keep selected saved-address id valid after address list refresh.
 * Orphan numeric ids fall back to default / first / new.
 */
export function resolveSelectedAddressId(
  current: number | 'new' | null,
  addresses: CheckoutAddressOption[],
): number | 'new' {
  if (current === 'new') {
    return 'new';
  }

  if (typeof current === 'number' && addresses.some((row) => row.id === current)) {
    return current;
  }

  const defaultAddress = addresses.find((row) => row.is_default) ?? addresses[0] ?? null;

  return defaultAddress ? defaultAddress.id : 'new';
}

export function findSelectedAddress(
  selectedAddressId: number | 'new' | null,
  addresses: CheckoutAddressOption[],
): CheckoutAddressOption | null {
  if (typeof selectedAddressId !== 'number') {
    return null;
  }

  return addresses.find((row) => row.id === selectedAddressId) ?? null;
}

export function maskPhoneForSummary(phone: string): string {
  const digits = phone.replace(/\D/g, '');

  if (digits.length < 4) {
    return phone.trim() || '—';
  }

  return `${digits.slice(0, 2)}${'x'.repeat(Math.max(0, digits.length - 4))}${digits.slice(-2)}`;
}
