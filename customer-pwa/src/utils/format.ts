export function formatCurrency(value: string | number | null | undefined): string {
  const numericValue = Number(value ?? 0);

  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 2,
    minimumFractionDigits: 2
  }).format(Number.isNaN(numericValue) ? 0 : numericValue);
}

export function joinLabels(values: Array<string | null | undefined>): string {
  return values.filter(Boolean).join(' • ');
}
