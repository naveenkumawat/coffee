import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import test from 'node:test';
import { fileURLToPath, pathToFileURL } from 'node:url';
import ts from 'typescript';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

async function loadModule(relativePath) {
  const source = readFileSync(join(root, relativePath), 'utf8');
  const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.ESNext,
      target: ts.ScriptTarget.ES2022,
      moduleResolution: ts.ModuleResolutionKind.Bundler,
    },
    fileName: relativePath,
  });
  const dir = mkdtempSync(join(tmpdir(), 'dining-state-'));
  const file = join(dir, 'mod.mjs');
  writeFileSync(file, outputText);

  return import(`${pathToFileURL(file).href}?t=${Date.now()}-${Math.random()}`);
}

test('active session restores even while loading or dining is disabled', async () => {
  const { resolveDiningPageView } = await loadModule('src/utils/diningPageView.ts');

  assert.equal(
    resolveDiningPageView({
      loading: true,
      hasBootstrapped: true,
      hasActiveSession: true,
      diningEnabled: false,
    }),
    'active-session',
  );
  assert.equal(
    resolveDiningPageView({
      loading: false,
      hasBootstrapped: true,
      hasActiveSession: true,
      diningEnabled: false,
    }),
    'active-session',
  );
});

test('dining disabled with no session settles to unavailable after bootstrap', async () => {
  const { resolveDiningPageView, diningPageShouldKeepLoading } = await loadModule(
    'src/utils/diningPageView.ts',
  );

  assert.equal(diningPageShouldKeepLoading(false, false), true);
  assert.equal(diningPageShouldKeepLoading(true, false), false);
  assert.equal(diningPageShouldKeepLoading(false, true), false);

  assert.equal(
    resolveDiningPageView({
      loading: true,
      hasBootstrapped: false,
      hasActiveSession: false,
      diningEnabled: false,
    }),
    'loading',
  );
  assert.equal(
    resolveDiningPageView({
      loading: false,
      hasBootstrapped: true,
      hasActiveSession: false,
      diningEnabled: false,
    }),
    'unavailable',
  );
});

test('content fetch failure keeps bootstrap dining_enabled and never defaults true', async () => {
  const { resolveDiningPageView } = await loadModule('src/utils/diningPageView.ts');
  const { resolveDiningCapability, diningEnabledForNewSession } = await loadModule(
    'src/utils/diningCapability.ts',
  );

  const afterFail = (bootstrapDiningEnabled) => {
    const capability =
      resolveDiningCapability({
        bootstrapDiningEnabled,
        storedDiningEnabled: null,
        content: null,
      }) ?? false;

    return resolveDiningPageView({
      loading: false,
      hasBootstrapped: true,
      hasActiveSession: false,
      diningEnabled: capability,
    });
  };

  assert.equal(afterFail(false), 'unavailable');
  assert.equal(afterFail(true), 'start');
  assert.equal(afterFail(null), 'unavailable');
  assert.equal(diningEnabledForNewSession(null), false);
  assert.equal(diningEnabledForNewSession(true), true);
});

test('cached dining ON cannot override bootstrap OFF', async () => {
  const { resolveDiningCapability } = await loadModule('src/utils/diningCapability.ts');

  assert.equal(
    resolveDiningCapability({
      bootstrapDiningEnabled: false,
      storedDiningEnabled: true,
      content: {
        fulfilment: { dining_enabled: true, dine_in_enabled: true, delivery_disclaimer: null },
      },
    }),
    false,
  );
});
