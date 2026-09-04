import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

function readSrc(relativePath) {
  return readFileSync(join(root, 'src', relativePath), 'utf8');
}

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

test('orderingContext keeps dining and retail modes separate', async () => {
  const source = readSrc('utils/orderingContext.ts');
  assert.match(source, /type: 'dining'/);
  assert.match(source, /diningSessionId/);
  assert.match(source, /type: 'retail'/);
  assert.match(source, /sessionStorage/);
});

test('dining session UI removes raw item select and qty form', () => {
  const source = readSrc('pages/DiningSessionPage.tsx');
  assert.doesNotMatch(source, /<select/);
  assert.doesNotMatch(source, /Add to draft/);
  assert.doesNotMatch(source, /Place round/);
  assert.match(source, /Table \{session\.table\.label\}/);
  assert.match(source, /Running bill/);
  assert.match(source, /formatCurrency\(billTotal\)/);
  assert.match(source, /Add items/);
  assert.match(source, /Your next round/);
  assert.match(source, /Place order/);
  assert.match(source, /Clear round/);
  assert.match(source, /Your orders/);
  assert.match(source, /Request bill/);
  assert.match(source, /Call waiter/);
  assert.match(source, /diningMenuPath/);
  assert.match(source, /aria-expanded/);
});

test('dining menu reuses ProductCard customization into dining draft', () => {
  const source = readSrc('pages/DiningMenuPage.tsx');
  assert.match(source, /ProductCard/);
  assert.match(source, /orderHandler/);
  assert.match(source, /addDiningDraft/);
  assert.match(source, /Add to round/);
  assert.match(source, /writeOrderingContext/);
  assert.match(source, /View round/);
  assert.doesNotMatch(source, /useCartStore/);
});

test('dining draft API accepts add-ons for customization sheet payload', () => {
  const source = readSrc('api/dining.ts');
  assert.match(source, /add_ons\?: CartAddOnSelection/);
  assert.match(source, /canonicalizeAddOns/);
});

test('router exposes dining-aware menu route', () => {
  const source = readSrc('routes/router.tsx');
  assert.match(source, /dining\/sessions\/:sessionId\/menu/);
  assert.match(source, /DiningMenuPage/);
});
