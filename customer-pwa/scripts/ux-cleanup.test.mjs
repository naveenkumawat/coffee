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

test('orderingContext separates active dining session from ordering mode', async () => {
  const source = readSrc('utils/orderingContext.ts');
  assert.match(source, /mode: OrderingMode/);
  assert.match(source, /diningSession/);
  assert.match(source, /setOrderingMode/);
  assert.match(source, /hasActiveDiningSession/);
  assert.match(source, /isDiningOrderingMode/);
  assert.match(source, /sessionStorage/);
});

test('dining session UI removes raw item select and qty form', () => {
  const source = readSrc('pages/DiningSessionPage.tsx');
  assert.doesNotMatch(source, /<select/);
  assert.doesNotMatch(source, /Add to draft/);
  assert.doesNotMatch(source, /Place round/);
  assert.doesNotMatch(source, /Your next round/);
  assert.doesNotMatch(source, /Clear round/);
  assert.doesNotMatch(source, /Round total/);
  assert.doesNotMatch(source, /Round \{/);
  assert.doesNotMatch(source, /another round/);
  assert.doesNotMatch(source, /in next round/);
  assert.doesNotMatch(source, /\} round\{/);
  assert.match(source, /Table \{session\.table\.label\}/);
  assert.match(source, /Running bill/);
  assert.match(source, /formatCurrency\(billTotal\)/);
  assert.match(source, /Add items/);
  assert.match(source, /Order takeaway/);
  assert.match(source, /Order more for Table/);
  assert.match(source, /Your next order/);
  assert.match(source, /Place order/);
  assert.match(source, /Clear items/);
  assert.match(source, /Order total/);
  assert.match(source, /Order \{round\.displayNumber\}/);
  assert.match(source, /Your orders/);
  assert.match(source, /Request bill/);
  assert.match(source, /Call waiter/);
  assert.match(source, /diningMenuPath/);
  assert.match(source, /mode: 'takeaway'/);
  assert.match(source, /aria-expanded/);
  assert.match(source, /order\{rounds\.length === 1 \? '' : 's'\}/);
});

test('dining menu deep-link binds dining mode then opens shared Menu', () => {
  const source = readSrc('pages/DiningMenuPage.tsx');
  assert.match(source, /Navigate to="\/menu"/);
  assert.match(source, /mode: 'dining'/);
  assert.match(source, /writeOrderingContext/);
  assert.doesNotMatch(source, /useCartStore/);
});

test('shared Menu supports Dining\/Takeaway switcher without cart trap', () => {
  const menu = readSrc('pages/MenuPage.tsx');
  assert.match(menu, /OrderingModeSwitcher/);
  assert.match(menu, /isDiningOrderingMode/);
  assert.match(menu, /hasActiveDiningSession/);
  assert.match(menu, /View order/);
  assert.match(menu, /Back to table/);
  assert.doesNotMatch(menu, /Navigate to=\{diningMenuPath/);
  assert.doesNotMatch(menu, /Navigate to=\{diningSessionPath/);
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
  assert.match(theme, /--dining-content-max-tablet:\s*48rem/);
  assert.match(theme, /--dining-content-max-desktop:\s*56rem/);
  assert.match(theme, /\.app-shell\s*\{[\s\S]*?var\(--customer-content-max\)/);
  assert.match(theme, /\.dining-content\s*\{[\s\S]*?max-width:\s*none/);
  assert.match(theme, /@media \(min-width: 768px\)[\s\S]*?\.dining-content[\s\S]*?var\(--dining-content-max-tablet\)/);
  assert.match(theme, /@media \(min-width: 1024px\)[\s\S]*?\.dining-content[\s\S]*?var\(--dining-content-max-desktop\)/);
  assert.match(theme, /\.dining-menu-page \.dining-content\s*\{[\s\S]*?max-width:\s*none/);
  assert.match(theme, /\.dining-page,[\s\S]*?\.dining-session-page,[\s\S]*?max-width:\s*none/);
  assert.doesNotMatch(
    theme,
    /\.dining-session-page,[\s\S]{0,200}max-width:\s*min\(100%,\s*var\(--coffee-shell-max\)\)/,
  );
});

test('dining session and bill use inner dining-content inside shared page shell', () => {
  const session = readSrc('pages/DiningSessionPage.tsx');
  assert.match(session, /page-container dining-session-page/);
  assert.match(session, /className="dining-content"/);
  assert.match(session, /tableLabel:/);

  const bill = readSrc('pages/DiningBillPage.tsx');
  assert.match(bill, /page-container dining-bill-page/);
  assert.match(bill, /className="dining-content"/);
  assert.match(bill, /tableLabel:/);

  const entry = readSrc('pages/DiningPage.tsx');
  assert.match(entry, /page-container dining-page/);
  assert.match(entry, /className="dining-content"/);
});

test('dining entry page uses table cards guest stepper and start dining CTA', () => {
  const source = readSrc('pages/DiningPage.tsx');
  assert.match(source, /PageHeader/);
  assert.match(source, /dining-table-card/);
  assert.match(source, /dining-table-grid/);
  assert.match(source, /QuantityStepper/);
  assert.match(source, /Decrease guests/);
  assert.match(source, /Increase guests/);
  assert.match(source, /Start dining/);
  assert.match(source, /Starting session…/);
  assert.match(source, /Return to table/);
  assert.match(source, /fetchActiveDiningSession/);
  assert.match(source, /writeOrderingContext/);
  assert.match(source, /clearOrderingContext/);
  assert.match(source, /No tables available right now/);
  assert.match(source, /aria-selected/);
  assert.match(source, /is-selected/);
  assert.match(source, /is-unavailable/);
  assert.match(source, /disabled=\{!canStart \|\| submitting\}/);
  assert.match(source, /submitting/);
  assert.doesNotMatch(source, /<select[\s>]/);
  assert.doesNotMatch(source, /type=["']number["']/);
  assert.doesNotMatch(source, /Start session/);
  assert.doesNotMatch(source, /navigate\(`\/dining\/sessions\/\$\{active\.data\.id\}`/);

  const theme = readSrc('assets/styles/theme.css');
  assert.match(theme, /\.dining-table-grid/);
  assert.match(theme, /\.dining-table-card/);
  assert.match(theme, /\.dining-table-card\.is-selected/);
  assert.match(theme, /\.app-shell\s*\{[\s\S]*?display:\s*flex/);
  assert.match(theme, /\.app-main\s*>\s*\.site-footer\s*\{[\s\S]*?margin-top:\s*auto/);
  assert.match(theme, /grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/);
});

test('customer footer always keeps Home/Menu/Dining/Cart/Account with retail cart', () => {
  const source = readSrc('components/navigation/BottomNavigation.tsx');
  assert.match(source, /useOrderingContext/);
  assert.match(source, /hasActiveDiningSession/);
  assert.match(source, /diningSessionPath/);
  assert.doesNotMatch(source, /Book table/i);
  assert.doesNotMatch(source, /label:\s*'Table'/);

  assert.match(source, /label: 'Home'/);
  assert.match(source, /label: 'Menu'/);
  assert.match(source, /label: 'Dining'/);
  assert.match(source, /label: 'Cart'/);
  assert.match(source, /label: isAuthenticated \? 'Account'/);
  assert.match(source, /AppIcons\.cart/);
  assert.match(source, /AppIcons\.dining/);

  assert.match(source, /menuTo = '\/menu'/);
  assert.match(source, /cartTo = '\/cart'/);
  assert.match(source, /cartCount = retailCartCount/);
  assert.match(source, /diningTo = diningSession/);
  assert.doesNotMatch(source, /draftItemCount/);
  assert.doesNotMatch(source, /diningMenuPath/);
  assert.doesNotMatch(source, /!diningContext && diningEnabled/);
  assert.doesNotMatch(source, /diningEnabled/);

  assert.match(source, /isDiningSessionSurfacePath/);
  assert.match(source, /isNavActive/);
});

test('product order control routes dining mode into dining draft', () => {
  const control = readSrc('components/catalog/ProductOrderControl.tsx');
  assert.match(control, /useOrderingAddHandler/);
  assert.match(control, /effectiveHandler/);

  const handler = readSrc('hooks/useOrderingAddHandler.ts');
  assert.match(handler, /addDiningDraft/);
  assert.match(handler, /isDiningOrderingMode/);
  assert.match(handler, /Added to your next order/);
});

test('dining add-items sticky bar lives on shared Menu in dining mode', () => {
  const source = readSrc('pages/MenuPage.tsx');
  assert.match(source, /No items yet/);
  assert.match(source, /Back to table/);
  assert.match(source, /View order/);
  assert.match(source, /diningDraftCount/);
  assert.doesNotMatch(source, /Book table/i);
  assert.doesNotMatch(source, /to="\/cart"/);
});

test('customer dining uses designed confirm dialog instead of browser confirm', () => {
  const session = readSrc('pages/DiningSessionPage.tsx');
  const orderDetail = readSrc('pages/OrderDetailPage.tsx');
  const confirmation = readSrc('pages/OrderConfirmationPage.tsx');
  const addresses = readSrc('pages/DeliveryAddressesPage.tsx');
  const waiter = readSrc('pages/waiter/WaiterSessionPage.tsx');
  const dialog = readSrc('components/common/ConfirmDialog.tsx');
  const select = readSrc('components/common/SearchableSelect.tsx');

  assert.match(dialog, /export function confirmAction/);
  assert.match(dialog, /ConfirmDialogHost/);
  assert.match(select, /export function SearchableSelect/);
  assert.match(session, /Request the bill\?/);
  assert.match(session, /confirmYes/);
  assert.match(orderDetail, /confirmYes/);
  assert.match(confirmation, /confirmYes/);
  assert.match(addresses, /confirmYes/);
  assert.match(waiter, /Resume ordering/);
  assert.match(waiter, /Accept Round/);

  const bill = readSrc('pages/DiningBillPage.tsx');
  assert.match(bill, /Submit Transaction ID\?/);
  assert.doesNotMatch(bill, /window\.confirm\(/);

  for (const [label, source] of [
    ['session', session],
    ['orderDetail', orderDetail],
    ['confirmation', confirmation],
    ['addresses', addresses],
    ['waiter', waiter],
  ]) {
    assert.doesNotMatch(source, /window\.confirm\(/, `${label} still uses window.confirm`);
  }
});

test('customer dining copy uses Order instead of Round', () => {
  const session = readSrc('pages/DiningSessionPage.tsx');
  const menu = readSrc('pages/MenuPage.tsx');
  const bill = readSrc('pages/DiningBillPage.tsx');
  const handler = readSrc('hooks/useOrderingAddHandler.ts');

  assert.match(session, /Order \{round\.displayNumber\}/);
  assert.match(session, /Your next order/);
  assert.match(session, /Place order/);
  assert.match(session, /Clear items/);
  assert.match(menu, /View order/);
  assert.match(menu, /in next order/);
  assert.match(handler, /Add to order|Added to your next order/);
  assert.match(bill, /order\{roundCount === 1 \? '' : 's'\}/);

  for (const [label, source] of [
    ['session', session],
    ['menu', menu],
    ['bill', bill],
  ]) {
    assert.doesNotMatch(source, /Your next round/, `${label} still says next round`);
    assert.doesNotMatch(source, /Place round/, `${label} still says Place round`);
    assert.doesNotMatch(source, /Add to round/, `${label} still says Add to round`);
    assert.doesNotMatch(source, /Clear round/, `${label} still says Clear round`);
    assert.doesNotMatch(source, /View round/, `${label} still says View round`);
    assert.doesNotMatch(source, /Round total/, `${label} still says Round total`);
    assert.doesNotMatch(source, /Round \{/, `${label} still says Round {`);
    assert.doesNotMatch(source, / in next round/, `${label} still says in next round`);
    assert.doesNotMatch(source, /\} round\{/, `${label} still counts rounds in copy`);
  }
});

test('menu and cart stay independent while dining session is active', () => {
  const menu = readSrc('pages/MenuPage.tsx');
  assert.match(menu, /OrderingModeSwitcher/);
  assert.doesNotMatch(menu, /Navigate to=\{diningMenuPath/);
  assert.doesNotMatch(menu, /navigate\(diningMenuPath/);

  const cart = readSrc('pages/CartPage.tsx');
  assert.doesNotMatch(cart, /isDiningOrderingContext/);
  assert.doesNotMatch(cart, /Navigate to=\{diningSessionPath/);
  assert.doesNotMatch(cart, /navigate\(diningSessionPath/);

  const product = readSrc('pages/ProductDetailPage.tsx');
  assert.match(product, /OrderingModeSwitcher/);
  assert.doesNotMatch(product, /Navigate to=\{diningMenuPath/);
  assert.doesNotMatch(product, /navigate\(diningMenuPath/);

  const diningMenu = readSrc('pages/DiningMenuPage.tsx');
  assert.match(diningMenu, /Navigate to="\/menu"/);
  assert.doesNotMatch(diningMenu, /Navigate to=\{?['"`]\/cart/);
});

test('orderingContext caches snapshots and hardens stale dining shapes', () => {
  const source = readSrc('utils/orderingContext.ts');
  assert.match(source, /tableLabel/);
  assert.match(source, /draftItemCount/);
  assert.match(source, /diningDraftItemCount/);
  assert.match(source, /subscribeOrderingContext/);
  assert.match(source, /coffee:ordering-context/);
  assert.match(source, /hasActiveDiningSession/);
  assert.match(source, /isDiningOrderingMode/);
  assert.match(source, /isDiningSessionTerminal/);
  assert.match(source, /normalizeOrderingContext/);
  assert.match(source, /RETAIL_ORDERING_CONTEXT/);
  assert.match(source, /cachedRaw/);
  assert.match(source, /rememberSnapshot/);

  const hook = readSrc('hooks/useOrderingContext.ts');
  assert.match(hook, /useSyncExternalStore/);
  assert.match(hook, /subscribeOrderingContext/);
  assert.match(hook, /readOrderingContext/);
  assert.match(hook, /RETAIL_ORDERING_CONTEXT/);
  assert.doesNotMatch(hook, /getSnapshot[\s\S]{0,80}return\s*\{\s*type:\s*['"]retail['"]/);
});

test('dining session and bill pages leave completed sessions for /dining', () => {
  const session = readSrc('pages/DiningSessionPage.tsx');
  assert.match(session, /isDiningSessionTerminal/);
  assert.match(session, /clearOrderingContext/);
  assert.match(session, /navigate\(['"`]\/dining['"`],\s*\{\s*replace:\s*true/);
  assert.match(session, /useDiningOpsSync/);
  assert.match(session, /useLiveCanonicalSync/);

  const bill = readSrc('pages/DiningBillPage.tsx');
  assert.match(bill, /isDiningSessionTerminal/);
  assert.match(bill, /clearOrderingContext/);
});

test('dining bill payment uses catalog methods and UTR not screenshot upload', () => {
  const bill = readSrc('pages/DiningBillPage.tsx');
  assert.match(bill, /PaymentMethodSelector/);
  assert.match(bill, /submitDiningPaymentTransactionId/);
  assert.match(bill, /Transaction ID \/ UTR/);
  assert.match(bill, /Verification Pending/);
  assert.match(bill, /OrderTaxBreakdown/);
  assert.match(bill, /diningDiscountLines/);
  assert.match(bill, /can_resubmit_transaction_id/);
  assert.match(bill, /Submit new transaction ID/);
  assert.match(bill, /showUtrForm/);
  assert.match(bill, /Payment confirmed/);
  assert.match(bill, /Your table session is complete/);
  assert.match(bill, /clearOrderingContext/);
  assert.match(bill, /isDiningSessionTerminal/);
  assert.match(bill, /navigate\(['"`]\/dining['"`],\s*\{\s*replace:\s*true/);
  assert.match(bill, /useDiningOpsSync/);
  assert.match(bill, /useLiveCanonicalSync/);
  assert.match(bill, /Back to table/);
  assert.doesNotMatch(bill, /type=["']file["']/);
  assert.doesNotMatch(bill, /Upload proof/i);
  assert.doesNotMatch(bill, /UPI payment proof/i);
  assert.doesNotMatch(bill, /Resubmit for Verification/);
  assert.doesNotMatch(bill, /Replace Transaction ID/);
  assert.doesNotMatch(bill, /<dt>Discount<\/dt>/);

  const diningApi = readSrc('api/dining.ts');
  assert.match(diningApi, /submitDiningPaymentTransactionId/);
  assert.match(diningApi, /can_resubmit_transaction_id/);
  assert.doesNotMatch(diningApi, /uploadDiningPaymentProof/);
  assert.doesNotMatch(diningApi, /FormData/);

  const router = readSrc('routes/router.tsx');
  assert.match(router, /DiningBillPage/);
  assert.match(router, /pages\/DiningBillPage/);
});

test('shared discount helpers prefer named backend lines over generic Discount', () => {
  const source = readSrc('utils/discounts.ts');
  assert.match(source, /export function discountDisplayLabel/);
  assert.match(source, /export function orderDiscountLines/);
  assert.match(source, /export function diningDiscountLines/);
  assert.match(source, /order\.discounts/);
  assert.match(source, /name: 'Discount'/);

  const breakdown = readSrc('components/orders/OrderTaxBreakdown.tsx');
  assert.match(breakdown, /discountDisplayLabel/);
});

test('route error page logs the real error in development', () => {
  const source = readSrc('pages/RouteErrorPage.tsx');
  assert.match(source, /import\.meta\.env\.DEV/);
  assert.match(source, /console\.error/);
  assert.match(source, /Something went wrong/);
});
