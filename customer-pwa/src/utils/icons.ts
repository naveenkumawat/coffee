/** Canonical Bootstrap Icons classes for customer PWA concepts. */
export const AppIcons = {
  home: 'bi-house-door',
  menu: 'bi-grid',
  cart: 'bi-bag',
  account: 'bi-person',
  orders: 'bi-receipt',
  addresses: 'bi-geo-alt',
  favourite: 'bi-heart',
  rewards: 'bi-gift',
  referral: 'bi-people',
  notification: 'bi-bell',
  check: 'bi-check-lg',
  dining: 'bi-cup-hot',
  chevronRight: 'bi-chevron-right',
  chevronDown: 'bi-chevron-down',
  edit: 'bi-pencil',
  password: 'bi-lock',
} as const;

export type AppIconName = keyof typeof AppIcons;

export function appIconClass(name: AppIconName, filled = false): string {
  const base = AppIcons[name];

  if (!filled) {
    return base;
  }

  // Prefer fill variants when Bootstrap Icons provides them.
  const fillMap: Partial<Record<AppIconName, string>> = {
    home: 'bi-house-door-fill',
    cart: 'bi-bag-fill',
    account: 'bi-person-fill',
    favourite: 'bi-heart-fill',
    notification: 'bi-bell-fill',
    rewards: 'bi-gift-fill',
  };

  return fillMap[name] ?? base;
}

export function formatCountBadge(count: number): string | null {
  if (!Number.isFinite(count) || count <= 0) {
    return null;
  }

  return count > 99 ? '99+' : String(Math.floor(count));
}
