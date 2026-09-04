import assert from 'node:assert/strict';
import test from 'node:test';

/** Mirrors customer-pwa/src/utils/icons.ts formatCountBadge */
function formatCountBadge(count) {
  if (!Number.isFinite(count) || count <= 0) {
    return null;
  }

  return count > 99 ? '99+' : String(Math.floor(count));
}

/** Mirrors customer-pwa/src/utils/checkoutAddress.ts resolveSelectedAddressId */
function resolveSelectedAddressId(current, addresses) {
  if (current === 'new') {
    return 'new';
  }

  if (typeof current === 'number' && addresses.some((row) => row.id === current)) {
    return current;
  }

  const defaultAddress = addresses.find((row) => row.is_default) ?? addresses[0] ?? null;

  return defaultAddress ? defaultAddress.id : 'new';
}

function resolveNotificationOpenPath(actionUrl, fallbackSubjectPath, origin = 'https://app.test') {
  if (actionUrl) {
    if (
      actionUrl.startsWith('/orders') ||
      actionUrl.startsWith('/dining') ||
      actionUrl.startsWith('/account') ||
      actionUrl.startsWith('/waiter')
    ) {
      return actionUrl;
    }

    try {
      const parsed = new URL(actionUrl, origin);
      if (parsed.origin === origin) {
        if (
          parsed.pathname.startsWith('/orders') ||
          parsed.pathname.startsWith('/dining') ||
          parsed.pathname.startsWith('/account') ||
          parsed.pathname.startsWith('/waiter')
        ) {
          return `${parsed.pathname}${parsed.search}`;
        }
      }
    } catch {
      // fall through
    }
  }

  return fallbackSubjectPath;
}

test('formatCountBadge hides zero and caps at 99+', () => {
  assert.equal(formatCountBadge(0), null);
  assert.equal(formatCountBadge(-1), null);
  assert.equal(formatCountBadge(3), '3');
  assert.equal(formatCountBadge(99), '99');
  assert.equal(formatCountBadge(100), '99+');
});

test('resolveSelectedAddressId repairs orphan ids', () => {
  const addresses = [
    { id: 2, label: 'Work', is_default: false },
    { id: 5, label: 'Home', is_default: true },
  ];

  assert.equal(resolveSelectedAddressId(null, addresses), 5);
  assert.equal(resolveSelectedAddressId(99, addresses), 5);
  assert.equal(resolveSelectedAddressId(2, addresses), 2);
  assert.equal(resolveSelectedAddressId('new', addresses), 'new');
  assert.equal(resolveSelectedAddressId(1, []), 'new');
});

test('resolveNotificationOpenPath only allows safe destinations', () => {
  assert.equal(resolveNotificationOpenPath('/orders/12', null), '/orders/12');
  assert.equal(resolveNotificationOpenPath('/account/loyalty', null), '/account/loyalty');
  assert.equal(resolveNotificationOpenPath('https://evil.example/orders/1', '/orders/9'), '/orders/9');
  assert.equal(resolveNotificationOpenPath(null, null), null);
});
