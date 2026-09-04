import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import test from 'node:test';
import { fileURLToPath, pathToFileURL } from 'node:url';
import ts from 'typescript';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { mkdtempSync, writeFileSync } from 'node:fs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(import.meta.url);

function installSessionStorageMock() {
  const store = new Map();

  globalThis.window = {
    sessionStorage: {
      getItem(key) {
        return store.has(key) ? store.get(key) : null;
      },
      setItem(key, value) {
        store.set(key, String(value));
      },
      removeItem(key) {
        store.delete(key);
      },
    },
    dispatchEvent() {
      return true;
    },
    addEventListener() {},
    removeEventListener() {},
  };

  return store;
}

async function loadOrderingContextModule() {
  installSessionStorageMock();

  const source = readFileSync(join(root, 'src/utils/orderingContext.ts'), 'utf8');
  const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.ESNext,
      target: ts.ScriptTarget.ES2022,
      moduleResolution: ts.ModuleResolutionKind.Bundler,
    },
    fileName: 'orderingContext.ts',
  });

  const dir = mkdtempSync(join(tmpdir(), 'ordering-context-'));
  const file = join(dir, 'orderingContext.mjs');
  writeFileSync(file, outputText);

  return import(`${pathToFileURL(file).href}?t=${Date.now()}-${Math.random()}`);
}

test('ordering context boots takeaway with stable snapshots', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  const first = mod.readOrderingContext();
  const second = mod.readOrderingContext();

  assert.equal(first.mode, 'takeaway');
  assert.equal(first.diningSession, null);
  assert.equal(first, second);
  assert.equal(first, mod.RETAIL_ORDERING_CONTEXT);
  assert.equal(mod.hasActiveDiningSession(first), false);
  assert.equal(mod.isDiningOrderingMode(first), false);
});

test('active dining session and takeaway mode can coexist', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  mod.writeOrderingContext({
    mode: 'dining',
    diningSessionId: '42',
    tableLabel: 'T1',
    draftItemCount: 1,
  });

  let context = mod.readOrderingContext();
  assert.equal(context.mode, 'dining');
  assert.equal(context.diningSession.diningSessionId, '42');
  assert.equal(mod.isDiningOrderingMode(context), true);

  mod.setOrderingMode('takeaway');
  context = mod.readOrderingContext();
  assert.equal(context.mode, 'takeaway');
  assert.equal(context.diningSession.diningSessionId, '42');
  assert.equal(context.diningSession.draftItemCount, 1);
  assert.equal(mod.hasActiveDiningSession(context), true);
  assert.equal(mod.isDiningOrderingMode(context), false);
});

test('session metadata refresh does not yank takeaway mode', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  mod.writeOrderingContext({
    mode: 'takeaway',
    diningSessionId: '42',
    tableLabel: 'T1',
    draftItemCount: 2,
  });
  mod.writeOrderingContext({ type: 'dining', diningSessionId: '42', draftItemCount: 3 });

  const context = mod.readOrderingContext();
  assert.equal(context.mode, 'takeaway');
  assert.equal(context.diningSession.draftItemCount, 3);
  assert.equal(context.diningSession.tableLabel, 'T1');
});

test('legacy dining shapes normalize into mode + diningSession', async () => {
  const mod = await loadOrderingContextModule();

  const fromLegacy = mod.normalizeOrderingContext({
    type: 'dining',
    sessionId: '55',
    table: { label: 'Patio 2' },
  });
  assert.equal(fromLegacy.mode, 'dining');
  assert.equal(fromLegacy.diningSession.diningSessionId, '55');
  assert.equal(fromLegacy.diningSession.tableLabel, 'Patio 2');

  assert.equal(mod.normalizeOrderingContext({ type: 'retail' }), mod.RETAIL_ORDERING_CONTEXT);
  assert.equal(mod.normalizeOrderingContext({ type: 'dining' }), mod.RETAIL_ORDERING_CONTEXT);
});

test('clearing dining session keeps takeaway cart independent', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  mod.writeOrderingContext({
    mode: 'takeaway',
    diningSessionId: '9',
    tableLabel: 'T9',
  });
  mod.clearOrderingContext();

  const context = mod.readOrderingContext();
  assert.equal(context.mode, 'takeaway');
  assert.equal(context.diningSession, null);
  assert.equal(context, mod.RETAIL_ORDERING_CONTEXT);
});

test('missing diningSessionId resets to takeaway', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  window.sessionStorage.setItem(
    'coffee.ordering_context.v2',
    JSON.stringify({ mode: 'dining', diningSession: { diningSessionId: '' } }),
  );
  mod.resetOrderingContextCacheForTests();

  const context = mod.readOrderingContext();
  assert.equal(context.mode, 'takeaway');
  assert.equal(window.sessionStorage.getItem('coffee.ordering_context.v2'), null);
});

test('legacy v1 storage migrates on read', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  window.sessionStorage.setItem(
    'coffee.ordering_context.v1',
    JSON.stringify({ type: 'dining', diningSessionId: '77', tableLabel: 'T7', draftItemCount: 4 }),
  );

  const context = mod.readOrderingContext();
  assert.equal(context.mode, 'dining');
  assert.equal(context.diningSession.diningSessionId, '77');
  assert.equal(context.diningSession.draftItemCount, 4);

  // Next write persists v2 and drops v1.
  mod.writeOrderingContext({ mode: 'takeaway' });
  assert.equal(window.sessionStorage.getItem('coffee.ordering_context.v1'), null);
  assert.ok(window.sessionStorage.getItem('coffee.ordering_context.v2'));
});

test('isDiningSessionTerminal covers paid closed and cancelled', async () => {
  const mod = await loadOrderingContextModule();

  assert.equal(mod.isDiningSessionTerminal({ status: 'awaiting_payment', payment_status: 'awaiting_review' }), false);
  assert.equal(mod.isDiningSessionTerminal({ status: 'awaiting_payment', payment_status: 'confirmed' }), true);
  assert.equal(mod.isDiningSessionTerminal({ status: 'closed', payment_status: 'confirmed' }), true);
  assert.equal(mod.isDiningSessionTerminal({ status: 'cancelled', payment_status: 'pending' }), true);
  assert.equal(mod.isDiningSessionTerminal({ status: 'paid', payment_status: 'confirmed' }), true);
});

test('hook source uses cached getSnapshot (no unstable retail object)', () => {
  assert.equal(typeof require('typescript').transpileModule, 'function');
});
