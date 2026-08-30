import { Order, OrderStatusTimelineItem, OrderStatusValue } from '../types/order';

export const ORDER_PROGRESS_STEPS: Array<{ status: OrderStatusValue; label: string }> = [
  { status: 'pending_payment', label: 'Pending Payment' },
  { status: 'payment_confirmed', label: 'Payment Confirmed' },
  { status: 'accepted', label: 'Accepted' },
  { status: 'preparing', label: 'Preparing' },
  { status: 'ready_for_pickup', label: 'Ready for Pickup' },
  { status: 'completed', label: 'Completed' }
];

const TERMINAL_FAILURE_STATUSES: OrderStatusValue[] = ['cancelled', 'rejected'];

export function isPendingPayment(status: string | null | undefined): boolean {
  return status === 'pending_payment';
}

export function isTerminalFailure(status: string | null | undefined): boolean {
  return TERMINAL_FAILURE_STATUSES.includes(status as OrderStatusValue);
}

export function progressStepIndex(status: string | null | undefined): number {
  if (!status || isTerminalFailure(status)) {
    return -1;
  }

  return ORDER_PROGRESS_STEPS.findIndex((step) => step.status === status);
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
    rejected: order.rejected_at
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
