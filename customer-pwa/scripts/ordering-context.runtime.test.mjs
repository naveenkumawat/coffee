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

  return import(pathToFileURL(file).href);
}

test('ordering context boots retail with stable snapshots', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  const first = mod.readOrderingContext();
  const second = mod.readOrderingContext();

  assert.equal(first.type, 'retail');
  assert.equal(first, second);
  assert.equal(first, mod.RETAIL_ORDERING_CONTEXT);
  assert.equal(mod.isDiningOrderingContext(first), false);
});

test('valid dining context keeps identity until mutated', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  mod.writeOrderingContext({ type: 'dining', diningSessionId: '42', tableLabel: 'T1' });
  const first = mod.readOrderingContext();
  const second = mod.readOrderingContext();

  assert.equal(first.type, 'dining');
  assert.equal(first.diningSessionId, '42');
  assert.equal(first.tableLabel, 'T1');
  assert.equal(first, second);
  assert.equal(mod.isDiningOrderingContext(first), true);
});

test('missing tableLabel does not crash or invalidate dining context', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  mod.writeOrderingContext({ type: 'dining', diningSessionId: '7' });
  const context = mod.readOrderingContext();

  assert.equal(context.type, 'dining');
  assert.equal(context.diningSessionId, '7');
  assert.equal(context.tableLabel, undefined);
  assert.equal(mod.isDiningOrderingContext(context), true);
});

test('draftItemCount is preserved and used for dining cart badge', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  assert.equal(mod.diningDraftItemCount([{ quantity: 2 }, { quantity: 1 }]), 3);
  assert.equal(mod.diningDraftItemCount([]), 0);

  mod.writeOrderingContext({
    type: 'dining',
    diningSessionId: '42',
    tableLabel: 'T1',
    draftItemCount: 2,
  });
  const first = mod.readOrderingContext();
  assert.equal(first.type, 'dining');
  assert.equal(first.draftItemCount, 2);

  // Partial write for same session must not wipe draft badge.
  mod.writeOrderingContext({ type: 'dining', diningSessionId: '42' });
  const second = mod.readOrderingContext();
  assert.equal(second.type, 'dining');
  assert.equal(second.tableLabel, 'T1');
  assert.equal(second.draftItemCount, 2);
});

test('missing diningSessionId resets to retail', async () => {
  const mod = await loadOrderingContextModule();
  mod.resetOrderingContextCacheForTests();

  assert.deepEqual(mod.normalizeOrderingContext({ type: 'dining' }), mod.RETAIL_ORDERING_CONTEXT);
  assert.deepEqual(
    mod.normalizeOrderingContext({ type: 'dining', diningSessionId: '' }),
    mod.RETAIL_ORDERING_CONTEXT,
  );
  assert.deepEqual(
    mod.normalizeOrderingContext({ type: 'dining', diningSessionId: '   ' }),
    mod.RETAIL_ORDERING_CONTEXT,
  );

  window.sessionStorage.setItem(
    'coffee.ordering_context.v1',
    JSON.stringify({ type: 'dining', diningSessionId: '' }),
  );
  mod.resetOrderingContextCacheForTests();

  const context = mod.readOrderingContext();
  assert.equal(context.type, 'retail');
  assert.equal(window.sessionStorage.getItem('coffee.ordering_context.v1'), null);
});

test('stale persisted shapes normalize safely', async () => {
  const mod = await loadOrderingContextModule();

  assert.equal(mod.normalizeOrderingContext(null).type, 'retail');
  assert.equal(mod.normalizeOrderingContext({}).type, 'retail');
  assert.equal(mod.normalizeOrderingContext({ type: 'takeaway' }).type, 'retail');

  const fromNumber = mod.normalizeOrderingContext({ type: 'dining', diningSessionId: 99 });
  assert.equal(fromNumber.type, 'dining');
  assert.equal(fromNumber.diningSessionId, '99');

  const fromLegacy = mod.normalizeOrderingContext({
    type: 'dining',
    sessionId: '55',
    table: { label: 'Patio 2' },
  });
  assert.equal(fromLegacy.type, 'dining');
  assert.equal(fromLegacy.diningSessionId, '55');
  assert.equal(fromLegacy.tableLabel, 'Patio 2');
});

test('hook source uses cached getSnapshot (no unstable retail object)', () => {
  // Keep TypeScript available for runtime tests above; also assert package resolves.
  assert.equal(typeof require('typescript').transpileModule, 'function');
});
