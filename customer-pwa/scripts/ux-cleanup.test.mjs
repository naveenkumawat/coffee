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
  assert.match(source, /Back to table/);
  assert.match(source, /No items in next round/);
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

test('customer shell uses shared content max tokens', () => {
  const theme = readFileSync(join(root, 'src/assets/styles/theme.css'), 'utf8');
  assert.match(theme, /--customer-content-max:\s*72rem/);
  assert.match(theme, /--customer-content-max-mobile:\s*34rem/);
  assert.match(theme, /--customer-content-gutter:/);
  assert.match(theme, /--dining-content-max:\s*48rem/);
  assert.match(theme, /\.app-shell\s*\{[\s\S]*?var\(--customer-content-max\)/);
  assert.match(theme, /\.dining-content\s*\{[\s\S]*?var\(--dining-content-max\)/);
  assert.match(theme, /\.dining-session-page,[\s\S]*?max-width:\s*none/);
  assert.doesNotMatch(
    theme,
    /\.dining-session-page,[\s\S]{0,200}max-width:\s*min\(100%,\s*var\(--coffee-shell-max\)\)/,
  );
});

test('dining session and bill use inner dining-content inside shared page shell', () => {
  const source = readSrc('pages/DiningSessionPage.tsx');
  assert.match(source, /page-container dining-session-page/);
  assert.match(source, /className="dining-content"/);
  assert.match(source, /page-container dining-bill-page/);
  assert.match(source, /tableLabel:/);
});

test('dining bottom nav uses orderingContext and hides booking/cart', () => {
  const source = readSrc('components/navigation/BottomNavigation.tsx');
  assert.match(source, /useOrderingContext/);
  assert.match(source, /isDiningOrderingContext/);
  assert.match(source, /diningMenuPath/);
  assert.match(source, /diningSessionPath/);
  assert.match(source, /diningActive/);
  assert.doesNotMatch(source, /pathname\.(includes|startsWith)\(/);
  assert.doesNotMatch(source, /Book table/i);

  const diningBranch = source.match(/diningActive\s*\?\s*(\[[\s\S]*?\])\s*:/);
  assert.ok(diningBranch, 'expected diningActive ternary branch');
  assert.match(diningBranch[1], /diningMenuPath/);
  assert.match(diningBranch[1], /diningSessionPath/);
  assert.match(diningBranch[1], /label: 'Menu'/);
  assert.doesNotMatch(diningBranch[1], /to:\s*'\/cart'/);
  assert.doesNotMatch(diningBranch[1], /label:\s*'Cart'/);
  assert.doesNotMatch(diningBranch[1], /label:\s*'Dining'/);
  assert.doesNotMatch(diningBranch[1], /to:\s*[^,\n]*\/dining['"`]/);

  // Retail booking + cart remain available outside dining context.
  assert.match(source, /label: 'Dining'/);
  assert.match(source, /to: '\/cart'/);
});

test('dining add-items sticky bar has table return actions without retail cart', () => {
  const source = readSrc('pages/DiningMenuPage.tsx');
  assert.match(source, /No items in next round/);
  assert.match(source, /Back to table/);
  assert.match(source, /View round/);
  assert.match(source, /tableLabel:/);
  assert.doesNotMatch(source, /Book table/i);
  assert.doesNotMatch(source, /useCartStore/);
  assert.doesNotMatch(source, /to="\/cart"/);
});

test('retail menu and cart redirect into dining when dining context is active', () => {
  const menu = readSrc('pages/MenuPage.tsx');
  assert.match(menu, /isDiningOrderingContext/);
  assert.match(menu, /diningMenuPath/);
  assert.match(menu, /Navigate to=\{diningMenuPath/);

  const cart = readSrc('pages/CartPage.tsx');
  assert.match(cart, /isDiningOrderingContext/);
  assert.match(cart, /diningSessionPath/);
  assert.match(cart, /Navigate to=\{diningSessionPath/);

  const product = readSrc('pages/ProductDetailPage.tsx');
  assert.match(product, /isDiningOrderingContext/);
  assert.match(product, /diningMenuPath/);
});

test('orderingContext exposes tableLabel and live subscription', () => {
  const source = readSrc('utils/orderingContext.ts');
  assert.match(source, /tableLabel/);
  assert.match(source, /subscribeOrderingContext/);
  assert.match(source, /coffee:ordering-context/);
  assert.match(source, /isDiningOrderingContext/);

  const hook = readSrc('hooks/useOrderingContext.ts');
  assert.match(hook, /useSyncExternalStore/);
  assert.match(hook, /subscribeOrderingContext/);
});
