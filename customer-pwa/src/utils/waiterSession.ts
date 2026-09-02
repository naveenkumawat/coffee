const STORAGE_KEY = 'coffee_waiter_active_session';

export function rememberWaiterSession(sessionId: number | string): void {
  const id = String(sessionId).trim();

  if (!id) {
    return;
  }

  try {
    window.sessionStorage.setItem(STORAGE_KEY, id);
  } catch {
    // Ignore storage failures (private mode / quota).
  }
}

export function getRememberedWaiterSession(): string | null {
  try {
    const value = window.sessionStorage.getItem(STORAGE_KEY)?.trim() ?? '';

    return value.length > 0 ? value : null;
  } catch {
    return null;
  }
}

export function clearRememberedWaiterSession(): void {
  try {
    window.sessionStorage.removeItem(STORAGE_KEY);
  } catch {
    // Ignore storage failures.
  }
}
