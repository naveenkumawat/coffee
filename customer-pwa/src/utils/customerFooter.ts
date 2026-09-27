export type CustomerFooterSlot = 'home' | 'menu' | 'dining' | 'cart' | 'account';

/** Retail footer slots. An active Dining session is expressed as showDiningNav. */
export function customerFooterSlots(input: {
  cartEnabled: boolean;
  showDiningNav: boolean;
}): CustomerFooterSlot[] {
  const slots: CustomerFooterSlot[] = ['home', 'menu'];

  if (input.showDiningNav) {
    slots.push('dining');
  }

  if (input.cartEnabled) {
    slots.push('cart');
  }

  slots.push('account');

  return slots;
}
