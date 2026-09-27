import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';
import test from 'node:test';
import { fileURLToPath, pathToFileURL } from 'node:url';
import ts from 'typescript';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

async function loadModule(relativePath) {
  const source = readFileSync(join(root, relativePath), 'utf8');
  const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.ESNext,
      target: ts.ScriptTarget.ES2022,
    },
    fileName: relativePath,
  });
  const dir = mkdtempSync(join(tmpdir(), 'cart-capability-'));
  const file = join(dir, 'mod.mjs');
  writeFileSync(file, outputText.replaceAll("from '../types/content'", "from './types.mjs'"));

  return import(`${pathToFileURL(file).href}?t=${Date.now()}-${Math.random()}`);
}

test('cart capability defaults on and lets bootstrap override stale content', async () => {
  const { resolveCartEnabled } = await loadModule('src/utils/cartCapability.ts');
  const staleOn = { fulfilment: { cart_enabled: true, delivery_disclaimer: null } };
  const staleOff = { fulfilment: { cart_enabled: false, delivery_disclaimer: null } };

  assert.equal(
    resolveCartEnabled({ bootstrapCartEnabled: null, storedCartEnabled: null, content: null }),
    true,
  );
  assert.equal(
    resolveCartEnabled({ bootstrapCartEnabled: false, storedCartEnabled: true, content: staleOn }),
    false,
  );
  assert.equal(
    resolveCartEnabled({ bootstrapCartEnabled: true, storedCartEnabled: false, content: staleOff }),
    true,
  );
  assert.equal(
    resolveCartEnabled({ bootstrapCartEnabled: null, storedCartEnabled: null, content: staleOff }),
    false,
  );
  assert.equal(
    resolveCartEnabled({ bootstrapCartEnabled: null, storedCartEnabled: false, content: staleOn }),
    false,
  );
});

test('customer footer redistributes cart and dining slots', async () => {
  const { customerFooterSlots } = await loadModule('src/utils/customerFooter.ts');

  assert.deepEqual(customerFooterSlots({ cartEnabled: true, showDiningNav: true }), [
    'home',
    'menu',
    'dining',
    'cart',
    'account',
  ]);
  assert.deepEqual(customerFooterSlots({ cartEnabled: true, showDiningNav: false }), [
    'home',
    'menu',
    'cart',
    'account',
  ]);
  assert.deepEqual(customerFooterSlots({ cartEnabled: false, showDiningNav: true }), [
    'home',
    'menu',
    'dining',
    'account',
  ]);
  assert.deepEqual(customerFooterSlots({ cartEnabled: false, showDiningNav: false }), [
    'home',
    'menu',
    'account',
  ]);
});
