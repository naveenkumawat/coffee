/**
 * First-party anonymous visitor identity (no fingerprinting).
 * Opaque random id persisted in localStorage with TTL refresh.
 */

const STORAGE_KEY = 'coffee.visitor-id.v1';
const CREATED_AT_KEY = 'coffee.visitor-id.created-at.v1';

function randomVisitorId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID().replace(/-/g, '');
  }

  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);

  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function ttlDays(): number {
  return 180;
}

export function getOrCreateVisitorId(): string {
  try {
    const existing = window.localStorage.getItem(STORAGE_KEY);
    const createdRaw = window.localStorage.getItem(CREATED_AT_KEY);
    const createdAt = createdRaw ? Number(createdRaw) : NaN;
    const maxAgeMs = ttlDays() * 24 * 60 * 60 * 1000;

    if (
      existing
      && /^[A-Za-z0-9_-]+$/.test(existing)
      && existing.length <= 64
      && Number.isFinite(createdAt)
      && Date.now() - createdAt < maxAgeMs
    ) {
      return existing;
    }

    const next = randomVisitorId();
    window.localStorage.setItem(STORAGE_KEY, next);
    window.localStorage.setItem(CREATED_AT_KEY, String(Date.now()));

    return next;
  } catch {
    return randomVisitorId();
  }
}

export function rotateVisitorId(): string {
  const next = randomVisitorId();

  try {
    window.localStorage.setItem(STORAGE_KEY, next);
    window.localStorage.setItem(CREATED_AT_KEY, String(Date.now()));
  } catch {
    // ignore storage failures
  }

  return next;
}
