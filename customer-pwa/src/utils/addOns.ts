import { CartAddOnSelection, CartItemAddOn } from '../types/cart';
import { ProductAddOn } from '../types/catalog';

export function canonicalizeAddOns(
  addOns: Array<CartAddOnSelection | CartItemAddOn> | null | undefined,
): CartAddOnSelection[] {
  const normalized = new Map<number, number>();

  for (const entry of addOns ?? []) {
    const id = Number('add_on_id' in entry ? entry.add_on_id : 0);
    const quantity = Number(entry.quantity ?? 0);

    if (!Number.isFinite(id) || id <= 0 || !Number.isFinite(quantity) || quantity <= 0) {
      continue;
    }

    normalized.set(id, (normalized.get(id) ?? 0) + Math.floor(quantity));
  }

  return [...normalized.entries()]
    .sort(([left], [right]) => left - right)
    .map(([add_on_id, quantity]) => ({ add_on_id, quantity }));
}

export function addOnsConfigurationKey(
  productVariantId: number,
  addOns: Array<CartAddOnSelection | CartItemAddOn> | null | undefined,
): string {
  const canonical = canonicalizeAddOns(addOns);

  return `${productVariantId}|${canonical.map((row) => `${row.add_on_id}:${row.quantity}`).join(',')}`;
}

export function addOnsSelectionsEqual(
  left: Array<CartAddOnSelection | CartItemAddOn> | null | undefined,
  right: Array<CartAddOnSelection | CartItemAddOn> | null | undefined,
): boolean {
  const a = canonicalizeAddOns(left);
  const b = canonicalizeAddOns(right);

  if (a.length !== b.length) {
    return false;
  }

  return a.every((row, index) => row.add_on_id === b[index].add_on_id && row.quantity === b[index].quantity);
}

export function buildCartAddOnDisplay(
  catalog: ProductAddOn[],
  selected: CartAddOnSelection[],
): CartItemAddOn[] {
  const byId = new Map(catalog.map((addOn) => [addOn.id, addOn]));

  return canonicalizeAddOns(selected).flatMap((row) => {
    const catalogAddOn = byId.get(row.add_on_id);

    if (!catalogAddOn) {
      return [];
    }

    const unitPrice = Number(catalogAddOn.price);
    const lineTotal = unitPrice * row.quantity;

    return [
      {
        add_on_id: row.add_on_id,
        name: catalogAddOn.name,
        quantity: row.quantity,
        unit_price: unitPrice.toFixed(2),
        line_total: lineTotal.toFixed(2),
      },
    ];
  });
}

export function addonUnitTotal(addOns: CartItemAddOn[] | null | undefined): number {
  return (addOns ?? []).reduce((carry, addOn) => carry + Number(addOn.unit_price) * addOn.quantity, 0);
}

export function formatAddOnLabel(addOn: { name: string | null; quantity: number }): string {
  const name = addOn.name?.trim() || 'Add-on';

  return addOn.quantity > 1 ? `+ ${name} × ${addOn.quantity}` : `+ ${name}`;
}
