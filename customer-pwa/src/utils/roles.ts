import { Customer } from '../types/auth';

export function isWaiter(user: Customer | null | undefined): boolean {
  return user?.role === 'waiter';
}

export function isWaiterUser(user: Customer | null | undefined): boolean {
  return isWaiter(user);
}

export function isCustomerUser(user: Customer | null | undefined): boolean {
  return user?.role === 'customer';
}

export function homePathForUser(user: Customer | null | undefined): string {
  return isWaiter(user) ? '/waiter' : '/';
}
