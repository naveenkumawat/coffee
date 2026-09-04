import { Order, OrderStatusTimelineItem, OrderStatusValue } from '../types/order';

/** Cafe preparation path after payment (excludes Pending Payment). */
export const ORDER_PREPARATION_STEPS: Array<{ status: OrderStatusValue; label: string }> = [
  { status: 'payment_confirmed', label: 'Payment Confirmed' },
  { status: 'accepted', label: 'Accepted' },
  { status: 'preparing', label: 'Preparing' },
  { status: 'ready_for_pickup', label: 'Ready for Pickup' },
  { status: 'completed', label: 'Completed' },
];

/** @deprecated Prefer ORDER_PREPARATION_STEPS for the customer tracker UI. */
export const ORDER_PROGRESS_STEPS = ORDER_PREPARATION_STEPS;

const TERMINAL_FAILURE_STATUSES: OrderStatusValue[] = ['cancelled', 'rejected'];

const ACTIVE_STATUSES: OrderStatusValue[] = [
  'pending_payment',
  'payment_confirmed',
  'accepted',
  'preparing',
  'ready_for_pickup',
];

export type OrderStatusTone = 'payment' | 'ready' | 'active' | 'done' | 'danger' | 'neutral';

export function isCashPayment(order: Pick<Order, 'payment_method'> | null | undefined): boolean {
  return order?.payment_method === 'cash';
}

export function isDeliveryOrder(order: Pick<Order, 'fulfilment_method'> | null | undefined): boolean {
  return order?.fulfilment_method === 'delivery';
}

export function isDineInOrder(order: Pick<Order, 'fulfilment_method'> | null | undefined): boolean {
  return order?.fulfilment_method === 'dine_in';
}

/** Short chip label for confirmation / order surfaces (CSS uppercases). */
export function fulfilmentChipLabel(
  order: Pick<Order, 'fulfilment_method' | 'fulfilment_method_label'> | null | undefined,
): string {
  if (order?.fulfilment_method === 'delivery') {
    return 'Delivery';
  }

  if (order?.fulfilment_method === 'dine_in') {
    return 'Dine-in';
  }

  if (order?.fulfilment_method === 'takeaway') {
    return 'Takeaway';
  }

  return order?.fulfilment_method_label?.trim() || 'Takeaway';
}

export function preparationStepsForOrder(
  order: Pick<Order, 'fulfilment_method'>,
): Array<{ status: OrderStatusValue; label: string }> {
  return ORDER_PREPARATION_STEPS.map((step) => {
    if (step.status !== 'ready_for_pickup') {
      return step;
    }

    if (isDeliveryOrder(order)) {
      return { ...step, label: 'Ready for Delivery' };
    }

    if (isDineInOrder(order)) {
      return { ...step, label: 'Ready to Serve' };
    }

    return step;
  });
}

export function isPendingPayment(status: string | null | undefined): boolean {
  return status === 'pending_payment';
}

export function isReadyForPickup(status: string | null | undefined): boolean {
  return status === 'ready_for_pickup';
}

export function isTerminalFailure(status: string | null | undefined): boolean {
  return TERMINAL_FAILURE_STATUSES.includes(status as OrderStatusValue);
}

export function isActiveOrder(status: string | null | undefined): boolean {
  return ACTIVE_STATUSES.includes(status as OrderStatusValue);
}

export function statusTone(status: string | null | undefined): OrderStatusTone {
  switch (status) {
    case 'pending_payment':
      return 'payment';
    case 'ready_for_pickup':
      return 'ready';
    case 'payment_confirmed':
    case 'accepted':
    case 'preparing':
      return 'active';
    case 'completed':
      return 'done';
    case 'cancelled':
    case 'rejected':
      return 'danger';
    default:
      return 'neutral';
  }
}

export function progressStepIndex(status: string | null | undefined): number {
  if (!status || isTerminalFailure(status) || isPendingPayment(status)) {
    return -1;
  }

  return ORDER_PREPARATION_STEPS.findIndex((step) => step.status === status);
}

export function timelineTimestampForStatus(order: Order, status: OrderStatusValue): string | null {
  const timestampByStatus: Record<OrderStatusValue, string | null> = {
    pending_payment: order.placed_at,
    payment_confirmed: order.payment_confirmed_at,
    accepted: order.accepted_at,
    preparing: order.preparing_at,
    ready_for_pickup: order.ready_for_pickup_at,
    completed: order.completed_at,
    cancelled: order.cancelled_at,
    rejected: order.rejected_at,
  };

  return timestampByStatus[status] ?? null;
}

export function sortedTimeline(items: OrderStatusTimelineItem[]): OrderStatusTimelineItem[] {
  return [...items].sort((left, right) => {
    const leftTime = left.created_at ? Date.parse(left.created_at) : 0;
    const rightTime = right.created_at ? Date.parse(right.created_at) : 0;

    if (leftTime === rightTime) {
      return left.id - right.id;
    }

    return leftTime - rightTime;
  });
}

export function primaryItemLabel(order: Order): string {
  if (order.items.length === 0) {
    return 'Coffee order';
  }

  const [firstItem] = order.items;

  if (order.items.length === 1) {
    return firstItem.product_name;
  }

  return `${firstItem.product_name} +${order.items.length - 1} more`;
}

export function sortOrdersForDisplay(orders: Order[]): Order[] {
  const priority: Record<string, number> = {
    ready_for_pickup: 0,
    pending_payment: 1,
    preparing: 2,
    accepted: 3,
    payment_confirmed: 4,
    completed: 50,
    cancelled: 60,
    rejected: 61,
  };

  return [...orders].sort((left, right) => {
    const leftRank = priority[left.status ?? ''] ?? 40;
    const rightRank = priority[right.status ?? ''] ?? 40;

    if (leftRank !== rightRank) {
      return leftRank - rightRank;
    }

    const leftTime = left.placed_at ? Date.parse(left.placed_at) : 0;
    const rightTime = right.placed_at ? Date.parse(right.placed_at) : 0;

    return rightTime - leftTime;
  });
}

export function orderListActionLabel(
  order: Pick<Order, 'status' | 'fulfilment_method' | 'payment_method' | 'can_cancel'>,
): string {
  if (isPendingPayment(order.status)) {
    if (isCashPayment(order)) {
      return order.can_cancel ? 'Cancel' : 'Track';
    }

    return order.can_cancel ? 'Pay · Cancel' : 'Pay now';
  }

  if (isReadyForPickup(order.status)) {
    if (isDeliveryOrder(order)) {
      return 'Ready';
    }

    if (isDineInOrder(order)) {
      return 'Serve';
    }

    return 'Pickup';
  }

  if (isActiveOrder(order.status)) {
    return 'Track';
  }

  return 'View';
}
